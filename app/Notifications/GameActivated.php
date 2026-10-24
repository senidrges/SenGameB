<?php

namespace App\Notifications;

use App\Models\LoveGame;
use App\Models\User;
use App\Notifications\Messages\ExpoMessage;

class GameActivated extends PushNotification
{
    public function __construct(public LoveGame $game) {}

    public function toExpo(User $notifiable): ExpoMessage
    {
        return new ExpoMessage(
            title: '🎮 Jeu activé !',
            body: "{$this->game->organizer->username} a activé \"{$this->game->title}\". C'est à toi de jouer !",
            data: ['type' => 'game_activated', 'gameId' => $this->game->id],
            channelId: 'games',
        );
    }
}
