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
    /**
     * Generate a URL for a named route.
     *
     * @param string $name Route name
     * @param array $parameters Route parameters
     * @param bool $absolute Generate absolute URL
     * @return string Generated URL
     */
    function route(string $name, array $parameters = [], bool $absolute = false): string
    {
        $router = app(Router::class);
        return $router->route($name, $parameters, $absolute);
    }
}

if (!function_exists('redirect')) {
    /**
     * Create a redirect response.
     *
     * @param string $url Target URL or route name
     * @param int $status HTTP status code (302 by default)
     * @param array $headers Additional headers
     * @return ResponseInterface
     */
    function redirect(string $url, int $status = 302, array $headers = []): ResponseInterface
    {
        // Check if it's a route name (doesn't start with / or http)
        if (!str_starts_with($url, '/') && !str_starts_with($url, 'http')) {
            try {
                $url = route($url);
            } catch (\Exception $e) {
                // If route not found, treat as regular URL
            }
        }

        return ResponseFactory::redirect($url, $status);
    }
}

if (!function_exists('redirectTo')) {
    /**
     * Create a redirect to named route.
     *
     * @param string $routeName Route name
     * @param array $parameters Route parameters
     * @param int $status HTTP status code
     * @return ResponseInterface
     */
    function redirectTo(string $routeName, array $parameters = [], int $status = 302): ResponseInterface
    {
        $url = route($routeName, $parameters);
        return ResponseFactory::redirect($url, $status);
    }
}

if (!function_exists('redirectBack')) {
    /**
     * Create a redirect back to the previous page.
     *
     * @param string $fallback Fallback URL if referrer is not available
     * @param int $status HTTP status code
     * @return ResponseInterface
     */
    function redirectBack(string $fallback = '/', int $status = 302): ResponseInterface
    {
        $referer = $_SERVER['HTTP_REFERER'] ?? $fallback;
        return ResponseFactory::redirect($referer, $status);
    }
}

if (!function_exists('currentRoute')) {
    /**
     * Get the current route from request.
     *
     * @param ServerRequestInterface|null $request
     * @return \Plugs\Router\Route|null
     */
    function currentRoute(?ServerRequestInterface $request = null): ?\Plugs\Router\Route
    {
        $request = $request ?? request();
        return $request?->getAttribute('_route');
    }
}

if (!function_exists('currentRouteName')) {
    /**
     * Get the current route name.
     *
     * @param ServerRequestInterface|null $request
     * @return string|null
     */
    function currentRouteName(?ServerRequestInterface $request = null): ?string
    {
        $route = currentRoute($request);
        return $route?->getName();
    }
}

if (!function_exists('currentPath')) {
    /**
     * Get the current request path.
     *
     * @param ServerRequestInterface|null $request
     * @return string
     */
    function currentPath(?ServerRequestInterface $request = null): string
    {
        $request = $request ?? request();
        return $request?->getUri()->getPath() ?? '/';
    }
}

if (!function_exists('isRoute')) {
    /**
     * Check if current route matches the given name.
     *
     * @param string|array $routeName Route name(s) to check
     * @param ServerRequestInterface|null $request
     * @return bool
     */
    function isRoute($routeName, ?ServerRequestInterface $request = null): bool
    {
        $current = currentRouteName($request);

        if ($current === null) {
            return false;
        }

        if (is_array($routeName)) {
            return in_array($current, $routeName, true);
        }

        return $current === $routeName;
    }
}

if (!function_exists('isPath')) {
    /**
     * Check if current path matches the given path or pattern.
     *
     * @param string $path Path to check (supports wildcards)
     * @param ServerRequestInterface|null $request
     * @return bool
     */
    function isPath(string $path, ?ServerRequestInterface $request = null): bool
    {
        $currentPath = currentPath($request);

        // Exact match
        if ($currentPath === $path) {
            return true;
        }

        // Wildcard support: /admin/* matches /admin/users, /admin/posts, etc.
        if (str_contains($path, '*')) {
            $pattern = '#^' . str_replace('\*', '.*', preg_quote($path, '#')) . '#';
            return preg_match($pattern, $currentPath) === 1;
        }

        return false;
    }
}

if (!function_exists('routeIs')) {
    /**
     * Check if current route name matches pattern(s).
     * Supports wildcards: admin.* matches admin.users, admin.posts, etc.
     *
     * @param string|array $patterns Pattern(s) to check
     * @param ServerRequestInterface|null $request
     * @return bool
     */
    function routeIs($patterns, ?ServerRequestInterface $request = null): bool
    {
        $current = currentRouteName($request);

        if ($current === null) {
            return false;
        }

        $patterns = is_array($patterns) ? $patterns : [$patterns];

        foreach ($patterns as $pattern) {
            // Exact match
            if ($current === $pattern) {
                return true;
            }

            // Wildcard match
            if (str_contains($pattern, '*')) {
                $regex = '#^' . str_replace('\*', '.*', preg_quote($pattern, '#')) . '$#';
                if (preg_match($regex, $current) === 1) {
                    return true;
                }
            }
        }

        return false;
    }
}

if (!function_exists('activeClass')) {
    /**
     * Return CSS class if current route/path matches.
     *
     * @param string|array $routeOrPath Route name or path pattern
     * @param string $activeClass Class to return when active
     * @param string $inactiveClass Class to return when inactive
     * @param bool $checkPath Check path instead of route name
     * @param ServerRequestInterface|null $request
     * @return string
     */
    function activeClass(
        $routeOrPath,
        string $activeClass = 'active',
        string $inactiveClass = '',
        bool $checkPath = false,
        ?ServerRequestInterface $request = null
    ): string {
        $isActive = $checkPath
            ? isPath($routeOrPath, $request)
            : routeIs($routeOrPath, $request);

        return $isActive ? $activeClass : $inactiveClass;
    }
}

