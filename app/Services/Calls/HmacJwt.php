<?php

namespace App\Services\Calls;

/**
 * JSON Web Tokens signed with HS256, the format LiveKit uses for access
 * tokens and to sign its webhooks.
 */
class HmacJwt
{
    /**
     * @param  array<string, mixed>  $claims
     */
    public static function encode(array $claims, string $secret): string
    {
        $unsigned = self::base64Url(json_encode(['alg' => 'HS256', 'typ' => 'JWT']))
            .'.'.self::base64Url(json_encode($claims));

        return $unsigned.'.'.self::base64Url(hash_hmac('sha256', $unsigned, $secret, true));
    }

    /**
     * The claims of a token signed with this secret and valid now, or null.
     *
     * @return array<string, mixed>|null
     */
    public static function decode(string $token, string $secret): ?array
    {
        $parts = explode('.', $token);

        if (count($parts) !== 3) {
            return null;
        }

        [$header, $payload, $signature] = $parts;
        $expected = self::base64Url(hash_hmac('sha256', "{$header}.{$payload}", $secret, true));

        if (! hash_equals($expected, $signature)) {
            return null;
        }

        $claims = json_decode(self::base64UrlDecode($payload), true);
        $now = now()->timestamp;

        if (! is_array($claims)
            || (isset($claims['exp']) && $claims['exp'] < $now)
            || (isset($claims['nbf']) && $claims['nbf'] > $now + 60)) {
            return null;
        }

        return $claims;
    }

    private static function base64Url(string $data): string
    {
        return rtrim(strtr(base64_encode($data), '+/', '-_'), '=');
    }

    private static function base64UrlDecode(string $data): string
    {
        return (string) base64_decode(strtr($data, '-_', '+/'));
    }
}
