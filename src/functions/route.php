<?php

declare(strict_types=1);

/*
|--------------------------------------------------------------------------
| Routing Helper Functions
|--------------------------------------------------------------------------
|
| These helper functions provide convenient shortcuts for common routing
| operations like redirects, URL generation, and route checking.
*/

use Plugs\Router\Router;
use Plugs\Http\ResponseFactory;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Message\ResponseInterface;

if (!function_exists('route')) {
    function route(string $name, array $parameters = [], bool $absolute = false): string
    {
        $router = app(Router::class);
        return $router->route($name, $parameters, $absolute);
    }
}

if (!function_exists('setCurrentRequest')) {
    function setCurrentRequest(ServerRequestInterface $request): void
    {
        $GLOBALS['__current_request'] = $request;
    }
}

if (!function_exists('currentRoute')) {
    function currentRoute(?ServerRequestInterface $request = null): ?\Plugs\Router\Route
    {
        $request = $request ?? request();
        return $request?->getAttribute('_route');
    }
}

if (!function_exists('currentRouteName')) {
    function currentRouteName(?ServerRequestInterface $request = null): ?string
    {
        $route = currentRoute($request ?? request());
        return $route?->getName();
    }
}

if (!function_exists('currentPath')) {
    function currentPath(?ServerRequestInterface $request = null): string
    {
        $request = $request ?? request();
        if ($request === null) {
            return parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?: '/';
        }
        return $request->getUri()->getPath() ?: '/';
    }
}

if (!function_exists('currentUrl')) {
    function currentUrl(?ServerRequestInterface $request = null): string
    {
        $request = $request ?? request();

        if ($request === null) {
            // Fallback to server variables
            $scheme = isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off' ? 'https' : 'http';
            $host = $_SERVER['HTTP_HOST'] ?? 'localhost';
            $path = $_SERVER['REQUEST_URI'] ?? '/';
            return $scheme . '://' . $host . $path;
        }

        return (string) $request->getUri();
    }
}

if (!function_exists('hasRoute')) {
    function hasRoute(string $name): bool
    {
        try {
            $router = app(Router::class);
            return $router->hasRoute($name);
        } catch (\Exception $e) {
            return false;
        }
    }
}

if (!function_exists('routeParams')) {
    function routeParams(?string $key = null, $default = null, ?ServerRequestInterface $request = null)
    {
        $request = $request ?? request();

        if ($request === null) {
            return $key === null ? [] : $default;
        }

        // Get route parameters from request attributes
        $params = [];
        $attributes = $request->getAttributes();

        foreach ($attributes as $attrKey => $value) {
            // Skip framework attributes
            if (in_array($attrKey, ['_route', '_router', '_middleware'])) {
                continue;
            }
            $params[$attrKey] = $value;
        }

        if ($key === null) {
            return $params;
        }

        return $params[$key] ?? $default;
    }
}

if (!function_exists('isMethod')) {
    function isMethod($method, ?ServerRequestInterface $request = null): bool
    {
        $request = $request ?? request();

        if ($request === null) {
            $currentMethod = $_SERVER['REQUEST_METHOD'] ?? 'GET';
        } else {
            $currentMethod = $request->getMethod();
        }

        $currentMethod = strtoupper($currentMethod);
        $methods = is_array($method)
            ? array_map('strtoupper', $method)
            : [strtoupper($method)];

        return in_array($currentMethod, $methods, true);
    }
}

if (!function_exists('redirect')) {
    function redirect(string $url, int $status = 302): void
    {
        // Simple PHP header redirect
        header("Location: $url", true, $status);
        exit; // Important: stop script execution
    }
}

if (!function_exists('redirectTo')) {
    function redirectTo(string $routeName, array $parameters = [], int $status = 302): void
    {
        $url = route($routeName, $parameters);
        header("Location: $url", true, $status);
        exit;
    }
}

if (!function_exists('redirectBack')) {
    function redirectBack(string $fallback = '/', int $status = 302): void
    {
        $referer = $_SERVER['HTTP_REFERER'] ?? $fallback;
        header("Location: $referer", true, $status);
        exit;
    }
}

if (!function_exists('previousUrl')) {
    function previousUrl(string $default = '/'): string
    {
        return $_SERVER['HTTP_REFERER'] ?? $default;
    }
}

if (!function_exists('back')) {
    function back(): string
    {
        return previousUrl();
    }
}