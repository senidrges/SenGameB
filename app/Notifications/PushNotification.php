<?php

namespace App\Notifications;

use App\Models\User;
use App\Notifications\Channels\ExpoPushChannel;
use App\Notifications\Messages\ExpoMessage;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueueAfterCommit;
use Illuminate\Notifications\Notification;

/**
 * Base class of the app's push notifications: queued, sent after the
 * database transaction commits, only to users that have a push token.
 */
abstract class PushNotification extends Notification implements ShouldQueueAfterCommit
{
    use Queueable;

    /**
     * Get the notification's delivery channels.
     *
     * @return list<class-string>
     */
    public function via(User $notifiable): array
    {
        return $notifiable->push_token === null ? [] : [ExpoPushChannel::class];
    }

    abstract public function toExpo(User $notifiable): ExpoMessage;
}
