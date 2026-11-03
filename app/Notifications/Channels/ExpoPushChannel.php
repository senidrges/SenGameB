<?php

namespace App\Notifications\Channels;

use App\Models\User;
use App\Notifications\Messages\ExpoMessage;
use App\Notifications\PushNotification;
use App\Services\Push\FcmClient;
use App\Services\Push\FcmTokenUnregistered;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Throwable;

class ExpoPushChannel
{
    public function __construct(private FcmClient $fcm) {}

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

        if ($this->sendThroughFirebase($notifiable, $message)) {
            return;
        }

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

    /**
     * Pushes with buttons (a category) go straight to Firebase on Android so
     * the buttons show even when the app is closed (see FcmClient). Expo
     * remains the fallback when this fails.
     */
    private function sendThroughFirebase(User $notifiable, ExpoMessage $message): bool
    {
        if ($message->categoryId === null || $notifiable->fcm_token === null || ! FcmClient::isConfigured()) {
            return false;
        }

        try {
            $this->fcm->send($notifiable->fcm_token, $message);

            return true;
        } catch (FcmTokenUnregistered) {
            $notifiable->forceFill(['fcm_token' => null])->saveQuietly();
        } catch (Throwable $exception) {
            Log::warning('Firebase refused a push notification, sent through Expo instead', [
                'user_id' => $notifiable->id,
                'error' => $exception->getMessage(),
            ]);
        }

        return false;
    }
}
