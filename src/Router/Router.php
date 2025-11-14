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
use ReflectionParameter;

use Plugs\Http\MiddlewareDispatcher;
use Plugs\Http\ResponseFactory;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use RuntimeException;
use InvalidArgumentException;

class Router
{
    /** @var Route[] Registered routes */
    private array $routes = [];

    /** @var string Current group prefix */
    private string $groupPrefix = '';

    /** @var array Current group middleware */
    private array $groupMiddleware = [];

    /** @var string Current group namespace */
    private string $groupNamespace = '';

    /** @var array Named routes for URL generation */
    private array $namedRoutes = [];

    /** @var array Cached middleware aliases */
    private ?array $middlewareAliases = null;

    /** @var array Route cache for performance */
    private array $routeCache = [];

    /** @var bool Enable route caching */
    private bool $cacheEnabled = true;

    /** @var int Maximum cache size */
    private const MAX_CACHE_SIZE = 1000;

    private ?ServerRequestInterface $currentRequest = null;

    /**
     * Register a GET route
     */
    public function get(string $path, $handler, array $middleware = []): Route
    {
        return $this->addRoute('GET', $path, $handler, $middleware);
    }

    /**
     * Register a POST route
     */
    public function post(string $path, $handler, array $middleware = []): Route
    {
        return $this->addRoute('POST', $path, $handler, $middleware);
    }

    /**
     * Register a PUT route
     */
    public function put(string $path, $handler, array $middleware = []): Route
    {
        return $this->addRoute('PUT', $path, $handler, $middleware);
    }

    /**
     * Register a DELETE route
     */
    public function delete(string $path, $handler, array $middleware = []): Route
    {
        return $this->addRoute('DELETE', $path, $handler, $middleware);
    }

    /**
     * Register a PATCH route
     */
    public function patch(string $path, $handler, array $middleware = []): Route
    {
        return $this->addRoute('PATCH', $path, $handler, $middleware);
    }

    /**
     * Register an OPTIONS route
     */
    public function options(string $path, $handler, array $middleware = []): Route
    {
        return $this->addRoute('OPTIONS', $path, $handler, $middleware);
    }

    /**
     * Register a HEAD route
     */
    public function head(string $path, $handler, array $middleware = []): Route
    {
        return $this->addRoute('HEAD', $path, $handler, $middleware);
    }

    /**
     * Register a route for multiple HTTP methods
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
     * Register a route for all HTTP methods
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
     * Register a RESTful resource controller
     * 
     * Creates standard CRUD routes:
     * GET    /resource          -> index
     * GET    /resource/create   -> create
     * POST   /resource          -> store
     * GET    /resource/{id}     -> show
     * GET    /resource/{id}/edit -> edit
     * PUT    /resource/{id}     -> update
     * PATCH  /resource/{id}     -> update
     * DELETE /resource/{id}     -> destroy
     */
    public function resource(string $name, string $controller, array $options = []): void
    {
        $only = $options['only'] ?? ['index', 'create', 'store', 'show', 'edit', 'update', 'destroy'];
        $except = $options['except'] ?? [];
        $middleware = $options['middleware'] ?? [];
        $names = $options['names'] ?? [];

        $actions = array_diff($only, $except);

        $resourceRoutes = [
            'index' => ['GET', '', 'index'],
            'create' => ['GET', '/create', 'create'],
            'store' => ['POST', '', 'store'],
            'show' => ['GET', '/{id}', 'show'],
            'edit' => ['GET', '/{id}/edit', 'edit'],
            'update' => ['PUT', '/{id}', 'update'],
            'destroy' => ['DELETE', '/{id}', 'destroy'],
        ];

        foreach ($resourceRoutes as $action => $routeData) {
            if (!in_array($action, $actions, true)) {
                continue;
            }

            [$method, $path, $controllerMethod] = $routeData;
            $route = $this->addRoute(
                $method,
                '/' . trim($name, '/') . $path,
                $controller . '@' . $controllerMethod,
                $middleware
            );

            // Add PATCH support for update
            if ($action === 'update') {
                $this->addRoute(
                    'PATCH',
                    '/' . trim($name, '/') . $path,
                    $controller . '@' . $controllerMethod,
                    $middleware
                );
            }

            // Set route name if provided
            if (isset($names[$action])) {
                $route->name($names[$action]);
            } else {
                $route->name("{$name}.{$action}");
            }
        }
    }

