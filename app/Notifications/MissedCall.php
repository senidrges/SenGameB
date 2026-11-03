<?php

namespace App\Notifications;

use App\Enums\CallType;
use App\Models\Call;
use App\Models\User;
use App\Notifications\Messages\ExpoMessage;

/**
 * The caller hung up before the callee answered.
 */
class MissedCall extends PushNotification
{
    public function __construct(public Call $call) {}

    public function toExpo(User $notifiable): ExpoMessage
    {
        $kind = $this->call->type === CallType::Video ? 'Appel vidéo manqué' : 'Appel audio manqué';
        $isLocked = $this->call->conversation->participants()
            ->whereKey($notifiable->id)
            ->wherePivotNotNull('locked_at')
            ->exists();

        return new ExpoMessage(
            title: $isLocked ? 'LoveGames' : $this->call->caller->username,
            body: $kind,
            data: [
                'type' => 'missed_call',
                'callId' => $this->call->id,
                'conversationId' => $this->call->conversation_id,
            ],
            channelId: 'messages',
        );
    }
}
