<?php

declare(strict_types=1);

if (defined('PLUGS_ROUTE_LOADED'))
    return;
define('PLUGS_ROUTE_LOADED', true);

use Plugs\Utils\Navigation;
use Plugs\Http\RedirectResponse;
use Plugs\Http\Redirector;
use Plugs\Router\Router;
use Plugs\Router\Route;
use Psr\Http\Message\ServerRequestInterface;

/*
|--------------------------------------------------------------------------
| Routing Helper Functions - Refactored & Restored
|--------------------------------------------------------------------------
|
| Thin wrappers delegates to Plugs\Utils\Navigation and Plugs core classes.
| Restored to ensure full backward compatibility and system dependency satisfaction.
*/

if (!function_exists('route')) {
    /**
     * Generate a URL for a named route
     */
    function route(string $name, array $parameters = [], bool $absolute = true): string
    {
        return app(Router::class)->route($name, $parameters, $absolute);
    }
}

if (!function_exists('get_base_path')) {
    /**
     * Get the application base path (subdirectory)
     */
    function get_base_path(): string
    {
        static $basePath;
        if ($basePath !== null) {
            return $basePath;
        }

        $scriptName = $_SERVER['SCRIPT_NAME'] ?? '';
        $basePath = rtrim(dirname($scriptName), '/\\');

        return $basePath ?: '/';
    }
}

if (!function_exists('url')) {
    /**
     * Generate a full URL from a path
     */
    function url(string $path = '', array $parameters = []): string
    {
        $path = '/' . ltrim($path, '/');
        $basePath = get_base_path();

        $request = request();
        $scheme = isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off' ? 'https' : 'http';
        $host = '';

        if ($request !== null) {
            $uri = $request->getUri();
            $scheme = $uri->getScheme() ?: $scheme;
            $host = $uri->getAuthority();

            // Fallback to Host header if Authority is empty (common in some PSR-7 setups)
            if (empty($host) && $request->hasHeader('Host')) {
                $host = $request->getHeaderLine('Host');
            }
        }

        // If still no host, try $_SERVER or fallback to env('APP_URL')
        if (empty($host)) {
            if (!empty($_SERVER['HTTP_HOST'])) {
                $host = $_SERVER['HTTP_HOST'];
            } else {
                $baseUrl = env('APP_URL');
                if ($baseUrl) {
                    $parsed = parse_url($baseUrl);
                    $scheme = $parsed['scheme'] ?? $scheme;
                    $host = ($parsed['host'] ?? 'localhost') . (isset($parsed['port']) ? ':' . $parsed['port'] : '');
                } else {
                    $host = 'localhost';
                }
            }
        }

        $url = $scheme . '://' . $host . rtrim($basePath, '/') . $path;

        if (!empty($parameters)) {
            $url .= (str_contains($url, '?') ? '&' : '?') . http_build_query($parameters);
        }

        return $url;
    }
}

if (!function_exists('setCurrentRequest')) {
    /**
     * Set the current request instance in the global state
     */
    function setCurrentRequest(ServerRequestInterface $request): void
    {
        $GLOBALS['__current_request'] = $request;
    }
}

if (!function_exists('currentRoute')) {
    /**
     * Get the current route instance
     */
    function currentRoute(?ServerRequestInterface $request = null): ?Route
    {
        return Navigation::getCurrentRoute($request);
    }
}

if (!function_exists('currentRouteName')) {
    /**
     * Get the current route name
     */
    function currentRouteName(?ServerRequestInterface $request = null): ?string
    {
        return Navigation::getCurrentRouteName($request);
    }
}

if (!function_exists('routeIs')) {
    /**
     * Check if current route matches given pattern(s)
     */
    function routeIs(string|array $patterns): bool
    {
        return Navigation::routeIs($patterns);
    }
}

if (!function_exists('currentPath')) {
    /**
     * Get the current path
     */
    function currentPath(?ServerRequestInterface $request = null): string
    {
        return Navigation::getCurrentPath($request);
    }
}

if (!function_exists('currentUrl')) {
    /**
     * Get the current full URL
     */
    function currentUrl(?ServerRequestInterface $request = null, bool $includeQuery = true): string
    {
        $request = $request ?? request();
        if ($request === null) {
            $scheme = isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off' ? 'https' : 'http';
            $host = $_SERVER['HTTP_HOST'] ?? 'localhost';
            $uri = $_SERVER['REQUEST_URI'] ?? '/';
            if (!$includeQuery)
                $uri = parse_url($uri, PHP_URL_PATH) ?: '/';
            return $scheme . '://' . $host . $uri;
        }

        $uri = (string) $request->getUri();
        if (!$includeQuery) {
            $parsedUri = parse_url($uri);
            $uri = ($parsedUri['scheme'] ?? 'http') . '://' . ($parsedUri['host'] ?? 'localhost') . ($parsedUri['path'] ?? '/');
        }
        return $uri;
    }
}

if (!function_exists('hasRoute')) {
    /**
     * Check if a route name exists in the router
     */
    function hasRoute(string $name): bool
    {
        try {
            return app(Router::class)->hasRoute($name);
        } catch (\Exception $e) {
            return false;
        }
    }
}