    /**
     * Register API resource routes (without create/edit)
     */
    public function apiResource(string $name, string $controller, array $options = []): void
    {
        $options['except'] = array_merge($options['except'] ?? [], ['create', 'edit']);
        $this->resource($name, $controller, $options);
    }

    /**
     * Group routes with common attributes
     */
    public function group(array $attributes, callable $callback): void
    {
        $previousPrefix = $this->groupPrefix;
        $previousMiddleware = $this->groupMiddleware;
        $previousNamespace = $this->groupNamespace;

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

        // Apply group namespace
        if (isset($attributes['namespace'])) {
            $this->groupNamespace = $previousNamespace
                ? $previousNamespace . '\\' . trim($attributes['namespace'], '\\')
                : trim($attributes['namespace'], '\\');
        }

        // Execute callback to register routes
        $callback($this);

        // Restore previous state
        $this->groupPrefix = $previousPrefix;
        $this->groupMiddleware = $previousMiddleware;
        $this->groupNamespace = $previousNamespace;
    }

    /**
     * Add a route to the collection
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

        // Apply namespace to handler if it's a string
        $handler = $this->normalizeHandler($handler);

        $route = new Route($method, $path, $handler, $middleware, $this);
        $this->routes[] = $route;

        // Clear route cache when new routes are added
        $this->clearCache();

        return $route;
    }

    /**
     * Normalize handler to consistent format
     */
    private function normalizeHandler($handler)
    {
        // Convert array handler [Controller::class, 'method'] to string
        if (is_array($handler) && count($handler) === 2) {
            if (is_string($handler[0]) && is_string($handler[1])) {
                $handler = $handler[0] . '@' . $handler[1];
            } elseif (is_object($handler[0]) && is_string($handler[1])) {
                // Instance method - keep as array for callable
                return $handler;
            }
        }

        // Apply namespace to controller string
        if (is_string($handler) && strpos($handler, '@') !== false && $this->groupNamespace) {
            [$controller, $method] = explode('@', $handler, 2);

            // Don't apply namespace if controller already has namespace
            if (strpos($controller, '\\') === false) {
                $handler = $this->groupNamespace . '\\' . $controller . '@' . $method;
            }
        }

        return $handler;
    }

    /**
     * Get route by name
     */
    public function getRouteByName(string $name): ?Route
    {
        return $this->namedRoutes[$name] ?? null;
    }

    /**
     * Register named route internally
     */
    public function registerNamedRoute(string $name, Route $route): void
    {
        if (isset($this->namedRoutes[$name])) {
            throw new RuntimeException("Route name [{$name}] already exists");
        }

        $this->namedRoutes[$name] = $route;
    }

    /**
     * Generate URL for named route
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
        if ($absolute) {
            $scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
            $host = $_SERVER['HTTP_HOST'] ?? 'localhost';
            $path = $scheme . '://' . $host . $path;
        }

        return $path;
    }

    public function getCurrentRequest(): ?ServerRequestInterface
    {
        return $this->currentRequest;
    }

    /**
     * Dispatch the request to matching route
     */
    public function dispatch(ServerRequestInterface $request): ?ResponseInterface
    {
        $this->currentRequest = $request;
        setCurrentRequest($request);

        $method = $request->getMethod();
        $path = $request->getUri()->getPath();

        // Handle HEAD requests as GET
        if ($method === 'HEAD') {
            $method = 'GET';
        }

        // Check cache first
        $cacheKey = $method . ':' . $path;
        if ($this->cacheEnabled && isset($this->routeCache[$cacheKey])) {
            return $this->dispatchCachedRoute($this->routeCache[$cacheKey], $request);
        }

        // Find matching route
        foreach ($this->routes as $route) {
            if (!$route->matches($method, $path)) {
                continue;
            }

            // Cache the route match
            if ($this->cacheEnabled) {
                $this->cacheRoute($cacheKey, $route);
            }

            return $this->dispatchRoute($route, $request, $path);
        }

        return null;
    }

