<?php

namespace App\Actions;

use App\Events\GroupMessagesReceipts;
use App\Models\Conversation;
use App\Models\Message;
use App\Models\User;
use App\Support\Realtime;
use Illuminate\Support\Carbon;

/**
 * Ticks of a group, like WhatsApp: a message is delivered (two grey ticks)
 * once the phone of every other current member received it, and read (two
 * blue ticks) once every one of them read it. Each member's progress is
 * kept on their pivot row (`last_delivered_at`, `last_read_at`), which also
 * gives the "Infos du message" screen.
 */
class UpdateGroupReceipts
{
    /**
     * The user opened the group: everything sent so far is read.
     */
    public function markRead(Conversation $conversation, User $user): void
    {
        $conversation->participants()->updateExistingPivot($user->id, [
            'last_read_at' => now(),
            'last_delivered_at' => now(),
        ]);

        $this->refresh($conversation);
    }

    /**
     * The user's phone received the messages of these groups.
     *
     * @param  list<string>  $conversationIds
     */
    public function markDelivered(User $user, array $conversationIds): void
    {
        if ($conversationIds === []) {
            return;
        }

        $user->conversations()->newPivotQuery()
            ->where('user_id', $user->id)
            ->whereIn('conversation_id', $conversationIds)
            ->whereNull('left_at')
            ->update(['last_delivered_at' => now()]);

        Conversation::query()->whereKey($conversationIds)->get()
            ->each(fn (Conversation $conversation) => $this->refresh($conversation));
    }

    /**
     * Mark the messages every other member received or read, and tell the
     * phones showing the group and the senders' lists.
     */
    public function refresh(Conversation $conversation): void
    {
        $members = $conversation->members()->get();
        $readIds = [];
        $deliveredIds = [];
        $senderIds = [];

        foreach ($members as $sender) {
            $others = $members->reject(fn (User $member) => $member->is($sender));
            if ($others->isEmpty()) {
                continue;
            }

            $readUntil = $this->earliest($others->map(fn (User $other) => $other->pivot->last_read_at)->all());
            $deliveredUntil = $this->earliest($others->map(fn (User $other) => $this->latest(
                $other->pivot->last_delivered_at,
                $other->pivot->last_read_at,
            ))->all());

            $messages = $conversation->messages()->where('sender_id', $sender->id);

            if ($readUntil !== null) {
                $ids = (clone $messages)->where('is_read', false)->where('created_at', '<=', $readUntil)->pluck('id')->all();
                Message::query()->whereKey($ids)->update(['is_read' => true, 'is_delivered' => true]);
                $readIds = [...$readIds, ...$ids];
            }

            if ($deliveredUntil !== null) {
                $ids = (clone $messages)->where('is_delivered', false)->where('created_at', '<=', $deliveredUntil)->pluck('id')->all();
                Message::query()->whereKey($ids)->update(['is_delivered' => true]);
                $deliveredIds = [...$deliveredIds, ...$ids];
            }

            if ($readIds !== [] || $deliveredIds !== []) {
                $senderIds[] = $sender->id;
            }
        }

        if ($readIds !== [] || $deliveredIds !== []) {
            Realtime::send(new GroupMessagesReceipts($conversation->id, $readIds, $deliveredIds, array_values(array_unique($senderIds))));
        }
    }

    /**
     * The earliest moment, or null when one of them never happened.
     *
     * @param  list<Carbon|string|null>  $moments
     */
    private function earliest(array $moments): ?Carbon
    {
        $earliest = null;
        foreach ($moments as $moment) {
            if ($moment === null) {
                return null;
            }
            $moment = Carbon::parse($moment);
            $earliest = $earliest === null || $moment->lt($earliest) ? $moment : $earliest;
        }

        return $earliest;
    }

    private function latest(Carbon|string|null $first, Carbon|string|null $second): ?Carbon
    {
        if ($first === null || $second === null) {
            return $first === null ? ($second === null ? null : Carbon::parse($second)) : Carbon::parse($first);
        }

        return Carbon::parse($first)->max(Carbon::parse($second));
    }
}