if (!function_exists('routeParams')) {
    /**
     * Get route parameters from the current request
     */
    function routeParams(?string $key = null, $default = null, ?ServerRequestInterface $request = null)
    {
        $request = $request ?? request();
        if ($request === null)
            return $key === null ? [] : $default;

        $params = [];
        $attributes = $request->getAttributes();
        foreach ($attributes as $attrKey => $value) {
            if (str_starts_with($attrKey, '_'))
                continue;
            $params[$attrKey] = $value;
        }

        if ($key === null)
            return $params;
        return $params[$key] ?? $default;
    }
}

if (!function_exists('isMethod')) {
    /**
     * Check the current request method
     */
    function isMethod(string|array $method, ?ServerRequestInterface $request = null): bool
    {
        $request = $request ?? request();
        $currentMethod = $request ? $request->getMethod() : ($_SERVER['REQUEST_METHOD'] ?? 'GET');
        $currentMethod = strtoupper($currentMethod);
        $methods = is_array($method) ? array_map('strtoupper', $method) : [strtoupper($method)];
        return in_array($currentMethod, $methods, true);
    }
}

if (!function_exists('isGet')) {
    function isGet(?ServerRequestInterface $request = null): bool
    {
        return isMethod('GET', $request);
    }
}
if (!function_exists('isPost')) {
    function isPost(?ServerRequestInterface $request = null): bool
    {
        return isMethod('POST', $request);
    }
}
if (!function_exists('isPut')) {
    function isPut(?ServerRequestInterface $request = null): bool
    {
        return isMethod('PUT', $request);
    }
}
if (!function_exists('isDelete')) {
    function isDelete(?ServerRequestInterface $request = null): bool
    {
        return isMethod('DELETE', $request);
    }
}
if (!function_exists('isPatch')) {
    function isPatch(?ServerRequestInterface $request = null): bool
    {
        return isMethod('PATCH', $request);
    }
}

if (!function_exists('isAjax')) {
    /**
     * Check if the request is an AJAX request
     */
    function isAjax(?ServerRequestInterface $request = null): bool
    {
        $request = $request ?? request();
        if ($request === null) {
            return isset($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest';
        }
        return strtolower($request->getHeaderLine('X-Requested-With')) === 'xmlhttprequest';
    }
}

if (!function_exists('wantsJson')) {
    /**
     * Check if the request expects a JSON response
     */
    function wantsJson(?ServerRequestInterface $request = null): bool
    {
        $request = $request ?? request();
        $accept = $request ? $request->getHeaderLine('Accept') : ($_SERVER['HTTP_ACCEPT'] ?? '');
        return str_contains($accept, 'application/json') || str_contains($accept, 'text/json');
    }
}

if (!function_exists('previousUrl')) {
    /**
     * Get the previous URL from referer header
     */
    function previousUrl(string $default = '/'): string
    {
        return $_SERVER['HTTP_REFERER'] ?? $default;
    }
}

if (!function_exists('redirect')) {
    /**
     * Create a redirect response
     */
    function redirect(?string $url = null, int $status = 302): RedirectResponse|Redirector
    {
        $redirector = new Redirector();
        return is_null($url) ? $redirector : $redirector->to($url, $status);
    }
}

if (!function_exists('redirectTo')) {
    /**
     * Redirect to a named route
     */
    function redirectTo(string $routeName, array $parameters = [], int $status = 302): RedirectResponse
    {
        return redirect(route($routeName, $parameters), $status);
    }
}

if (!function_exists('redirectRoute')) {
    /**
     * Alias for redirectTo
     */
    function redirectRoute(string $routeName, array $parameters = [], int $status = 302): RedirectResponse
    {
        return redirectTo($routeName, $parameters, $status);
    }
}

if (!function_exists('back')) {
    /**
     * Create a redirect to the previous URL
     */
    function back(int $status = 302, array $headers = [], string $fallback = '/'): RedirectResponse
    {
        return (new Redirector())->back($status, $headers, $fallback);
    }
}

if (!function_exists('redirectBack')) {
    /**
     * Alias for back
     */
    function redirectBack(string $fallback = '/', int $status = 302): RedirectResponse
    {
        return back($status, [], $fallback);
    }
}

if (!function_exists('isActive')) {
    /**
     * Check if the given path/route is active
     */
    function isActive(string|array $path, bool $exact = false): bool
    {
        return Navigation::isActive($path, $exact);
    }
}

if (!function_exists('activeClass')) {
    /**
     * Return active class if path matches
     */
    function activeClass(string|array $path, string $activeClass = 'active', string $inactiveClass = '', bool $exact = false): string
    {
        return isActive($path, $exact) ? $activeClass : $inactiveClass;
    }
}

if (!function_exists('activeRoute')) {
    function activeRoute(string|array $routeName, string $activeClass = 'active', string $inactiveClass = ''): string
    {
        return routeIs($routeName) ? $activeClass : $inactiveClass;
    }
}

if (!function_exists('activePath')) {
    function activePath(string|array $path, string $activeClass = 'active', string $inactiveClass = '', bool $exact = false): string
    {
        return activeClass($path, $activeClass, $inactiveClass, $exact);
    }
}
