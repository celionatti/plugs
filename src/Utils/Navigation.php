<?php

declare(strict_types=1);

namespace Plugs\Utils;

use Plugs\Router\Router;
use Plugs\Router\Route;
use Psr\Http\Message\ServerRequestInterface;

/**
 * Plugs Framework Navigation & Active Path Utilities
 */
class Navigation
{
    /**
     * Check if the given path/route is active.
     */
    public static function isActive(string|array $path, bool $exact = false): bool
    {
        $currentPath = static::getCurrentPath();
        $paths = is_array($path) ? $path : [$path];

        foreach ($paths as $p) {
            if (str_contains($p, '.') || static::hasRoute($p)) {
                if (static::routeIs($p))
                    return true;
                continue;
            }

            $p = '/' . trim($p, '/');
            $currentPath = '/' . trim($currentPath, '/');

            if ($exact) {
                if ($p === $currentPath)
                    return true;
            } else {
                if ($p === '/' && $currentPath === '/')
                    return true;
                if ($p !== '/' && str_starts_with($currentPath, $p))
                    return true;
            }
        }

        return false;
    }

    /**
     * Check if the current route name matches a pattern.
     */
    public static function routeIs(string|array $patterns): bool
    {
        $currentName = static::getCurrentRouteName();
        if ($currentName === null)
            return false;

        $patterns = (array) $patterns;
        foreach ($patterns as $pattern) {
            $regex = preg_quote($pattern, '#');
            $regex = str_replace('\*', '.*', $regex);
            if (preg_match('#^' . $regex . '$#', $currentName))
                return true;
        }

        return false;
    }

    /**
     * Get the current path.
     */
    public static function getCurrentPath(?ServerRequestInterface $request = null): string
    {
        $request = $request ?? static::request();
        if ($request === null) {
            return parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?: '/';
        }
        return $request->getUri()->getPath() ?: '/';
    }

    /**
     * Get the current route name.
     */
    public static function getCurrentRouteName(?ServerRequestInterface $request = null): ?string
    {
        $route = static::getCurrentRoute($request);
        return $route?->getName();
    }

    /**
     * Get the current route instance.
     */
    public static function getCurrentRoute(?ServerRequestInterface $request = null): ?Route
    {
        $request = $request ?? static::request();
        return $request?->getAttribute('_route');
    }

    /**
     * Check if a route exists.
     */
    protected static function hasRoute(string $name): bool
    {
        try {
            return app(Router::class)->hasRoute($name);
        } catch (\Exception $e) {
            return false;
        }
    }

    /**
     * Proxy to request helper.
     */
    protected static function request()
    {
        return function_exists('request') ? request() : null;
    }
}
