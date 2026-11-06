<?php

namespace App\Actions;

use App\Events\GameMessagesReceipts;
use App\Models\GameMessage;
use App\Models\LoveGame;
use App\Models\User;
use App\Support\Realtime;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Ticks of the game chat, like WhatsApp groups: a message is delivered
 * (two grey ticks) once the phone of every other participant received it,
 * and read (two colored ticks) once every one of them read it.
 */
class UpdateGameReceipts
{
    /**
     * The user opened the game chat: everything sent so far is read.
     */
    public function markRead(LoveGame $game, User $user): void
    {
        $game->participants()->updateExistingPivot($user->id, [
            'last_read_at' => now(),
            'last_delivered_at' => now(),
        ]);

        $this->refresh($game);
    }

    /**
     * The user's phone received the messages of all their games.
     */
    public function markDeliveredFor(User $user): void
    {
        $gameIds = GameMessage::query()
            ->whereIn('game_id', DB::table('game_participants')->where('user_id', $user->id)->select('game_id'))
            ->where('sender_id', '!=', $user->id)
            ->where('is_delivered', false)
            ->distinct()
            ->pluck('game_id');

        if ($gameIds->isEmpty()) {
            return;
        }

        DB::table('game_participants')
            ->where('user_id', $user->id)
            ->whereIn('game_id', $gameIds)
            ->update(['last_delivered_at' => now()]);

        LoveGame::query()->whereKey($gameIds)->get()->each(fn (LoveGame $game) => $this->refresh($game));
    }

    /**
     * Mark the messages every other participant received or read, and tell
     * the phones showing the chat.
     */
    private function refresh(LoveGame $game): void
    {
        $participants = $game->participants()->get();
        $readIds = [];
        $deliveredIds = [];

        foreach ($participants as $sender) {
            $others = $participants->reject(fn (User $participant) => $participant->is($sender));
            if ($others->isEmpty()) {
                continue;
            }

            $readUntil = $this->earliest($others->map(fn (User $other) => $other->pivot->last_read_at)->all());
            $deliveredUntil = $this->earliest($others->map(fn (User $other) => $this->latest(
                $other->pivot->last_delivered_at,
                $other->pivot->last_read_at,
            ))->all());

            $messages = $game->messages()->where('sender_id', $sender->id);

            if ($readUntil !== null) {
                $ids = (clone $messages)->where('is_read', false)->where('created_at', '<=', $readUntil)->pluck('id')->all();
                GameMessage::query()->whereKey($ids)->update(['is_read' => true, 'is_delivered' => true]);
                $readIds = [...$readIds, ...$ids];
            }

            if ($deliveredUntil !== null) {
                $ids = (clone $messages)->where('is_delivered', false)->where('created_at', '<=', $deliveredUntil)->pluck('id')->all();
                GameMessage::query()->whereKey($ids)->update(['is_delivered' => true]);
                $deliveredIds = [...$deliveredIds, ...$ids];
            }
        }

        if ($readIds !== [] || $deliveredIds !== []) {
            Realtime::send(new GameMessagesReceipts($game->id, $readIds, $deliveredIds));
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
