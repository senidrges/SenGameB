<?php

namespace App\Actions;

use App\Enums\GroupRole;
use App\Enums\MessageType;
use App\Events\MessageCreated;
use App\Models\Conversation;
use App\Models\Message;
use App\Models\User;
use App\Notifications\NewChatMessage;
use App\Support\Realtime;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;

class SendMessage
{
    /**
     * Store a message, move the conversation to the top of the list and
     * tell the other participants (the current members of a group).
     *
     * Like WhatsApp:
     * - with disappearing messages on, the message goes after that time;
     * - the members named with @ get a push even when they muted the group;
     * - group notices only notify $notifyIds (e.g. those just added);
     * - a notice for the admins (audience) only reaches them.
     *
     * @param  array{message_type: string, content?: string|null, file_path?: string|null, file_name?: string|null, file_size?: int|null, payload?: array<string, mixed>|null, reply_to_id?: string|null, forwarded_from_id?: string|null, audience?: string|null, mentions?: list<string>}  $attributes
     * @param  list<string>|null  $notifyIds
     */
    public function handle(Conversation $conversation, User $sender, array $attributes, ?array $notifyIds = null): Message
    {
        $mentionIds = $attributes['mentions'] ?? [];
        unset($attributes['mentions']);

        $isNotice = ($attributes['message_type'] ?? null) === MessageType::System->value;

        $message = DB::transaction(function () use ($conversation, $sender, $attributes, $mentionIds, $isNotice): Message {
            $message = $conversation->messages()->create([
                ...$attributes,
                'sender_id' => $sender->id,
                // Notices stay; the rest disappears when the chat says so
                'expires_at' => ! $isNotice && $conversation->disappearing_seconds !== null
                    ? now()->addSeconds($conversation->disappearing_seconds)
                    : null,
            ]);
            $conversation->touch();

            if ($mentionIds !== []) {
                $message->mentions()->attach($mentionIds);
            }

            // Writing in a group means having read it
            if ($conversation->isGroup() && $conversation->hasMember($sender)) {
                $conversation->participants()->updateExistingPivot($sender->id, [
                    'last_read_at' => $message->created_at,
                    'last_delivered_at' => $message->created_at,
                ]);
            }

            return $message;
        });

        $message->setRelation('sender', $sender)->setRelation('conversation', $conversation)->load(['replyTo.sender', 'mentions:id']);

        $members = $conversation->members()->get();
        $isForAdmins = $message->audience === Message::AUDIENCE_ADMINS;

        if ($isForAdmins) {
            $recipients = $members->filter(fn (User $member) => $member->is($sender) || $member->pivot->role === GroupRole::Admin->value);
            Realtime::send(new MessageCreated($message, $recipients->modelKeys(), toConversation: false));
        } else {
            Realtime::send(new MessageCreated($message, $members->modelKeys()));
        }

        Notification::send(
            $members->reject(fn (User $member) => $member->is($sender)
                || ($isNotice && ! in_array($member->id, $notifyIds ?? [], true))
                || (Conversation::pivotIsMuted($member->pivot) && ! in_array($member->id, $mentionIds, true))),
            new NewChatMessage($message),
        );

        return $message;
    }
}
