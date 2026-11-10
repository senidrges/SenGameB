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

        if ($this->call->is_group) {
            $kind = $this->call->type === CallType::Video ? 'Appel vidéo de groupe manqué' : 'Appel audio de groupe manqué';
        }

        return new ExpoMessage(
            title: $isLocked ? 'LoveGames' : ($this->call->is_group ? (string) $this->call->conversation->name : $this->call->caller->username),
            body: $this->call->is_group && ! $isLocked ? "{$this->call->caller->username} · {$kind}" : $kind,
            data: [
                'type' => 'missed_call',
                'callId' => $this->call->id,
                'conversationId' => $this->call->conversation_id,
            ],
            channelId: 'messages',
        );
    }
}
