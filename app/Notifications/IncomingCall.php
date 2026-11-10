<?php

namespace App\Notifications;

use App\Enums\CallType;
use App\Models\Call;
use App\Models\User;
use App\Notifications\Channels\ExpoPushChannel;
use App\Notifications\Messages\ExpoMessage;
use Illuminate\Support\Facades\URL;

/**
 * Makes the callee's phone ring when the app is closed.
 */
class IncomingCall extends PushNotification
{
    /**
     * Category registered by the app for the "Répondre" / "Refuser" actions.
     */
    public const CATEGORY = 'incoming_call';

    /**
     * Android channel created by the app, whose sound is a ringtone lasting
     * about the ringing time (the older "calls" channel only beeps once).
     */
    public const CHANNEL = 'incoming_calls';

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
            // "Refuser" on the lock screen call screen, answered natively
            'declineUrl' => URL::temporarySignedRoute(
                'calls.notification-decline',
                now()->addSeconds(Call::RING_SECONDS + 60),
                // Group call: the link declines for this member only
                ['call' => $this->call->id, ...($this->call->is_group ? ['user' => $notifiable->id] : [])],
            ),
        ];
        $body = $this->call->type === CallType::Video ? 'Appel vidéo entrant' : 'Appel audio entrant';

        if ($this->call->is_group) {
            $data['isGroup'] = '1';
            $body = $this->call->type === CallType::Video ? 'Appel vidéo de groupe entrant' : 'Appel audio de groupe entrant';
        }

        // Locked conversation: the caller stays hidden
        if ($this->isLockedFor($notifiable)) {
            return new ExpoMessage(
                title: 'LoveGames',
                body: $body,
                data: $data,
                channelId: self::CHANNEL,
                categoryId: self::CATEGORY,
                priority: 'high',
                ttl: Call::RING_SECONDS,
            );
        }

        $caller = $this->call->caller;

        // Group call: the group's name and photo on the call screen
        if ($this->call->is_group) {
            $group = $this->call->conversation;

            return new ExpoMessage(
                title: (string) $group->name,
                body: "{$caller->username} · {$body}",
                data: [
                    ...$data,
                    'callerUsername' => (string) $group->name,
                    'callerAvatarUrl' => $group->avatar_thumb_url ?? '',
                ],
                channelId: self::CHANNEL,
                categoryId: self::CATEGORY,
                priority: 'high',
                ttl: Call::RING_SECONDS,
            );
        }

        return new ExpoMessage(
            title: $caller->username,
            body: $body,
            data: [
                ...$data,
                'callerUsername' => $caller->username,
                'callerAvatarUrl' => $caller->avatar_thumb_url ?? '',
            ],
            channelId: self::CHANNEL,
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
