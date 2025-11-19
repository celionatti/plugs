<?php

declare(strict_types=1);

namespace Plugs\Router;

/*
|--------------------------------------------------------------------------
| Router Class
|--------------------------------------------------------------------------
|
| Router class for handling application routing.
 * Production-ready router with features including:
 * - Method spoofing (_method support)
 * - Route groups with prefixes, middleware, namespaces
 * - RESTful resources
 * - Named routes
 * - Advanced dependency injection
 * - Route caching
*/

use Plugs\Container\Container;
use ReflectionMethod;

use ReflectionParameter;

use Plugs\Http\MiddlewareDispatcher;
use Plugs\Http\ResponseFactory;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use RuntimeException;
use InvalidArgumentException;

class Router
{
    private array $routes = [];
    private string $groupPrefix = '';
    private array $groupMiddleware = [];
    private string $groupNamespace = '';
    private ?string $groupDomain = null;
    private array $groupWhere = [];
    private array $namedRoutes = [];
    private ?array $middlewareAliases = null;
    private array $routeCache = [];
    private bool $cacheEnabled = true;
    private const MAX_CACHE_SIZE = 1000;
    private ?ServerRequestInterface $currentRequest = null;
    private array $globalMiddleware = [];
    private ?Route $currentRoute = null;
    private array $patterns = [];

    /**
     * Register route methods
     */
    public function get(string $path, $handler, array $middleware = []): Route
    {
        return $this->addRoute('GET', $path, $handler, $middleware);
    }

    public function post(string $path, $handler, array $middleware = []): Route
    {
        return $this->addRoute('POST', $path, $handler, $middleware);
    }

    public function put(string $path, $handler, array $middleware = []): Route
    {
        return $this->addRoute('PUT', $path, $handler, $middleware);
    }

    public function delete(string $path, $handler, array $middleware = []): Route
    {
        return $this->addRoute('DELETE', $path, $handler, $middleware);
    }

    public function patch(string $path, $handler, array $middleware = []): Route
    {
        return $this->addRoute('PATCH', $path, $handler, $middleware);
    }

    public function options(string $path, $handler, array $middleware = []): Route
    {
        return $this->addRoute('OPTIONS', $path, $handler, $middleware);
    }

    public function head(string $path, $handler, array $middleware = []): Route
    {
        return $this->addRoute('HEAD', $path, $handler, $middleware);
    }

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
     * RESTful resource routing
     */
    public function resource(string $name, string $controller, array $options = []): void
    {
        $only = $options['only'] ?? ['index', 'create', 'store', 'show', 'edit', 'update', 'destroy'];
        $except = $options['except'] ?? [];
        $middleware = $options['middleware'] ?? [];
        $names = $options['names'] ?? [];
        $parameters = $options['parameters'] ?? ['id'];

        $actions = array_diff($only, $except);

        $param = is_array($parameters) ? ($parameters[0] ?? 'id') : $parameters;

        $resourceRoutes = [
            'index' => ['GET', '', 'index'],
            'create' => ['GET', '/create', 'create'],
            'store' => ['POST', '', 'store'],
            'show' => ['GET', '/{' . $param . '}', 'show'],
            'edit' => ['GET', '/{' . $param . '}/edit', 'edit'],
            'update' => ['PUT', '/{' . $param . '}', 'update'],
            'destroy' => ['DELETE', '/{' . $param . '}', 'destroy'],
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
                )->name($names[$action] ?? "{$name}.{$action}");
            }