if (!function_exists('activeLink')) {
    /**
     * Return aria-current attribute if route/path is active.
     *
     * @param string|array $routeOrPath Route name or path pattern
     * @param bool $checkPath Check path instead of route name
     * @param ServerRequestInterface|null $request
     * @return string
     */
    function activeLink(
        $routeOrPath,
        bool $checkPath = false,
        ?ServerRequestInterface $request = null
    ): string {
        $isActive = $checkPath
            ? isPath($routeOrPath, $request)
            : routeIs($routeOrPath, $request);

        return $isActive ? 'aria-current="page"' : '';
    }
}

if (!function_exists('urlFor')) {
    /**
     * Generate URL for route with query parameters.
     *
     * @param string $name Route name
     * @param array $parameters Route parameters
     * @param array $query Query string parameters
     * @param bool $absolute Generate absolute URL
     * @return string
     */
    function urlFor(
        string $name,
        array $parameters = [],
        array $query = [],
        bool $absolute = false
    ): string {
        $url = route($name, $parameters, $absolute);

        if (!empty($query)) {
            $url .= '?' . http_build_query($query);
        }

        return $url;
    }
}

if (!function_exists('currentUrl')) {
    /**
     * Get the current full URL.
     *
     * @param ServerRequestInterface|null $request
     * @return string
     */
    function currentUrl(?ServerRequestInterface $request = null): string
    {
        $request = $request ?? request();

        if ($request === null) {
            return '';
        }

        $uri = $request->getUri();
        return (string) $uri;
    }
}

if (!function_exists('currentUrlWithQuery')) {
    /**
     * Get current URL with modified query parameters.
     *
     * @param array $add Query parameters to add/update
     * @param array|null $remove Query parameters to remove (null = keep all)
     * @param ServerRequestInterface|null $request
     * @return string
     */
    function currentUrlWithQuery(
        array $add = [],
        ?array $remove = null,
        ?ServerRequestInterface $request = null
    ): string {
        $request = $request ?? request();

        if ($request === null) {
            return '';
        }

        $query = $request->getQueryParams();

        // Add/update parameters
        $query = array_merge($query, $add);

        // Remove specified parameters
        if ($remove !== null) {
            foreach ($remove as $key) {
                unset($query[$key]);
            }
        }

        $uri = $request->getUri();
        $url = $uri->getScheme() . '://' . $uri->getAuthority() . $uri->getPath();

        if (!empty($query)) {
            $url .= '?' . http_build_query($query);
        }

        return $url;
    }
}

if (!function_exists('hasRoute')) {
    /**
     * Check if a named route exists.
     *
     * @param string $name Route name
     * @return bool
     */
    function hasRoute(string $name): bool
    {
        $router = app(Router::class);
        return $router->hasRoute($name);
    }
}

if (!function_exists('routeParams')) {
    /**
     * Get route parameters from current request.
     *
     * @param string|null $key Specific parameter key (null = all params)
     * @param mixed $default Default value if parameter doesn't exist
     * @param ServerRequestInterface|null $request
     * @return mixed
     */
    function routeParams(?string $key = null, $default = null, ?ServerRequestInterface $request = null)
    {
        $route = currentRoute($request);

        if ($route === null) {
            return $key === null ? [] : $default;
        }

        $request = $request ?? request();
        $path = currentPath($request);
        $params = $route->extractParameters($path);

        if ($key === null) {
            return $params;
        }

        return $params[$key] ?? $default;
    }
}

if (!function_exists('isMethod')) {
    /**
     * Check if current request method matches.
     *
     * @param string|array $method HTTP method(s) to check
     * @param ServerRequestInterface|null $request
     * @return bool
     */
    function isMethod($method, ?ServerRequestInterface $request = null): bool
    {
        $request = $request ?? request();

        if ($request === null) {
            return false;
        }

        $currentMethod = strtoupper($request->getMethod());
        $methods = is_array($method)
            ? array_map('strtoupper', $method)
            : [strtoupper($method)];

        return in_array($currentMethod, $methods, true);
    }
}

if (!function_exists('abortIf')) {
    /**
     * Abort with response if condition is true.
     *
     * @param bool $condition Condition to check
     * @param ResponseInterface|int $response Response or status code
     * @param string $message Optional message for status code
     * @return void
     * @throws \RuntimeException
     */
    function abortIf(bool $condition, $response, string $message = ''): void
    {
        if (!$condition) {
            return;
        }

        if (is_int($response)) {
            $response = ResponseFactory::json(
                ['error' => $message ?: 'Request aborted'],
                $response
            );
        }

        throw new class($response) extends \RuntimeException {
            private $response;

            public function __construct(ResponseInterface $response)
            {
                $this->response = $response;
                parent::__construct('Request aborted');
            }

            public function getResponse(): ResponseInterface
            {
                return $this->response;
            }
        };
    }
}

if (!function_exists('previousUrl')) {
    /**
     * Get the previous URL from HTTP referer.
     *
     * @param string $default Default URL if referer is not available
     * @return string
     */
    function previousUrl(string $default = '/'): string
    {
        return $_SERVER['HTTP_REFERER'] ?? $default;
    }
}

if (!function_exists('back')) {
    /**
     * Get the previous URL from HTTP referer.
     *
     * @param string $default Default URL if referer is not available
     * @return string
     */
    function back(string $default = '/'): string
    {
        return $_SERVER['HTTP_REFERER'] ?? $default;
    }
}
