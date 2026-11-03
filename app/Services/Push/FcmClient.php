<?php

namespace App\Services\Push;

use App\Notifications\Messages\ExpoMessage;
use Illuminate\Http\Client\RequestException;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use RuntimeException;

/**
 * Sends pushes straight to Firebase Cloud Messaging, as data-only messages.
 *
 * Expo's push service sends Android "notification" messages, which Android
 * draws by itself when the app is closed: without the buttons of the
 * notification category. A data-only message is always handed to
 * expo-notifications, which draws it with its buttons, title and channel.
 */
class FcmClient
{
    private const SCOPE = 'https://www.googleapis.com/auth/firebase.messaging';

    /**
     * Whether a Firebase service account is configured.
     */
    public static function isConfigured(): bool
    {
        return self::credentials() !== null;
    }

    /**
     * @throws FcmTokenUnregistered when Firebase no longer knows the token
     * @throws RequestException|RuntimeException on any other failure
     */
    public function send(string $token, ExpoMessage $message): void
    {
        $credentials = self::credentials() ?? throw new RuntimeException('Firebase credentials are not configured.');

        // expo-notifications reads these keys (see its NotificationData class)
        $data = array_filter([
            'title' => $message->title,
            'message' => $message->body,
            'body' => json_encode((object) $message->data),
            'channelId' => $message->channelId,
            'categoryId' => $message->categoryId,
        ], fn (?string $value): bool => $value !== null);

        $response = Http::acceptJson()
            ->timeout(10)
            ->withToken($this->accessToken($credentials))
            ->post("https://fcm.googleapis.com/v1/projects/{$credentials['project_id']}/messages:send", [
                'message' => [
                    'token' => $token,
                    'data' => $data,
                    'android' => array_filter([
                        // A data message only wakes a closed app right away with a high priority
                        'priority' => 'high',
                        'ttl' => $message->ttl === null ? null : "{$message->ttl}s",
                    ]),
                ],
            ]);

        if ($response->status() === 404 || $response->json('error.details.0.errorCode') === 'UNREGISTERED') {
            throw new FcmTokenUnregistered;
        }

        $response->throw();
    }

    /**
     * @param  array{project_id: string, client_email: string, private_key: string, token_uri?: string}  $credentials
     */
    private function accessToken(array $credentials): string
    {
        // Google tokens last an hour
        return Cache::remember('fcm.access_token', now()->addMinutes(50), function () use ($credentials): string {
            $tokenUri = $credentials['token_uri'] ?? 'https://oauth2.googleapis.com/token';
            $now = now()->timestamp;

            $assertion = self::signedJwt([
                'iss' => $credentials['client_email'],
                'scope' => self::SCOPE,
                'aud' => $tokenUri,
                'iat' => $now,
                'exp' => $now + 3600,
            ], $credentials['private_key']);

            return Http::asForm()
                ->timeout(10)
                ->post($tokenUri, [
                    'grant_type' => 'urn:ietf:params:oauth:grant-type:jwt-bearer',
                    'assertion' => $assertion,
                ])
                ->throw()
                ->json('access_token');
        });
    }

    /**
     * @param  array<string, mixed>  $claims
     */
    private static function signedJwt(array $claims, string $privateKey): string
    {
        $unsigned = self::base64Url(json_encode(['alg' => 'RS256', 'typ' => 'JWT']))
            .'.'.self::base64Url(json_encode($claims));

        if (! openssl_sign($unsigned, $signature, $privateKey, OPENSSL_ALGO_SHA256)) {
            throw new RuntimeException('The Firebase private key could not sign the token.');
        }

        return $unsigned.'.'.self::base64Url($signature);
    }

    private static function base64Url(string $data): string
    {
        return rtrim(strtr(base64_encode($data), '+/', '-_'), '=');
    }

    /**
     * The service account JSON key downloaded from the Firebase console.
     *
     * @return array{project_id: string, client_email: string, private_key: string, token_uri?: string}|null
     */
    private static function credentials(): ?array
    {
        $path = config('services.fcm.credentials');

        if (! is_string($path) || $path === '') {
            return null;
        }

        // Relative to the project root unless absolute (also C:\... locally)
        $path = str_starts_with($path, '/') || preg_match('~^[A-Za-z]:~', $path) ? $path : base_path($path);

        if (! is_file($path)) {
            return null;
        }

        $credentials = json_decode((string) file_get_contents($path), true);

        return is_array($credentials) && isset($credentials['project_id'], $credentials['client_email'], $credentials['private_key'])
            ? $credentials
            : null;
    }
}
