<?php

namespace App\Policies;

use App\Models\Message;
use App\Models\User;

class MessagePolicy
{
    /**
     * Participants can react to and forward the messages of their conversations.
     */
    public function view(User $user, Message $message): bool
    {
        return $message->conversation->hasParticipant($user);
    }

    /**
     * Only the sender edits a message, as long as it was not deleted.
     */
    public function update(User $user, Message $message): bool
    {
        return $message->sender_id === $user->id && ! $message->is_deleted;
    }

    /**
     * Only the sender deletes a message for everyone.
     */
    public function delete(User $user, Message $message): bool
    {
        return $message->sender_id === $user->id;
    }
}
