<?php

namespace App\Enums;

enum CallStatus: string
{
    // The callee's phone rings
    case Ringing = 'ringing';
    // Both are talking
    case Ongoing = 'ongoing';
    // Hung up after talking
    case Ended = 'ended';
    // The callee refused it
    case Declined = 'declined';
    // Nobody answered, or the caller gave up first
    case Missed = 'missed';

    public function isFinished(): bool
    {
        return in_array($this, [self::Ended, self::Declined, self::Missed], true);
    }
}
