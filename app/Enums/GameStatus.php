<?php

namespace App\Enums;

enum GameStatus: string
{
    case Waiting = 'waiting';
    case Active = 'active';
    case Completed = 'completed';
}
