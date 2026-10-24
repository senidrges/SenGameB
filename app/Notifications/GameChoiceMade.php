<?php

namespace App\Notifications;

use App\Models\LoveGame;
use App\Models\User;
use App\Notifications\Messages\ExpoMessage;

class GameChoiceMade extends PushNotification
{
    public function __construct(public LoveGame $game, public User $player) {}

    public function toExpo(User $notifiable): ExpoMessage
    {
        return new ExpoMessage(
            title: '🎲 Choix effectué !',
            body: "{$this->player->username} a fait son choix dans \"{$this->game->title}\" !",
            data: ['type' => 'game_choice_made', 'gameId' => $this->game->id],
            channelId: 'games',
        );
    }
}
