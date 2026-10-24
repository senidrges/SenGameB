<?php

namespace App\Policies;

use App\Models\GameMessage;
use App\Models\User;

class GameMessagePolicy
{
    /**
     * Participants of the game can react to its messages.
     */
    public function view(User $user, GameMessage $message): bool
    {
        return $message->game->hasParticipant($user);
    }

    public function update(User $user, GameMessage $message): bool
    {
        return $message->sender_id === $user->id && ! $message->is_deleted;
    }

    public function delete(User $user, GameMessage $message): bool
    {
        return $message->sender_id === $user->id;
    }
}
