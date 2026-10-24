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
        $sender = $this->message->sender;

        return new ExpoMessage(
            title: $sender->username,
            body: $this->message->message_type->previewLabel($this->message->content),
            data: [
                'type' => 'new_message',
                'conversationId' => $this->message->conversation_id,
                'senderUsername' => $sender->username,
                'senderAvatarUrl' => $sender->avatar_url ?? '',
            ],
            channelId: 'messages',
            categoryId: self::CATEGORY,
        );
    }
}
