<?php

declare(strict_types=1);

namespace Plugs\Security;

use Plugs\Facades\Cache;

class RateLimiter
{
    /**
     * Determine if the given key has too many attempts.
     *
     * @param  string  $key
     * @param  int  $maxAttempts
     * @return bool
     */
    public function tooManyAttempts(string $key, int $maxAttempts): bool
    {
        if ($this->attempts($key) >= $maxAttempts) {
            if (Cache::has($key . ':timer')) {
                return true;
            }

            $this->resetAttempts($key);
        }

        return false;
    }

    /**
     * Increment the counter for a given key for a given decay time.
     *
     * @param  string  $key
     * @param  int  $decaySeconds
     * @return int
     */
    public function hit(string $key, int $decaySeconds = 60): int
    {
        $attempts = $this->attempts($key) + 1;
        $resetAt = time() + $decaySeconds;

        Cache::set($key . ':timer', true, $decaySeconds);
        Cache::set($key, $attempts, $decaySeconds);
        Cache::set($key . ':reset_at', $resetAt, $decaySeconds);

        return $attempts;
    }

    /**
     * Get the number of attempts for the given key.
     *
     * @param  string  $key
     * @return int
     */
    public function attempts(string $key): int
    {
        return (int) Cache::get($key, 0);
    }

    /**
     * Reset the number of attempts for the given key.
     *
     * @param  string  $key
     * @return bool
     */
    public function resetAttempts(string $key): bool
    {
        Cache::delete($key . ':timer');
        return Cache::delete($key);
    }

    /**
     * Get the number of seconds until the "key" is accessible again.
     *
     * @param  string  $key
     * @return int
     */
    public function availableIn(string $key): int
    {
        // Plugs Cache driver might not support TTL retrieval directly if it's PSR-16 based.
        // For now, we'll store the expiration timestamp separately if needed, 
        // but let's assume standard behavior or implement a simple check.
        // Since we don't have a direct TTL getter in the Cache facade, we'll store it.

        $resetAt = Cache::get($key . ':reset_at');

        if (!$resetAt) {
            return 0;
        }

        return (int) max(0, $resetAt - time());
    }

    /**
     * Clear the attempts and timer for a given key.
     *
     * @param  string  $key
     * @return void
     */
    public function clear(string $key): void
    {
        $this->resetAttempts($key);
        Cache::delete($key . ':reset_at');
    }
}
