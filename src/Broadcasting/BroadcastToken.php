<?php

declare(strict_types=1);

namespace Plugs\Broadcasting;

/**
 * BroadcastToken
 *
 * HMAC-based token utility for authenticating private and presence
 * channel subscriptions. Tokens are signed with the application key,
 * include the channel name and user ID, and have a configurable TTL.
 */
class BroadcastToken
{
    /**
     * Generate a signed authentication token for a channel subscription.
     *
     * @param string $channel  The full channel name (e.g. 'private-user.42')
     * @param int|string $userId The authenticated user's identifier
     * @param int $ttl Token time-to-live in seconds (default: 1 hour)
     * @return string The signed token (base64url-encoded)
     */
    public static function sign(string $channel, int|string $userId, int $ttl = 3600): string
    {
        $expires = time() + $ttl;

        $payload = json_encode([
            'channel' => $channel,
            'user'    => $userId,
            'exp'     => $expires,
        ]);

        $signature = hash_hmac('sha256', $payload, self::getSecret());

        return self::base64UrlEncode($payload . '.' . $signature);
    }

    /**
     * Verify a token and return the decoded payload if valid.
     *
     * Returns the user ID on success, or null if the token is
     * invalid, expired, or doesn't match the requested channel.
     *
     * @param string $token   The signed token
     * @param string $channel The channel being requested
     * @return array|null ['user_id' => int|string] on success, null on failure
     */
    public static function verify(string $token, string $channel): ?array
    {
        try {
            $decoded = self::base64UrlDecode($token);

            $lastDot = strrpos($decoded, '.');
            if ($lastDot === false) {
                return null;
            }

            $payload   = substr($decoded, 0, $lastDot);
            $signature = substr($decoded, $lastDot + 1);

            // Verify HMAC signature
            $expected = hash_hmac('sha256', $payload, self::getSecret());
            if (!hash_equals($expected, $signature)) {
                return null;
            }

            $data = json_decode($payload, true);
            if (!$data) {
                return null;
            }

            // Check expiration
            if (isset($data['exp']) && $data['exp'] < time()) {
                return null;
            }

            // Check channel matches
            if (($data['channel'] ?? '') !== $channel) {
                return null;
            }

            return [
                'user_id' => $data['user'] ?? null,
            ];

        } catch (\Throwable $e) {
            return null;
        }
    }

    /**
     * Get the HMAC signing secret from the application key.
     */
    private static function getSecret(): string
    {
        $key = env('APP_KEY', '');

        if (empty($key)) {
            throw new \RuntimeException(
                'APP_KEY is not set. Broadcasting tokens require an application key for HMAC signing.'
            );
        }

        return $key;
    }

    /**
     * Base64 URL-safe encoding (no padding, URL-safe characters).
     */
    private static function base64UrlEncode(string $data): string
    {
        return rtrim(strtr(base64_encode($data), '+/', '-_'), '=');
    }

    /**
     * Base64 URL-safe decoding.
     */
    private static function base64UrlDecode(string $data): string
    {
        return base64_decode(strtr($data, '-_', '+/'), true) ?: '';
    }
}
