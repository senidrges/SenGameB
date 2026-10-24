<?php

namespace App\Enums;

use App\Models\GameMessage;
use App\Models\LoveGame;
use App\Models\Message;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;

/**
 * What can be reported. The values are also the morph aliases stored in
 * `reports.reportable_type`.
 */
enum ReportableType: string
{
    case User = 'user';
    case Message = 'message';
    case GameMessage = 'game_message';
    case Game = 'love_game';

    /**
     * @return class-string<Model>
     */
    public function modelClass(): string
    {
        return match ($this) {
            self::User => User::class,
            self::Message => Message::class,
            self::GameMessage => GameMessage::class,
            self::Game => LoveGame::class,
        };
    }
}
