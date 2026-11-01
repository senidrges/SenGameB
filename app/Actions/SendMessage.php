<?php

namespace App\Actions;

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
     * tell the other participants.
     *
     * @param  array{message_type: string, content?: string|null, file_path?: string|null, file_name?: string|null, file_size?: int|null, payload?: array<string, mixed>|null, reply_to_id?: string|null, forwarded_from_id?: string|null}  $attributes
     */
    public function handle(Conversation $conversation, User $sender, array $attributes): Message
    {
        $message = DB::transaction(function () use ($conversation, $sender, $attributes): Message {
            $message = $conversation->messages()->create([...$attributes, 'sender_id' => $sender->id]);
            $conversation->touch();

            return $message;
        });

        $message->setRelation('sender', $sender)->load('replyTo.sender');

        $participants = $conversation->participants()->get();

        Realtime::send(new MessageCreated($message, $participants->modelKeys()));

        Notification::send(
            $participants->reject(fn (User $participant) => $participant->is($sender)
                || Conversation::pivotIsMuted($participant->pivot)),
            new NewChatMessage($message),
        );

        return $message;
    }
}
