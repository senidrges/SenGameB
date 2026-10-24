<?php

namespace App\Notifications;

use App\Models\LoveGame;
use App\Models\User;
use App\Notifications\Messages\ExpoMessage;

class GameCreated extends PushNotification
{
    public function __construct(public LoveGame $game) {}

    public function toExpo(User $notifiable): ExpoMessage
    {
        return new ExpoMessage(
            title: '💝 Nouveau jeu !',
            body: "{$this->game->organizer->username} vous a créé un jeu : \"{$this->game->title}\"",
            data: ['type' => 'new_game', 'gameId' => $this->game->id],
            channelId: 'games',
        );
    }
}
