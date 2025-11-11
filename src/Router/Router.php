<?php

declare(strict_types=1);

namespace Plugs\Router;

/*
|--------------------------------------------------------------------------
| Router Class
|--------------------------------------------------------------------------
|
| Router class for handling application routing.
*/

use Plugs\Container\Container;
use Plugs\Http\MiddlewareDispatcher;
use Plugs\Http\ResponseFactory;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use RuntimeException;
use InvalidArgumentException;

class Router
{
    /**
     * @var Route[] Registered routes
     */
    private array $routes = [];

    /**
     * @var string Current group prefix
     */
    private string $groupPrefix = '';

    /**
     * @var array Current group middleware
     */
    private array $groupMiddleware = [];

    /**
     * @var array Named routes for URL generation
     */
    private array $namedRoutes = [];

    /**
     * @var array Cached middleware aliases
     */
    private ?array $middlewareAliases = null;

    /**
     * Register a GET route.
     *
     * @param string $path Route path pattern
     * @param callable|string|array $handler Route handler
     * @param array $middleware Route-specific middleware
     * @return Route
     */
    public function get(string $path, $handler, array $middleware = []): Route
    {
        return $this->addRoute('GET', $path, $handler, $middleware);
    }

    /**
     * Register a POST route.
     *
     * @param string $path Route path pattern
     * @param callable|string|array $handler Route handler
     * @param array $middleware Route-specific middleware
     * @return Route
     */
    public function post(string $path, $handler, array $middleware = []): Route
    {
        return $this->addRoute('POST', $path, $handler, $middleware);
    }

    /**
     * Register a PUT route.
     *
     * @param string $path Route path pattern
     * @param callable|string|array $handler Route handler
     * @param array $middleware Route-specific middleware
     * @return Route
     */
    public function put(string $path, $handler, array $middleware = []): Route
    {
        return $this->addRoute('PUT', $path, $handler, $middleware);
    }

    /**
     * Register a DELETE route.
     *
     * @param string $path Route path pattern
     * @param callable|string|array $handler Route handler
     * @param array $middleware Route-specific middleware
     * @return Route
     */
    public function delete(string $path, $handler, array $middleware = []): Route
    {
        return $this->addRoute('DELETE', $path, $handler, $middleware);
    }

    /**
     * Register a PATCH route.
     *
     * @param string $path Route path pattern
     * @param callable|string|array $handler Route handler
     * @param array $middleware Route-specific middleware
     * @return Route
     */
    public function patch(string $path, $handler, array $middleware = []): Route
    {
        return $this->addRoute('PATCH', $path, $handler, $middleware);
    }

    /**
     * Register a route for multiple HTTP methods.
     *
     * @param array $methods HTTP methods
     * @param string $path Route path pattern
     * @param callable|string|array $handler Route handler
     * @param array $middleware Route-specific middleware
     * @return Route Last registered route
     */
    public function match(array $methods, string $path, $handler, array $middleware = []): Route
    {
        if (empty($methods)) {
            throw new InvalidArgumentException('At least one HTTP method must be specified');
        }

        $route = null;
        foreach ($methods as $method) {
            $route = $this->addRoute(strtoupper($method), $path, $handler, $middleware);
        }

        return $route;
    }

    /**
     * Register a route for all HTTP methods.
     *
     * @param string $path Route path pattern
     * @param callable|string|array $handler Route handler
     * @param array $middleware Route-specific middleware
     * @return Route Last registered route
     */
    public function any(string $path, $handler, array $middleware = []): Route
    {
        return $this->match(
            ['GET', 'POST', 'PUT', 'DELETE', 'PATCH', 'OPTIONS', 'HEAD'],
            $path,
            $handler,
            $middleware
        );
    }

    /**
     * Group routes with common attributes.
     *
     * @param array $attributes Group attributes (prefix, middleware)
     * @param callable $callback Callback to register routes
     * @return void
     */
    public function group(array $attributes, callable $callback): void
    {
        $previousPrefix = $this->groupPrefix;
        $previousMiddleware = $this->groupMiddleware;

        // Apply group prefix
        if (isset($attributes['prefix'])) {
            $prefix = trim($attributes['prefix'], '/');
            $this->groupPrefix = $previousPrefix . ($prefix ? '/' . $prefix : '');
        }

        // Apply group middleware
        if (isset($attributes['middleware'])) {
            $middleware = is_array($attributes['middleware'])
                ? $attributes['middleware']
                : [$attributes['middleware']];
            $this->groupMiddleware = array_merge($previousMiddleware, $middleware);
        }

        // Execute callback to register routes
        $callback($this);

        // Restore previous state
        $this->groupPrefix = $previousPrefix;
        $this->groupMiddleware = $previousMiddleware;
    }

