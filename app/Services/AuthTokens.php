<?php

namespace App\Services;

use App\Models\User;
use Illuminate\Support\Str;
use Laravel\Sanctum\PersonalAccessToken;

/**
 * Issues the short lived access token and long lived refresh token pair of a
 * device session. Both tokens share the session id in their name so they can
 * be revoked together.
 */
class AuthTokens
{
    public const ACCESS_ABILITY = 'access';

    public const REFRESH_ABILITY = 'refresh';

    /**
     * @return array{access_token: string, refresh_token: string, expires_at: string, token_type: string}
     */
    public function issue(User $user): array
    {
        $sessionId = (string) Str::uuid();
        $accessExpiresAt = now()->addMinutes((int) config('sanctum.access_token_ttl'));

        $access = $user->createToken("{$sessionId}:access", [self::ACCESS_ABILITY], $accessExpiresAt);
        $refresh = $user->createToken(
            "{$sessionId}:refresh",
            [self::REFRESH_ABILITY],
            now()->addMinutes((int) config('sanctum.refresh_token_ttl')),
        );

        return [
            'access_token' => $access->plainTextToken,
            'refresh_token' => $refresh->plainTextToken,
            'expires_at' => $accessExpiresAt->toIso8601String(),
            'token_type' => 'Bearer',
        ];
    }

    /**
     * Revoke the session of the given refresh token and start a new one.
     *
     * @return array{access_token: string, refresh_token: string, expires_at: string, token_type: string}
     */
    public function rotate(PersonalAccessToken $refreshToken): array
    {
        /** @var User $user */
        $user = $refreshToken->tokenable;

        $this->revokeSession($refreshToken);

        return $this->issue($user);
    }

    /**
     * Delete both tokens of the session the given token belongs to.
     */
    public function revokeSession(PersonalAccessToken $token): void
    {
        $sessionId = Str::before($token->name, ':');

        PersonalAccessToken::query()
            ->where('tokenable_type', $token->tokenable_type)
            ->where('tokenable_id', $token->tokenable_id)
            ->whereIn('name', ["{$sessionId}:access", "{$sessionId}:refresh"])
            ->delete();
    }
}
