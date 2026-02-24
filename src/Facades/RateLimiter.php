<?php

declare(strict_types=1);

namespace Plugs\Facades;

use Plugs\Facade;

/**
 * @method static bool tooManyAttempts(string $key, int $maxAttempts)
 * @method static int hit(string $key, int $decaySeconds = 60)
 * @method static int attempts(string $key)
 * @method static bool resetAttempts(string $key)
 * @method static int availableIn(string $key)
 * @method static void clear(string $key)
 *
 * @see \Plugs\Security\RateLimiter
 */
class RateLimiter extends Facade
{
    /**
     * Get the registered name of the component.
     *
     * @return string
     */
    protected static function getFacadeAccessor(): string
    {
        return 'ratelimiter';
    }
}