            $route->name($names[$action] ?? "{$name}.{$action}");
        }
    }

    public function apiResource(string $name, string $controller, array $options = []): void
    {
        $options['except'] = array_merge($options['except'] ?? [], ['create', 'edit']);
        $this->resource($name, $controller, $options);
    }

    /**
     * Route grouping
     */
    public function group(array $attributes, callable $callback): void
    {
        $previousPrefix = $this->groupPrefix;
        $previousMiddleware = $this->groupMiddleware;
        $previousNamespace = $this->groupNamespace;
        $previousDomain = $this->groupDomain;
        $previousWhere = $this->groupWhere;

        // Apply prefix
        if (isset($attributes['prefix'])) {
            $prefix = trim($attributes['prefix'], '/');
            $this->groupPrefix = $previousPrefix . ($prefix ? '/' . $prefix : '');
        }

        // Apply middleware
        if (isset($attributes['middleware'])) {
            $middleware = is_array($attributes['middleware'])
                ? $attributes['middleware']
                : [$attributes['middleware']];
            $this->groupMiddleware = array_merge($previousMiddleware, $middleware);
        }

        // Apply namespace
        if (isset($attributes['namespace'])) {
            $this->groupNamespace = $previousNamespace
                ? $previousNamespace . '\\' . trim($attributes['namespace'], '\\')
                : trim($attributes['namespace'], '\\');
        }

        // Apply domain
        if (isset($attributes['domain'])) {
            $this->groupDomain = $attributes['domain'];
        }

        // Apply where constraints
        if (isset($attributes['where'])) {
            $this->groupWhere = array_merge($previousWhere, $attributes['where']);
        }

        // Execute callback
        $callback($this);

        // Restore previous state
        $this->groupPrefix = $previousPrefix;
        $this->groupMiddleware = $previousMiddleware;
        $this->groupNamespace = $previousNamespace;
        $this->groupDomain = $previousDomain;
        $this->groupWhere = $previousWhere;
    }

    /**
     * Add route to collection
     */
    private function addRoute(string $method, string $path, $handler, array $middleware = []): Route
    {
        // Normalize path
        $path = '/' . trim($this->groupPrefix . '/' . trim($path, '/'), '/');
        if ($path !== '/' && str_ends_with($path, '/')) {
            $path = rtrim($path, '/');
        }

        // Merge middleware
        $middleware = array_merge($this->groupMiddleware, $middleware);

        // Apply namespace
        $handler = $this->normalizeHandler($handler);

        $route = new Route($method, $path, $handler, $middleware, $this);

        // Apply group domain
        if ($this->groupDomain !== null) {
            $route->domain($this->groupDomain);
        }

        // Apply group where constraints
        if (!empty($this->groupWhere)) {
            $route->where($this->groupWhere);
        }

        $this->routes[] = $route;
        $this->clearCache();

        return $route;
    }

    /**
     * Normalize handler format
     */
    private function normalizeHandler($handler)
    {
        // Convert array [Controller::class, 'method'] to string
        if (is_array($handler) && count($handler) === 2) {
            if (is_string($handler[0]) && is_string($handler[1])) {
                $handler = $handler[0] . '@' . $handler[1];
            } elseif (is_object($handler[0]) && is_string($handler[1])) {
                return $handler;
            }
        }

        // Apply namespace
        if (is_string($handler) && strpos($handler, '@') !== false && $this->groupNamespace) {
            [$controller, $method] = explode('@', $handler, 2);

            if (strpos($controller, '\\') === false) {
                $handler = $this->groupNamespace . '\\' . $controller . '@' . $method;
            }
        }

        return $handler;
    }

    /**
     * Named routes
     */
    public function getRouteByName(string $name): ?Route
    {
        return $this->namedRoutes[$name] ?? null;
    }

    public function registerNamedRoute(string $name, Route $route): void
    {
        if (isset($this->namedRoutes[$name])) {
            throw new RuntimeException("Route name [{$name}] already exists");
        }

        $this->namedRoutes[$name] = $route;
    }

    public function route(string $name, array $parameters = [], bool $absolute = false): string
    {
        $route = $this->getRouteByName($name);

        if ($route === null) {
            throw new RuntimeException("Route [{$name}] not found");
        }

        return $route->url($parameters, $absolute);
    }

    /**
     * Current request/route access
     */
    public function getCurrentRequest(): ?ServerRequestInterface
    {
        return $this->currentRequest;
    }

    public function getCurrentRoute(): ?Route
    {
        return $this->currentRoute;
    }

    /**
     * Dispatch request to matching route
     * INCLUDES METHOD SPOOFING SUPPORT
     */
    public function dispatch(ServerRequestInterface $request): ?ResponseInterface
    {
        $this->currentRequest = $request;
        setCurrentRequest($request);

        $method = $request->getMethod();
        $path = $request->getUri()->getPath();

        // FIX: Support Laravel-style method spoofing via _method field
        if ($method === 'POST') {
            $method = $this->getMethodFromRequest($request);
        }

        // Handle HEAD as GET
        if ($method === 'HEAD') {
            $method = 'GET';
        }

        // Check cache
        $cacheKey = $method . ':' . $path;
        if ($this->cacheEnabled && isset($this->routeCache[$cacheKey])) {
            return $this->dispatchCachedRoute($this->routeCache[$cacheKey], $request, $method);
        }

        // Find matching route
        foreach ($this->routes as $route) {
            if (!$route->matches($method, $path)) {
                continue;
            }

            // Cache match
            if ($this->cacheEnabled) {
                $this->cacheRoute($cacheKey, $route);
            }

            return $this->dispatchRoute($route, $request, $path);
        }

        return null;
    }

    /**
     * Get method from request supporting _method spoofing
     */
    private function getMethodFromRequest(ServerRequestInterface $request): string
    {
        $method = $request->getMethod();

        // Only allow method spoofing for POST requests
        if ($method !== 'POST') {
            return $method;
        }

        // Check parsed body first
        $parsedBody = $request->getParsedBody();
        if (is_array($parsedBody) && isset($parsedBody['_method'])) {
            $spoofedMethod = strtoupper($parsedBody['_method']);

            // Only allow safe methods to be spoofed
            if (in_array($spoofedMethod, ['PUT', 'PATCH', 'DELETE'], true)) {
                return $spoofedMethod;
            }
        }

        // Check query params as fallback
        $queryParams = $request->getQueryParams();
        if (isset($queryParams['_method'])) {
            $spoofedMethod = strtoupper($queryParams['_method']);

            if (in_array($spoofedMethod, ['PUT', 'PATCH', 'DELETE'], true)) {
                return $spoofedMethod;
            }
        }

        // Check X-HTTP-Method-Override header
        $headerMethod = $request->getHeaderLine('X-HTTP-Method-Override');
        if ($headerMethod) {
            $spoofedMethod = strtoupper($headerMethod);

            if (in_array($spoofedMethod, ['PUT', 'PATCH', 'DELETE'], true)) {
                return $spoofedMethod;
            }
        }

        return $method;
    }

    /**
     * Dispatch cached route
     */
    private function dispatchCachedRoute(Route $route, ServerRequestInterface $request, string $method): ResponseInterface
    {
        $path = $request->getUri()->getPath();
        return $this->dispatchRoute($route, $request, $path);
    }

    /**
     * Dispatch matched route
     */
    private function dispatchRoute(Route $route, ServerRequestInterface $request, string $path): ResponseInterface
    {
        $this->currentRoute = $route;

        // Extract parameters
        $params = $route->extractParameters($path);

        // Add to request attributes
        foreach ($params as $key => $value) {
            $request = $request->withAttribute($key, $value);
        }

        $request = $request->withAttribute('_route', $route);
        $request = $request->withAttribute('_router', $this);

        $this->currentRequest = $request;
        setCurrentRequest($request);

        // Merge global and route middleware
        $allMiddleware = array_merge($this->globalMiddleware, $route->getMiddleware());

        if (!empty($allMiddleware)) {
            return $this->runMiddleware($route, $request, $allMiddleware);
        }

        return $this->executeHandler($route->getHandler(), $request);
    }

    /**
     * Run middleware chain
     */
    private function runMiddleware(Route $route, ServerRequestInterface $request, array $middleware): ResponseInterface
    {
        $handler = $route->getHandler();

        $stack = new MiddlewareDispatcher();

        foreach ($middleware as $mw) {
            if (is_string($mw)) {
                $mw = $this->resolveMiddleware($mw);
            }
            $stack->add($mw);
        }

        $stack->setFallbackHandler(function ($request) use ($handler) {
            return $this->executeHandler($handler, $request);
        });

        return $stack->handle($request);
    }

    /**
     * Resolve middleware from alias or class
     */
    private function resolveMiddleware(string $middleware): object
    {
        if ($this->middlewareAliases === null) {
            $config = function_exists('config') ? config('middleware') : [];
            $this->middlewareAliases = is_array($config) ? ($config['aliases'] ?? []) : [];
        }

        if (isset($this->middlewareAliases[$middleware])) {
            $middleware = $this->middlewareAliases[$middleware];
        }

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
     * Invoke callable with dependency injection
     */
    private function invokeCallable(callable $handler, ServerRequestInterface $request)
    {
        if ($handler instanceof \Closure) {
            $reflection = new \ReflectionFunction($handler);
            $parameters = $this->resolveMethodParameters($reflection, $request);
            return $handler(...$parameters);
        }

        if (is_array($handler) && count($handler) === 2) {
            $reflection = new ReflectionMethod($handler[0], $handler[1]);
            $parameters = $this->resolveMethodParameters($reflection, $request);
            return $handler(...$parameters);
        }

        return $handler($request);
    }

    /**
     * Execute controller action
     */
    private function executeControllerAction(string $handler, ServerRequestInterface $request)
    {
        [$controller, $method] = explode('@', $handler, 2);

        if (!class_exists($controller)) {
            throw new RuntimeException("Controller [{$controller}] not found");
        }

        $container = Container::getInstance();
        $instance = $container->make($controller);

        if (!method_exists($instance, $method)) {
            throw new RuntimeException(
                "Method [{$method}] not found in controller [{$controller}]"
            );
        }

        $reflection = new ReflectionMethod($instance, $method);
        $parameters = $this->resolveMethodParameters($reflection, $request);

        return $instance->$method(...$parameters);
    }

    /**
     * Resolve method parameters with dependency injection
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

            throw new RuntimeException(
                "Cannot resolve parameter [{$parameter->getName()}] for handler"
            );
        }

        return $parameters;
    }

    /**
     * Resolve single parameter
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

            if (
                $typeName === ServerRequestInterface::class ||
                is_subclass_of($typeName, ServerRequestInterface::class)
            ) {
                return $request;
            }

            if (
                $typeName === ResponseInterface::class ||
                is_subclass_of($typeName, ResponseInterface::class)
            ) {
                return ResponseFactory::createResponse();
            }

            try {
                return $container->make($typeName);
            } catch (\Exception $e) {
                // Fall through
            }
        }

        // Check route parameters
        if (isset($routeParams[$paramName])) {
            return $this->castParameterValue($routeParams[$paramName], $type);
        }

        // Check request attributes
        $attrValue = $request->getAttribute($paramName);
        if ($attrValue !== null) {
            return $this->castParameterValue($attrValue, $type);
        }

        // Default value
        if ($parameter->isDefaultValueAvailable()) {
            return $parameter->getDefaultValue();
        }

        // Nullable
        if ($type && $type->allowsNull()) {
            return null;
        }

        return null;
    }

    /**
     * Cast parameter value to type
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
     * Extract route parameters
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
     * Normalize response to PSR-7
     */
    private function normalizeResponse($response): ResponseInterface
    {
        if ($response instanceof ResponseInterface) {
            return $response;
        }

        if (is_string($response)) {
            return ResponseFactory::html($response);
        }

        if (is_array($response)) {
            return ResponseFactory::json($response);
        }

        if ($response === null) {
            return ResponseFactory::html('', 204);
        }

        if (is_bool($response)) {
            return ResponseFactory::json(['success' => $response]);
        }

        if (is_numeric($response)) {
            return ResponseFactory::html((string) $response);
        }

        if (is_object($response) && method_exists($response, '__toString')) {
            return ResponseFactory::html((string) $response);
        }

        throw new RuntimeException(
            'Route handler must return ResponseInterface, string, array, or null. Got: '
                . gettype($response)
        );
    }

    /**
     * Cache management
     */
    private function cacheRoute(string $key, Route $route): void
    {
        if (count($this->routeCache) >= self::MAX_CACHE_SIZE) {
            array_shift($this->routeCache);
        }

        $this->routeCache[$key] = $route;
    }

    public function clearCache(): void
    {
        $this->routeCache = [];
    }

    public function setCacheEnabled(bool $enabled): void
    {
        $this->cacheEnabled = $enabled;

        if (!$enabled) {
            $this->clearCache();
        }
    }

    /**
     * Global middleware
     */
    public function middleware($middleware): self
    {
        if (is_array($middleware)) {
            $this->globalMiddleware = array_merge($this->globalMiddleware, $middleware);
        } else {
            $this->globalMiddleware[] = $middleware;
        }

        return $this;
    }

    public function getGlobalMiddleware(): array
    {
        return $this->globalMiddleware;
    }

    /**
     * Pattern registration for reuse
     */
    public function pattern(string $key, string $pattern): void
    {
        $this->patterns[$key] = $pattern;
    }

    public function getPatterns(): array
    {
        return $this->patterns;
    }

    /**
     * Utility methods
     */
    public function getRoutes(): array
    {
        return $this->routes;
    }

    public function getNamedRoutes(): array
    {
        return $this->namedRoutes;
    }

    public function hasRoute(string $name): bool
    {
        return isset($this->namedRoutes[$name]);
    }

    public function getRoutesByMethod(string $method): array
    {
        $method = strtoupper($method);
        return array_filter($this->routes, fn($route) => $route->getMethod() === $method);
    }

    public function count(): int
    {
        return count($this->routes);
    }

    public function clear(): void
    {
        $this->routes = [];
        $this->namedRoutes = [];
        $this->groupPrefix = '';
        $this->groupMiddleware = [];
        $this->groupNamespace = '';
        $this->groupDomain = null;
        $this->groupWhere = [];
        $this->globalMiddleware = [];
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

    /**
     * Redirect route helper
     */
    public function redirect(string $from, string $to, int $status = 302): Route
    {
        return $this->any($from, function () use ($to, $status) {
            return ResponseFactory::redirect($to, $status);
        });
    }

    public function permanentRedirect(string $from, string $to): Route
    {
        return $this->redirect($from, $to, 301);
    }

    /**
     * Fallback route (404 handler)
     */
    public function fallback($handler): Route
    {
        return $this->any('{fallback}', $handler)
            ->where('fallback', '.*')
            ->name('fallback');
    }

    /**
     * Route list for debugging
     */
    public function getRouteList(): array
    {
        $list = [];

        foreach ($this->routes as $route) {
            $list[] = [
                'method' => $route->getMethod(),
                'path' => $route->getPath(),
                'name' => $route->getName(),
                'middleware' => $route->getMiddleware(),
                'handler' => $this->formatHandler($route->getHandler()),
            ];
        }

        return $list;
    }

    private function formatHandler($handler): string
    {
        if (is_string($handler)) {
            return $handler;
        }

        if (is_array($handler) && count($handler) === 2) {
            if (is_object($handler[0])) {
                return get_class($handler[0]) . '@' . $handler[1];
            }
            return $handler[0] . '@' . $handler[1];
        }

        if ($handler instanceof \Closure) {
            return 'Closure';
        }

        return 'Callable';
    }

    /**
     * Check if method spoofing is supported
     */
    public function isMethodSpoofingSupported(): bool
    {
        return true;
    }

    /**
     * Macro support - Add custom methods to router
     */
    private array $macros = [];

    public function macro(string $name, callable $callback): void
    {
        $this->macros[$name] = $callback;
    }

    public function hasMacro(string $name): bool
    {
        return isset($this->macros[$name]);
    }

    public function __call(string $name, array $arguments)
    {
        if (!$this->hasMacro($name)) {
            throw new RuntimeException("Method [{$name}] does not exist on Router");
        }

        $macro = $this->macros[$name];

        if ($macro instanceof \Closure) {
            return call_user_func_array($macro->bindTo($this, static::class), $arguments);
        }

        return call_user_func_array($macro, $arguments);
    }
}
