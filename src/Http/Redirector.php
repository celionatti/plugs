<?php

declare(strict_types=1);

namespace Plugs\Http;

/**
 * Redirector
 * 
 * Provides a factory for creating RedirectResponse objects.
 */
class Redirector
{
    /**
     * Create a new redirect response to the given path.
     *
     * @param  string  $path
     * @param  int  $status
     * @param  array  $headers
     * @param  bool|null  $secure
     * @return \Plugs\Http\RedirectResponse
     */
    public function to(string $path, int $status = 302, array $headers = [], ?bool $secure = null): RedirectResponse
    {
        return new RedirectResponse($path, $status);
    }

    /**
     * Create a new redirect response to a named route.
     *
     * @param  string  $route
     * @param  array  $parameters
     * @param  int  $status
     * @param  array  $headers
     * @return \Plugs\Http\RedirectResponse
     */
    public function route(string $route, array $parameters = [], int $status = 302, array $headers = []): RedirectResponse
    {
        return new RedirectResponse(route($route, $parameters), $status);
    }

    /**
     * Create a new redirect response to the previous location.
     *
     * @param  int  $status
     * @param  array  $headers
     * @param  string  $fallback
     * @return \Plugs\Http\RedirectResponse
     */
    public function back(int $status = 302, array $headers = [], string $fallback = '/'): RedirectResponse
    {
        return RedirectResponse::fromGlobal($fallback, $status);
    }

    /**
     * Create a new redirect response to the previously intended location.
     *
     * @param  string  $default
     * @param  int  $status
     * @param  array  $headers
     * @param  bool|null  $secure
     * @return \Plugs\Http\RedirectResponse
     */
    public function intended(string $default = '/', int $status = 302, array $headers = [], ?bool $secure = null): RedirectResponse
    {
        return RedirectResponse::intended($default, $status);
    }
}
