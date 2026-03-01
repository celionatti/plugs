<?php

declare(strict_types=1);

namespace Plugs\Facades;

use Plugs\Facade;

/**
 * @method static void set(string $name, mixed $value, int $minutes = 0, array $options = [])
 * @method static mixed get(string $name, mixed $default = null)
 * @method static bool has(string $name)
 * @method static void forget(string $name, string $path = '/', string|null $domain = null)
 *
 * @see \Plugs\Http\CookieJar
 */
class Cookie extends Facade
{
    /**
     * Get the registered name of the component.
     *
     * @return string
     */
    protected static function getFacadeAccessor(): string
    {
        return 'cookie';
    }
}
