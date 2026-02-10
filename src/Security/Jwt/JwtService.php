<?php

declare(strict_types=1);

namespace Plugs\Security\Jwt;

use Exception;
use Plugs\Utils\Arr;

class JwtService
{
    /**
     * The algorithm used for signing.
     */
    protected string $algo = 'SHA256';

    /**
     * The secret key used for signing.
     */
    protected string $secret;

    /**
     * Create a new JwtService instance.
     */
    public function __construct()
    {
        $this->secret = config('app.key', 'base64:default_secret_key_which_should_be_changed');
    }

    /**
     * Encode a payload into a JWT token.
     */
    public function encode(array $payload, int $expiry = 3600): string
    {
        $header = ['typ' => 'JWT', 'alg' => 'HS256'];

        // Add standard claims
        $payload['iat'] = time();
        $payload['exp'] = time() + $expiry;
        $payload['iss'] = config('app.url');

        $segments = [];
        $segments[] = $this->urlSafeB64Encode(json_encode($header));
        $segments[] = $this->urlSafeB64Encode(json_encode($payload));
        $signing_input = implode('.', $segments);

        $signature = $this->sign($signing_input);
        $segments[] = $this->urlSafeB64Encode($signature);

        return implode('.', $segments);
    }

    /**
     * Decode and verify a JWT token.
     *
     * @return array|null The payload if valid, null otherwise.
     */
    public function decode(string $token): ?array
    {
        $parts = explode('.', $token);

        if (count($parts) !== 3) {
            return null;
        }

        [$headb64, $bodyb64, $cryptob64] = $parts;

        $header = json_decode($this->urlSafeB64Decode($headb64), true);
        $payload = json_decode($this->urlSafeB64Decode($bodyb64), true);
        $sig = $this->urlSafeB64Decode($cryptob64);

        if (!$header || !$payload) {
            return null;
        }

        // Verify Signature
        if (!$this->verify($headb64 . '.' . $bodyb64, $sig)) {
            return null;
        }

        // Verify Expiration
        if (isset($payload['exp']) && time() >= $payload['exp']) {
            return null;
        }

        return $payload;
    }

    /**
     * Sign the input string.
     */
    protected function sign(string $input): string
    {
        return hash_hmac($this->algo, $input, $this->secret, true);
    }

    /**
     * Verify the signature.
     */
    protected function verify(string $input, string $signature): bool
    {
        $expected = $this->sign($input);

        return hash_equals($expected, $signature);
    }

    /**
     * Encode in URL-safe Base64.
     */
    protected function urlSafeB64Encode(string $input): string
    {
        return str_replace('=', '', strtr(base64_encode($input), '+/', '-_'));
    }

    /**
     * Decode from URL-safe Base64.
     */
    protected function urlSafeB64Decode(string $input): string
    {
        $remainder = strlen($input) % 4;

        if ($remainder) {
            $padlen = 4 - $remainder;
            $input .= str_repeat('=', $padlen);
        }

        return base64_decode(strtr($input, '-_', '+/'));
    }
}
