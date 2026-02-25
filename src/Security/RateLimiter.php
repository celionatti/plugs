<?php

declare(strict_types=1);

namespace Plugs\Security;

use Plugs\Facades\Cache;

class RateLimiter
{
    /**
     * Registered named rate limiters.
     * Each is a Closure that receives the request and returns a RateLimitConfig|array.
     *
     * @var array<string, \Closure>
     */
    protected static array $limiters = [];

    /**
     * Register a named rate limiter.
     *
     * Usage in AppServiceProvider::boot():
     *   RateLimiter::for('login', function ($request) {
     *       return RateLimiter::perMinute(5)->by($request->ip);
     *   });
     *
     * @param  string   $name
     * @param  \Closure $callback
     * @return void
     */
    public static function for(string $name, \Closure $callback): void
    {
        static::$limiters[$name] = $callback;
    }

    /**
     * Get a named rate limiter callback.
     *
     * @param  string $name
     * @return \Closure|null
     */
    public static function limiter(string $name): ?\Closure
    {
        return static::$limiters[$name] ?? null;
    }

    /**
     * Create a rate limit config: X attempts per minute.
     *
     * @param  int $maxAttempts
     * @return RateLimitConfig
     */
    public static function perMinute(int $maxAttempts): RateLimitConfig
    {
        return new RateLimitConfig($maxAttempts, 60);
    }

    /**
     * Create a rate limit config: X attempts per hour.
     *
     * @param  int $maxAttempts
     * @return RateLimitConfig
     */
    public static function perHour(int $maxAttempts): RateLimitConfig
    {
        return new RateLimitConfig($maxAttempts, 3600);
    }

    /**
     * Create a rate limit config: X attempts per day.
     *
     * @param  int $maxAttempts
     * @return RateLimitConfig
     */
    public static function perDay(int $maxAttempts): RateLimitConfig
    {
        return new RateLimitConfig($maxAttempts, 86400);
    }

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
