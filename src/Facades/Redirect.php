<?php

declare(strict_types=1);

namespace Plugs\Facades;

use Plugs\Facade;

/**
 * Redirect Facade
 * 
 * @method static \Plugs\Http\RedirectResponse to(string $path, int $status = 302, array $headers = [], bool|null $secure = null)
 * @method static \Plugs\Http\RedirectResponse route(string $route, array $parameters = [], int $status = 302, array $headers = [])
 * @method static \Plugs\Http\RedirectResponse back(int $status = 302, array $headers = [], string $fallback = '/')
 * @method static \Plugs\Http\RedirectResponse intended(string $default = '/', int $status = 302, array $headers = [], bool|null $secure = null)
 *
 * @see \Plugs\Http\Redirector
 */
class Redirect extends Facade
{
    /**
     * Get the registered name of the component.
     *
     * @return string
     */
    protected static function getFacadeAccessor(): string
    {
        return 'redirect';
    }
}
