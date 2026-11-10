<?php

namespace App\Policies;

use App\Enums\MessageType;
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
     * The sender deletes a message for everyone. Like WhatsApp, a group
     * admin also deletes the messages of the others (group notices aside).
     */
    public function delete(User $user, Message $message): bool
    {
        if ($message->message_type === MessageType::System) {
            return false;
        }

        return $message->sender_id === $user->id || $message->conversation->isAdmin($user);
    }

    /**
     * "Infos du message": who received and read it, for its sender.
     */
    public function viewInfo(User $user, Message $message): bool
    {
        return $message->sender_id === $user->id && $message->conversation->hasParticipant($user);
    }
}
