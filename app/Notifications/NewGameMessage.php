<?php

namespace App\Notifications;

use App\Models\GameMessage;
use App\Models\User;
use App\Notifications\Messages\ExpoMessage;

class NewGameMessage extends PushNotification
{
    public function __construct(public GameMessage $message) {}

    public function toExpo(User $notifiable): ExpoMessage
    {
        return new ExpoMessage(
            title: "{$this->message->sender->username} - {$this->message->game->title}",
            body: $this->message->content,
            data: ['type' => 'new_game_message', 'gameId' => $this->message->game_id],
            channelId: 'games',
        );
    }
}
