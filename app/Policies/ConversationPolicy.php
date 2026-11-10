<?php

namespace App\Policies;

use App\Models\Conversation;
use App\Models\User;
use Illuminate\Auth\Access\Response;

class ConversationPolicy
{
    /**
     * Only participants can read a conversation. The history stays readable
     * after a block, and after leaving a group (up to the departure).
     */
    public function view(User $user, Conversation $conversation): bool
    {
        return $conversation->hasParticipant($user);
    }

    /**
     * Participants write (messages, files, reactions) unless one of them
     * blocked the other. In a group, like WhatsApp: current members only,
     * and only the admins when the group says so. Blocks do not apply in
     * groups.
     */
    public function sendMessage(User $user, Conversation $conversation): Response
    {
        if ($conversation->isGroup()) {
            if (! $conversation->hasMember($user)) {
                return Response::deny("Vous ne pouvez pas envoyer de messages à ce groupe car vous n'en faites plus partie.");
            }

            return $conversation->members_can_send || $conversation->isAdmin($user)
                ? Response::allow()
                : Response::deny('Seuls les admins peuvent envoyer des messages.');
        }

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

    /**
     * Name, photo and description of a group.
     */
    public function editGroupInfo(User $user, Conversation $conversation): Response
    {
        return $conversation->canEditInfo($user)
            ? Response::allow()
            : Response::deny('Seuls les admins peuvent modifier les infos du groupe.');
    }

    public function addMembers(User $user, Conversation $conversation): Response
    {
        return $conversation->canAddMembers($user)
            ? Response::allow()
            : Response::deny('Seuls les admins peuvent ajouter des membres.');
    }

    /**
     * Remove members, name admins, change the permissions, manage the
     * invite link and the join requests.
     */
    public function administer(User $user, Conversation $conversation): Response
    {
        return $conversation->isAdmin($user)
            ? Response::allow()
            : Response::deny('Seuls les admins du groupe peuvent faire cela.');
    }
}
