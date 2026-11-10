<?php

namespace App\Enums;

/**
 * Where a member stands in a group call.
 */
enum CallParticipantStatus: string
{
    // Their phone rings
    case Ringing = 'ringing';
    // In the call
    case Joined = 'joined';
    // Refused to join
    case Declined = 'declined';
    // Joined, then hung up
    case Left = 'left';
    // Never answered
    case Missed = 'missed';
}