    /**
     * Add a route to the collection.
     *
     * @param string $method HTTP method
     * @param string $path Route path pattern
     * @param callable|string|array $handler Route handler
     * @param array $middleware Route-specific middleware
     * @return Route
     */
    private function addRoute(string $method, string $path, $handler, array $middleware = []): Route
    {
        // Normalize path
        $path = '/' . trim($this->groupPrefix . '/' . trim($path, '/'), '/');
        if ($path !== '/' && str_ends_with($path, '/')) {
            $path = rtrim($path, '/');
        }

        // Merge group and route middleware
        $middleware = array_merge($this->groupMiddleware, $middleware);

        // Normalize handler format
        $handler = $this->normalizeHandler($handler);

        $route = new Route($method, $path, $handler, $middleware, $this);
        $this->routes[] = $route;

        return $route;
    }

    /**
     * Normalize handler to consistent format.
     *
     * @param mixed $handler Route handler
     * @return callable|string Normalized handler
     */
    private function normalizeHandler($handler)
    {
        // Convert array handler [Controller::class, 'method'] to string
        if (is_array($handler) && count($handler) === 2) {
            if (is_string($handler[0]) && is_string($handler[1])) {
                return $handler[0] . '@' . $handler[1];
            }
            if (is_object($handler[0]) && is_string($handler[1])) {
                // Instance method - keep as array for callable
                return $handler;
            }
        }

        return $handler;
    }

    /**
     * Get route by name.
     *
     * @param string $name Route name
     * @return Route|null
     */
    public function getRouteByName(string $name): ?Route
    {
        return $this->namedRoutes[$name] ?? null;
    }

    /**
     * Register named route internally.
     *
     * @param string $name Route name
     * @param Route $route Route instance
     * @return void
     * @throws RuntimeException If route name already exists
     */
    public function registerNamedRoute(string $name, Route $route): void
    {
        if (isset($this->namedRoutes[$name])) {
            throw new RuntimeException("Route name [{$name}] already exists");
        }

        $this->namedRoutes[$name] = $route;
    }

    /**
     * Generate URL for named route.
     *
     * @param string $name Route name
     * @param array $parameters Route parameters
     * @param bool $absolute Generate absolute URL
     * @return string Generated URL
     * @throws RuntimeException If route not found
     */
    public function route(string $name, array $parameters = [], bool $absolute = false): string
    {
        $route = $this->getRouteByName($name);

        if ($route === null) {
            throw new RuntimeException("Route [{$name}] not found");
        }

        $path = $route->getPath();

        // Replace required parameters
        foreach ($parameters as $key => $value) {
            $path = str_replace('{' . $key . '}', (string) $value, $path);
            $path = str_replace('{' . $key . '?}', (string) $value, $path);
        }

        // Remove unfilled optional parameters
        $path = preg_replace('/\{[^}]+\?\}/', '', $path);

        // Check for unfilled required parameters
        if (preg_match('/\{([^}?]+)\}/', $path, $matches)) {
            throw new RuntimeException(
                "Missing required parameter [{$matches[1]}] for route [{$name}]"
            );
        }

        // Generate absolute URL if requested
        if ($absolute && isset($_SERVER['HTTP_HOST'])) {
            $scheme = isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off' ? 'https' : 'http';
            $path = $scheme . '://' . $_SERVER['HTTP_HOST'] . $path;
        }

        return $path;
    }

    /**
     * Dispatch the request to matching route.
     *
     * @param ServerRequestInterface $request PSR-7 request
     * @return ResponseInterface|null Response or null if no route matches
     */
    public function dispatch(ServerRequestInterface $request): ?ResponseInterface
    {
        $method = $request->getMethod();
        $path = $request->getUri()->getPath();

        // Handle HEAD requests as GET
        if ($method === 'HEAD') {
            $method = 'GET';
        }

        foreach ($this->routes as $route) {
            if (!$route->matches($method, $path)) {
                continue;
            }

            // Extract route parameters
            $params = $route->extractParameters($path);

            // Add parameters to request attributes
            foreach ($params as $key => $value) {
                $request = $request->withAttribute($key, $value);
            }

            // Add route and router info to request
            $request = $request->withAttribute('_route', $route);
            $request = $request->withAttribute('_router', $this);

            // Execute middleware chain or handler directly
            if (!empty($route->getMiddleware())) {
                return $this->runMiddleware($route, $request);
            }

            return $this->executeHandler($route->getHandler(), $request);
        }

        return null;
    }

