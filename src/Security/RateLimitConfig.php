<?php

declare(strict_types=1);

namespace Plugs\Security;

/*
|--------------------------------------------------------------------------
| RateLimitConfig Class
|--------------------------------------------------------------------------
|
| A fluent value object for configuring rate limits.
| Created via RateLimiter::perMinute(), perHour(), perDay().
|
| Usage:
|   RateLimiter::for('login', function ($request) {
|       return RateLimiter::perMinute(5)->by('login:' . $request->ip);
|   });
*/

class RateLimitConfig
{
    public int $maxAttempts;
    public int $decaySeconds;
    public string $key = '';

    public function __construct(int $maxAttempts, int $decaySeconds)
    {
        $this->maxAttempts = $maxAttempts;
        $this->decaySeconds = $decaySeconds;
    }

    /**
     * Set the key to throttle by.
     *
     * @param  string $key Unique identifier (e.g. email, ip, or a combination)
     * @return $this
     */
    public function by(string $key): static
    {
        $this->key = $key;

        return $this;
    }

    /**
     * Return multiple configs for multi-key throttling.
     * Each config is checked independently.
     *
     * Example:
     *   return RateLimiter::perMinute(5)
     *       ->by('login_email:' . $email)
     *       ->and(RateLimiter::perMinute(15)->by('login_ip:' . $request->ip));
     *
     * @param  RateLimitConfig $other
     * @return array<RateLimitConfig>
     */
    public function and(RateLimitConfig $other): array
    {
        return [$this, $other];
    }
}
