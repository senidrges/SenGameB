<?php

namespace App\Policies;

use App\Enums\GameStatus;
use App\Models\LoveGame;
use App\Models\User;

class LoveGamePolicy
{
    /**
     * Participants (organizer included) see the game and use its chat.
     */
    public function view(User $user, LoveGame $game): bool
    {
        return $game->hasParticipant($user);
    }

    /**
     * Only the organizer edits items, activates, resets or deletes the game.
     */
    public function update(User $user, LoveGame $game): bool
    {
        return $game->isOrganizedBy($user);
    }

    public function delete(User $user, LoveGame $game): bool
    {
        return $game->isOrganizedBy($user);
    }

    /**
     * Players pick a number once the organizer activated the game.
     */
    public function choose(User $user, LoveGame $game): bool
    {
        return $game->status === GameStatus::Active && $game->hasPlayer($user);
    }
}
