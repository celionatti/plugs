<?php

declare(strict_types=1);

namespace Plugs\Security\Identity;

/**
 * NonceService
 *
 * Generates and validates time-limited nonces for challenge-response
 * authentication. Nonces are one-time-use and expire after a configurable TTL.
 *
 * Uses HMAC to bind nonces to a specific identifier (e.g. email) and timestamp,
 * ensuring they cannot be reused across different users or time windows.
 */
class NonceService
{
    /**
     * Secret key for HMAC-based nonce generation.
     */
    protected string $secret;

    /**
     * Time-to-live in seconds.
     */
    protected int $ttl;

    public function __construct(?string $secret = null, int $ttl = 300)
    {
        $this->secret = $secret ?? config('app.key', 'default-nonce-secret');
        $this->ttl = $ttl;
    }

    /**
     * Generate a time-bound nonce for the given identifier.
     *
     * The nonce encodes:
     * - Random entropy (16 bytes)
     * - Timestamp
     * - HMAC binding to the identifier
     *
     * Format: base64(random_bytes) . '.' . timestamp . '.' . hmac
     *
     * @param string $identifier The user identifier (e.g. email)
     * @return string
     */
    public function generate(string $identifier): string
    {
        $random = bin2hex(random_bytes(16));
        $timestamp = time();

        $payload = $random . '.' . $timestamp;

        $hmac = hash_hmac('sha256', $identifier . '|' . $payload, $this->secret);

        return $payload . '.' . $hmac;
    }

    /**
     * Validate a nonce for the given identifier.
     *
     * Checks:
     * 1. Nonce has the correct format (3 parts)
     * 2. HMAC matches (bound to the identifier)
     * 3. Nonce has not expired (within TTL)
     *
     * @param string $identifier The user identifier (e.g. email)
     * @param string $nonce      The nonce to validate
     * @return bool
     */
    public function validate(string $identifier, string $nonce): bool
    {
        $parts = explode('.', $nonce);

        if (count($parts) !== 3) {
            return false;
        }

        [$random, $timestamp, $hmac] = $parts;

        // Validate timestamp
        if (!is_numeric($timestamp)) {
            return false;
        }

        $timestamp = (int) $timestamp;

        // Check expiration
        if ((time() - $timestamp) > $this->ttl) {
            return false;
        }

        // Verify HMAC
        $expectedPayload = $random . '.' . $timestamp;
        $expectedHmac = hash_hmac('sha256', $identifier . '|' . $expectedPayload, $this->secret);

        return hash_equals($expectedHmac, $hmac);
    }

    /**
     * Get the TTL in seconds.
     */
    public function getTtl(): int
    {
        return $this->ttl;
    }
}
