<?php

namespace App\Notifications;

use App\Enums\CallType;
use App\Models\Call;
use App\Models\User;
use App\Notifications\Channels\ExpoPushChannel;
use App\Notifications\Messages\ExpoMessage;

/**
 * Makes the callee's phone ring when the app is closed.
 */
class IncomingCall extends PushNotification
{
    /**
     * Category registered by the app for the "Répondre" / "Refuser" actions.
     */
    public const CATEGORY = 'incoming_call';

    public function __construct(public Call $call) {}

    /**
     * A call cannot wait for the end of the request: sent right away.
     *
     * @return array<class-string, string>
     */
    public function viaConnections(): array
    {
        return [ExpoPushChannel::class => 'sync'];
    }

    public function toExpo(User $notifiable): ExpoMessage
    {
        $data = [
            'type' => 'incoming_call',
            'callId' => $this->call->id,
            'callType' => $this->call->type->value,
            'conversationId' => $this->call->conversation_id,
        ];
        $body = $this->call->type === CallType::Video ? 'Appel vidéo entrant' : 'Appel audio entrant';

        // Locked conversation: the caller stays hidden
        if ($this->isLockedFor($notifiable)) {
            return new ExpoMessage(
                title: 'LoveGames',
                body: $body,
                data: $data,
                channelId: 'calls',
                categoryId: self::CATEGORY,
                priority: 'high',
                ttl: Call::RING_SECONDS,
            );
        }

        $caller = $this->call->caller;

        return new ExpoMessage(
            title: $caller->username,
            body: $body,
            data: [
                ...$data,
                'callerUsername' => $caller->username,
                'callerAvatarUrl' => $caller->avatar_thumb_url ?? '',
            ],
            channelId: 'calls',
            categoryId: self::CATEGORY,
            priority: 'high',
            ttl: Call::RING_SECONDS,
        );
    }

    private function isLockedFor(User $notifiable): bool
    {
        return $this->call->conversation->participants()
            ->whereKey($notifiable->id)
            ->wherePivotNotNull('locked_at')
            ->exists();
    }
}
