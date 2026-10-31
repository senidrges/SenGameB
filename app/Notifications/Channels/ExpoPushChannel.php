<?php

namespace App\Notifications\Channels;

use App\Models\User;
use App\Notifications\PushNotification;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class ExpoPushChannel
{
    /**
     * Send the notification to the user's device.
     *
     * A token Expo reports as no longer registered is removed so it is not
     * used again.
     */
    public function send(User $notifiable, PushNotification $notification): void
    {
        $token = $notifiable->routeNotificationFor('expo', $notification);

        if (! is_string($token) || $token === '') {
            return;
        }

        $message = $notification->toExpo($notifiable);

        $response = Http::acceptJson()
            ->timeout(10)
            ->retry(2, 500, throw: false)
            ->when(config('services.expo.access_token'), fn ($request, string $accessToken) => $request->withToken($accessToken))
            ->post(config('services.expo.push_url'), $message->toPayload($token))
            ->throw();

        if ($response->json('data.status') !== 'error') {
            return;
        }

        if ($response->json('data.details.error') === 'DeviceNotRegistered') {
            $notifiable->forceFill(['push_token' => null])->saveQuietly();

            return;
        }

        // e.g. InvalidCredentials: the FCM key is missing from the Expo project
        Log::warning('Expo refused a push notification', [
            'user_id' => $notifiable->id,
            'error' => $response->json('data.details.error'),
            'message' => $response->json('data.message'),
        ]);
    }
}