    /**
     * Run middleware chain for route.
     *
     * @param Route $route Matched route
     * @param ServerRequestInterface $request PSR-7 request
     * @return ResponseInterface
     */
    private function runMiddleware(Route $route, ServerRequestInterface $request): ResponseInterface
    {
        $middleware = $route->getMiddleware();
        $handler = $route->getHandler();

        // Create middleware dispatcher
        $stack = new MiddlewareDispatcher();

        // Add middleware to stack
        foreach ($middleware as $mw) {
            if (is_string($mw)) {
                $mw = $this->resolveMiddleware($mw);
            }
            $stack->add($mw);
        }

        // Set the route handler as final handler
        $stack->setFallbackHandler(function ($request) use ($handler) {
            return $this->executeHandler($handler, $request);
        });

        return $stack->handle($request);
    }

    /**
     * Resolve middleware from alias or class name.
     *
     * @param string $middleware Middleware alias or class name
     * @return object Middleware instance
     */
    private function resolveMiddleware(string $middleware): object
    {
        // Load middleware aliases on first use
        if ($this->middlewareAliases === null) {
            $config = config('middleware');
            $this->middlewareAliases = is_array($config) ? ($config['aliases'] ?? []) : [];
        }

        // Resolve alias to class name
        if (isset($this->middlewareAliases[$middleware])) {
            $middleware = $this->middlewareAliases[$middleware];
        }

        // Resolve from container
        $container = Container::getInstance();
        return $container->make($middleware);
    }

    /**
     * Execute route handler.
     *
     * @param mixed $handler Route handler
     * @param ServerRequestInterface $request PSR-7 request
     * @return ResponseInterface
     * @throws RuntimeException If handler is invalid or returns invalid response
     */
    private function executeHandler($handler, ServerRequestInterface $request): ResponseInterface
    {
        $response = null;

        if (is_string($handler) && strpos($handler, '@') !== false) {
            $response = $this->executeControllerAction($handler, $request);
        } elseif (is_callable($handler)) {
            $response = $handler($request);
        } else {
            throw new RuntimeException('Invalid route handler type');
        }

        return $this->normalizeResponse($response);
    }

    /**
     * Execute controller action.
     *
     * @param string $handler Controller@method string
     * @param ServerRequestInterface $request PSR-7 request
     * @return mixed Controller response
     * @throws RuntimeException If controller or method not found
     */
    private function executeControllerAction(string $handler, ServerRequestInterface $request)
    {
        [$controller, $method] = explode('@', $handler, 2);

        if (!class_exists($controller)) {
            throw new RuntimeException("Controller [{$controller}] not found");
        }

        // Resolve controller from container with dependency injection
        $container = Container::getInstance();
        $instance = $container->make($controller);

        if (!method_exists($instance, $method)) {
            throw new RuntimeException(
                "Method [{$method}] not found in controller [{$controller}]"
            );
        }

        return $instance->$method($request);
    }

    /**
     * Normalize response to PSR-7 ResponseInterface.
     *
     * @param mixed $response Handler response
     * @return ResponseInterface PSR-7 response
     * @throws RuntimeException If response cannot be normalized
     */
    private function normalizeResponse($response): ResponseInterface
    {
        // Already a PSR-7 response
        if ($response instanceof ResponseInterface) {
            return $response;
        }

        // String response - HTML response
        if (is_string($response)) {
            return ResponseFactory::html($response);
        }

        // Array response - JSON response
        if (is_array($response)) {
            return ResponseFactory::json($response);
        }

        // Null response - 204 No Content
        if ($response === null) {
            return ResponseFactory::html('', 204);
        }

        // Object with __toString
        if (is_object($response) && method_exists($response, '__toString')) {
            return ResponseFactory::html((string) $response);
        }

        throw new RuntimeException(
            'Route handler must return ResponseInterface, string, array, or null. Got: '
                . gettype($response)
        );
    }

    /**
     * Get all registered routes.
     *
     * @return Route[]
     */
    public function getRoutes(): array
    {
        return $this->routes;
    }

    /**
     * Get all named routes.
     *
     * @return array<string, Route>
     */
    public function getNamedRoutes(): array
    {
        return $this->namedRoutes;
    }

    /**
     * Check if a named route exists.
     *
     * @param string $name Route name
     * @return bool
     */
    public function hasRoute(string $name): bool
    {
        return isset($this->namedRoutes[$name]);
    }

    /**
     * Clear all registered routes (useful for testing).
     *
     * @return void
     */
    public function clear(): void
    {
        $this->routes = [];
        $this->namedRoutes = [];
        $this->groupPrefix = '';
        $this->groupMiddleware = [];
    }
}