    /**
     * Dispatch a cached route
     */
    private function dispatchCachedRoute(Route $route, ServerRequestInterface $request): ResponseInterface
    {
        $path = $request->getUri()->getPath();
        return $this->dispatchRoute($route, $request, $path);
    }

    /**
     * Dispatch a matched route
     */
    private function dispatchRoute(Route $route, ServerRequestInterface $request, string $path): ResponseInterface
    {
        // Extract route parameters
        $params = $route->extractParameters($path);

        // Add parameters to request attributes
        foreach ($params as $key => $value) {
            $request = $request->withAttribute($key, $value);
        }

        // Add route and router info to request
        $request = $request->withAttribute('_route', $route);
        $request = $request->withAttribute('_router', $this);

        $this->currentRequest = $request;
        setCurrentRequest($request);

        // Execute middleware chain or handler directly
        if (!empty($route->getMiddleware())) {
            return $this->runMiddleware($route, $request);
        }

        return $this->executeHandler($route->getHandler(), $request);
    }

    /**
     * Run middleware chain for route
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
     * Resolve middleware from alias or class name
     */
    private function resolveMiddleware(string $middleware): object
    {
        // Load middleware aliases on first use
        if ($this->middlewareAliases === null) {
            $config = function_exists('config') ? config('middleware') : [];
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
     * Execute route handler
     */
    private function executeHandler($handler, ServerRequestInterface $request): ResponseInterface
    {
        $response = null;

        if (is_string($handler) && strpos($handler, '@') !== false) {
            $response = $this->executeControllerAction($handler, $request);
        } elseif (is_callable($handler)) {
            $response = $this->invokeCallable($handler, $request);
        } else {
            throw new RuntimeException('Invalid route handler type');
        }

        return $this->normalizeResponse($response);
    }

    /**
     * Invoke callable with enhanced dependency injection
     */
    private function invokeCallable(callable $handler, ServerRequestInterface $request)
    {
        // Try to resolve dependencies if handler is a closure or invokable
        if ($handler instanceof \Closure) {
            $reflection = new \ReflectionFunction($handler);
            $parameters = $this->resolveMethodParameters($reflection, $request);
            return $handler(...$parameters);
        }

        // Handle array callables [Class, 'method'] or [$instance, 'method']
        if (is_array($handler) && count($handler) === 2) {
            $reflection = new \ReflectionMethod($handler[0], $handler[1]);
            $parameters = $this->resolveMethodParameters($reflection, $request);
            return $handler(...$parameters);
        }

        // Fallback to direct call
        return $handler($request);
    }

    /**
     * Execute controller action with enhanced dependency injection
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

        // Resolve method parameters with enhanced dependency injection
        $reflection = new \ReflectionMethod($instance, $method);
        $parameters = $this->resolveMethodParameters($reflection, $request);

        return $instance->$method(...$parameters);
    }

    /**
     * Resolve method parameters with enhanced dependency injection
     *
     * This method supports:
     * - ServerRequestInterface in any position
     * - ResponseInterface (creates empty response)
     * - Route parameters by name
     * - Container-resolved dependencies
     * - Default values
     */
    private function resolveMethodParameters(
        \ReflectionFunctionAbstract $reflection,
        ServerRequestInterface $request
    ): array {
        $parameters = [];
        $container = Container::getInstance();
        $routeParams = $this->extractRouteParameters($request);

        foreach ($reflection->getParameters() as $parameter) {
            $resolved = $this->resolveParameter($parameter, $request, $routeParams, $container);

            if ($resolved !== null) {
                $parameters[] = $resolved;
                continue;
            }

            // Parameter cannot be resolved
            throw new RuntimeException(
                "Cannot resolve parameter [{$parameter->getName()}] for handler"
            );
        }

        return $parameters;
    }

    /**
     * Resolve a single parameter
     */
    private function resolveParameter(
        ReflectionParameter $parameter,
        ServerRequestInterface $request,
        array $routeParams,
        Container $container
    ) {
        $paramName = $parameter->getName();
        $type = $parameter->getType();

        // Handle typed parameters
        if ($type && !$type->isBuiltin()) {
            $typeName = $type->getName();

            // Inject ServerRequestInterface
            if (
                $typeName === ServerRequestInterface::class ||
                is_subclass_of($typeName, ServerRequestInterface::class)
            ) {
                return $request;
            }

            // Inject ResponseInterface (empty response)
            if (
                $typeName === ResponseInterface::class ||
                is_subclass_of($typeName, ResponseInterface::class)
            ) {
                return ResponseFactory::createResponse();
            }

            // Try to resolve from container
            try {
                return $container->make($typeName);
            } catch (\Exception $e) {
                // Fall through to other resolution methods
            }
        }

        // Check for route parameter match by name
        if (isset($routeParams[$paramName])) {
            return $this->castParameterValue($routeParams[$paramName], $type);
        }

        // Check request attributes (includes route params)
        $attrValue = $request->getAttribute($paramName);
        if ($attrValue !== null) {
            return $this->castParameterValue($attrValue, $type);
        }

        // Use default value if available
        if ($parameter->isDefaultValueAvailable()) {
            return $parameter->getDefaultValue();
        }

        // Allow nullable parameters
        if ($type && $type->allowsNull()) {
            return null;
        }

        return null;
    }

    /**
     * Cast parameter value to appropriate type
     */
    private function castParameterValue($value, ?\ReflectionType $type)
    {
        if ($type === null || !$type instanceof \ReflectionNamedType) {
            return $value;
        }

        if ($type->isBuiltin()) {
            $typeName = $type->getName();

            switch ($typeName) {
                case 'int':
                    return (int) $value;
                case 'float':
                    return (float) $value;
                case 'bool':
                    return filter_var($value, FILTER_VALIDATE_BOOLEAN);
                case 'string':
                    return (string) $value;
                case 'array':
                    return is_array($value) ? $value : [$value];
            }
        }

        return $value;
    }

    /**
     * Extract route parameters from request
     */
    private function extractRouteParameters(ServerRequestInterface $request): array
    {
        $params = [];
        $route = $request->getAttribute('_route');

        if ($route instanceof Route) {
            $path = $request->getUri()->getPath();
            $params = $route->extractParameters($path);
        }

        return $params;
    }

    /**
     * Normalize response to PSR-7 ResponseInterface
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

        // Boolean response
        if (is_bool($response)) {
            return ResponseFactory::json(['success' => $response]);
        }

        // Numeric response
        if (is_numeric($response)) {
            return ResponseFactory::html((string) $response);
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
     * Cache a route match
     */
    private function cacheRoute(string $key, Route $route): void
    {
        // Prevent cache from growing too large
        if (count($this->routeCache) >= self::MAX_CACHE_SIZE) {
            // Remove oldest entry (FIFO)
            array_shift($this->routeCache);
        }

        $this->routeCache[$key] = $route;
    }

    /**
     * Clear route cache
     */
    public function clearCache(): void
    {
        $this->routeCache = [];
    }

    /**
     * Enable or disable route caching
     */
    public function setCacheEnabled(bool $enabled): void
    {
        $this->cacheEnabled = $enabled;

        if (!$enabled) {
            $this->clearCache();
        }
    }

    /**
     * Get all registered routes
     */
    public function getRoutes(): array
    {
        return $this->routes;
    }

    /**
     * Get all named routes
     */
    public function getNamedRoutes(): array
    {
        return $this->namedRoutes;
    }

    /**
     * Check if a named route exists
     */
    public function hasRoute(string $name): bool
    {
        return isset($this->namedRoutes[$name]);
    }

    /**
     * Get routes by method
     */
    public function getRoutesByMethod(string $method): array
    {
        $method = strtoupper($method);
        return array_filter($this->routes, fn($route) => $route->getMethod() === $method);
    }

    /**
     * Get route count
     */
    public function count(): int
    {
        return count($this->routes);
    }

    /**
     * Clear all registered routes (useful for testing)
     */
    public function clear(): void
    {
        $this->routes = [];
        $this->namedRoutes = [];
        $this->groupPrefix = '';
        $this->groupMiddleware = [];
        $this->groupNamespace = '';
        $this->clearCache();
    }

    /**
     * View route helper
     */
    public function view(string $path, string $view, array $data = []): Route
    {
        return $this->get($path, function () use ($view, $data) {
            if (function_exists('view')) {
                return view($view, $data);
            }
            return ResponseFactory::html("View: {$view}");
        });
    }
}
