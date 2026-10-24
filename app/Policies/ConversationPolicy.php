<?php

namespace App\Policies;

use App\Models\Conversation;
use App\Models\User;
use Illuminate\Auth\Access\Response;

class ConversationPolicy
{
    /**
     * Only participants can read a conversation. The history stays readable
     * after a block.
     */
    public function view(User $user, Conversation $conversation): bool
    {
        return $conversation->hasParticipant($user);
    }

    /**
     * Participants write (messages, files, reactions) unless one of them
     * blocked the other.
     */
    public function sendMessage(User $user, Conversation $conversation): Response
    {
        if (! $conversation->hasParticipant($user)) {
            return Response::deny();
        }

        $isBlocked = $conversation->participants()
            ->whereKeyNot($user->id)
            ->get()
            ->contains(fn (User $participant) => $user->hasBlockWith($participant));

        return $isBlocked
            ? Response::deny('Vous ne pouvez pas écrire dans cette conversation.')
            : Response::allow();
    }
}
