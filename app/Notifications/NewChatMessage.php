<?php

namespace App\Notifications;

use App\Enums\MessageType;
use App\Models\Message;
use App\Models\User;
use App\Notifications\Messages\ExpoMessage;
use App\Support\GroupEventText;

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
        $conversation = $this->message->conversation;

        // Like WhatsApp: the group as title, "Alice : message" as body
        if ($conversation->isGroup()) {
            $body = $this->message->message_type === MessageType::System
                ? GroupEventText::describe($this->message->payload ?? [], $notifiable)
                : "{$sender->username} : {$this->preview()}";

            return new ExpoMessage(
                title: (string) $conversation->name,
                body: $body,
                data: [
                    'type' => 'new_message',
                    'conversationId' => $this->message->conversation_id,
                    'messageId' => $this->message->id,
                    // The chat opens with the group's name and photo
                    'senderUsername' => (string) $conversation->name,
                    'senderAvatarUrl' => $conversation->avatar_url ?? '',
                    'isGroup' => '1',
                ],
                channelId: 'messages',
                categoryId: self::CATEGORY,
            );
        }

        return new ExpoMessage(
            title: $sender->username,
            body: $this->preview(),
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

    private function preview(): string
    {
        return $this->message->message_type->previewLabel($this->message->content, $this->message->payload, $this->message->file_name);
    }

    private function isLockedFor(User $notifiable): bool
    {
        return $this->message->conversation->participants()
            ->whereKey($notifiable->id)
            ->wherePivotNotNull('locked_at')
            ->exists();
    }
}
