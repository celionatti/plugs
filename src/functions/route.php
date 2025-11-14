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
use Plugs\Http\RedirectResponse;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Message\ResponseInterface;

if (!function_exists('route')) {
    /**
     * Generate a URL for a named route
     */
    function route(string $name, array $parameters = [], bool $absolute = true): string
    {
        $router = app(Router::class);
        return $router->route($name, $parameters, $absolute);
    }
}

if (!function_exists('url')) {
    /**
     * Generate a full URL from a path
     */
    function url(string $path = '', array $parameters = []): string
    {
        $request = request();
        
        if ($request === null) {
            $scheme = isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off' ? 'https' : 'http';
            $host = $_SERVER['HTTP_HOST'] ?? 'localhost';
            $basePath = rtrim($path, '/');
        } else {
            $uri = $request->getUri();
            $scheme = $uri->getScheme();
            $host = $uri->getHost();
            $port = $uri->getPort();
            $host .= ($port && !in_array($port, [80, 443])) ? ":$port" : '';
            $basePath = '/' . ltrim($path, '/');
        }
        
        $url = "$scheme://$host$basePath";
        
        if (!empty($parameters)) {
            $url .= '?' . http_build_query($parameters);
        }
        
        return $url;
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
        $route = currentRoute($request);
        return $route?->getName();
    }
}

if (!function_exists('routeIs')) {
    /**
     * Check if current route matches given pattern(s)
     */
    function routeIs(string|array $patterns): bool
    {
        $currentName = currentRouteName();
        
        if ($currentName === null) {
            return false;
        }
        
        $patterns = is_array($patterns) ? $patterns : [$patterns];
        
        foreach ($patterns as $pattern) {
            // Convert wildcard pattern to regex
            $regex = preg_quote($pattern, '#');
            $regex = str_replace('\*', '.*', $regex);
            
            if (preg_match('#^' . $regex . '$#', $currentName)) {
                return true;
            }
        }
        
        return false;
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
    function currentUrl(?ServerRequestInterface $request = null, bool $includeQuery = true): string
    {
        $request = $request ?? request();

        if ($request === null) {
            $scheme = isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off' ? 'https' : 'http';
            $host = $_SERVER['HTTP_HOST'] ?? 'localhost';
            $uri = $_SERVER['REQUEST_URI'] ?? '/';
            
            if (!$includeQuery) {
                $uri = parse_url($uri, PHP_URL_PATH) ?: '/';
            }
            
            return $scheme . '://' . $host . $uri;
        }

        $uri = (string) $request->getUri();
        
        if (!$includeQuery) {
            $parsedUri = parse_url($uri);
            $uri = ($parsedUri['scheme'] ?? 'http') . '://' . 
                   ($parsedUri['host'] ?? 'localhost') . 
                   ($parsedUri['path'] ?? '/');
        }
        
        return $uri;
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
            if (str_starts_with($attrKey, '_')) {
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
    function isMethod(string|array $method, ?ServerRequestInterface $request = null): bool
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
            return isset($_SERVER['HTTP_X_REQUESTED_WITH']) && 
                   strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest';
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
        
        if ($request === null) {
            $accept = $_SERVER['HTTP_ACCEPT'] ?? '';
        } else {
            $accept = $request->getHeaderLine('Accept');
        }
        
        return str_contains($accept, 'application/json') || 
               str_contains($accept, 'text/json');
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

/*
|--------------------------------------------------------------------------
| Chainable Redirect Functions
|--------------------------------------------------------------------------
*/

if (!function_exists('redirect')) {
    /**
     * Create a redirect response (chainable)
     */
    function redirect(?string $url = null, int $status = 302): RedirectResponse
    {
        $redirectResponse = new RedirectResponse($url ?? '/', $status);
        
        return $redirectResponse;
    }
}

if (!function_exists('redirectTo')) {
    /**
     * Create a redirect to a named route (chainable)
     */
    function redirectTo(string $routeName, array $parameters = [], int $status = 302): RedirectResponse
    {
        $url = route($routeName, $parameters);
        return redirect($url, $status);
    }
}

if (!function_exists('redirectBack')) {
    /**
     * Create a redirect to the previous URL (chainable)
     */
    function redirectBack(string $fallback = '/', int $status = 302): RedirectResponse
    {
        $url = previousUrl($fallback);
        return redirect($url, $status);
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