<?php

namespace App\Models\Concerns;

use Illuminate\Support\Facades\DB;

/**
 * Reactions are stored as a JSON map of emoji => list of user ids.
 */
trait HasReactions
{
    /**
     * Add the user's reaction, or remove it when it is already there.
     *
     * The row is locked so two users reacting at the same time never
     * overwrite each other.
     */
    public function toggleReaction(string $emoji, string $userId): void
    {
        DB::transaction(function () use ($emoji, $userId): void {
            $locked = static::query()->whereKey($this->getKey())->lockForUpdate()->firstOrFail();

            $reactions = $locked->reactions ?? [];
            $users = $reactions[$emoji] ?? [];

            $users = in_array($userId, $users, true)
                ? array_values(array_diff($users, [$userId]))
                : [...$users, $userId];

            if ($users === []) {
                unset($reactions[$emoji]);
            } else {
                $reactions[$emoji] = $users;
            }

            $this->forceFill(['reactions' => $reactions === [] ? null : $reactions])->save();
        });
    }
}
