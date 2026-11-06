<?php

namespace App\Notifications;

use App\Models\Message;
use App\Models\User;
use App\Notifications\Messages\ExpoMessage;

class NewChatMessage extends PushNotification
{
    /**
     * Category registered by the app to offer the "Répondre" quick action.
     */
    public const CATEGORY = 'chat_message';

    public function __construct(public Message $message) {}

    public function toExpo(User $notifiable): ExpoMessage
    {
        // Locked conversation: neither the sender nor the content, and no quick reply
        if ($this->isLockedFor($notifiable)) {
            return new ExpoMessage(
                title: 'LoveGames',
                body: 'Nouveau message',
                data: [
                    'type' => 'new_message',
                    'conversationId' => $this->message->conversation_id,
                    'messageId' => $this->message->id,
                ],
                channelId: 'messages',
            );
        }

        $sender = $this->message->sender;

        return new ExpoMessage(
            title: $sender->username,
            body: $this->message->message_type->previewLabel($this->message->content, $this->message->payload, $this->message->file_name),
            data: [
                'type' => 'new_message',
                'conversationId' => $this->message->conversation_id,
                'messageId' => $this->message->id,
                'senderUsername' => $sender->username,
                'senderAvatarUrl' => $sender->avatar_url ?? '',
            ],
            channelId: 'messages',
            categoryId: self::CATEGORY,
        );
    }

    private function isLockedFor(User $notifiable): bool
    {
        return $this->message->conversation->participants()
            ->whereKey($notifiable->id)
            ->wherePivotNotNull('locked_at')
            ->exists();
    }
}
