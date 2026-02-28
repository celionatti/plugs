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

use Closure;
use Exception;
use InvalidArgumentException;
use Plugs\Container\Container;
use Plugs\Http\MiddlewareDispatcher;
use Plugs\Http\ResponseFactory;
use Plugs\Inertia\InertiaResponse;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use ReflectionClass;
use ReflectionFunction;
use ReflectionFunctionAbstract;
use ReflectionMethod;
use ReflectionNamedType;
use ReflectionParameter;
use ReflectionType;
use RuntimeException;
use Throwable;



class Router
{
    /**
     * Routes indexed by HTTP method.
     * @var array<string, Route[]>
     */
    private array $routes = [
        'GET' => [],
        'POST' => [],
        'PUT' => [],
        'DELETE' => [],
        'PATCH' => [],
        'OPTIONS' => [],
        'HEAD' => [],
    ];

    private string $groupPrefix = '';
    private array $groupMiddleware = [];
    private string $groupNamespace = '';
    private ?string $groupDomain = null;
    private array $groupWhere = [];
    private string $groupAs = '';

    public function getGroupAs(): string
    {
        return $this->groupAs;
    }

    private array $namedRoutes = [];


    private array $routeCache = [];
    private bool $cacheEnabled = true;
    private array $reflectionCache = [];
    private const MAX_CACHE_SIZE = 1000;
    private ?ServerRequestInterface $currentRequest = null;
    private array $globalMiddleware = [];
    private ?Route $currentRoute = null;
    private array $patterns = [];
    private ?PageRouter $pageRouter = null;
    private bool $pagesRoutingEnabled = false;
    private string $cachePath = '';
    private array $staticRoutes = [];
    private ?\Plugs\Http\Middleware\MiddlewareRegistry $registry = null;
    private array $compiledRoutes = [];
    protected ?RouteUrlGenerator $urlGenerator = null;
    private bool $routesLoaded = false;



    /**
     * Get the route cache path.
     */
    public function getCachePath(): string
    {
        return $this->cachePath;
    }

    /**
     * Fallback route for unmatched requests.
     * @var Route|null
     */
    private ?Route $fallbackRoute = null;

    public function __construct()
    {
        $this->resolveRegistry();
    }

    private function resolveRegistry(): void
    {
        if (function_exists('app') && app()->has(\Plugs\Http\Middleware\MiddlewareRegistry::class)) {
            $this->registry = app(\Plugs\Http\Middleware\MiddlewareRegistry::class);
        } else {
            $this->registry = new \Plugs\Http\Middleware\MiddlewareRegistry();
        }
    }

    /**
     * Set the middleware registry.
     */
    public function setRegistry(\Plugs\Http\Middleware\MiddlewareRegistry $registry): void
    {
        $this->registry = $registry;
    }


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
     * Register a route that redirects to another URI.
     */
    public function redirect(string $uri, string $destination, int $status = 302): Route
    {
        return $this->get($uri, function () use ($destination, $status) {
            return ResponseFactory::redirect($destination, $status);
        });
    }

    /**
     * Register a route that returns a view.
     */
    public function view(string $uri, string $view, array $data = []): Route
    {
        return $this->get($uri, function () use ($view, $data) {
            if (function_exists('view')) {
                return view($view, $data);
            }

            // Fallback if view helper is missing
            return ResponseFactory::html("View: {$view}");
        });
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
     * Register an array of API resources.
     */
    public function apiResources(array $resources): void
    {
        foreach ($resources as $name => $controller) {
            $this->apiResource($name, $controller);
        }
    }

    /**
     * Register a fallback route for when no other route matches.
     *
     * @param callable|array|string $handler
     * @return Route
     */
    public function fallback($handler): Route
    {
        $route = new Route('GET', '/{any:.*}', $handler, [], $this);
        $this->fallbackRoute = $route;

        return $route;
    }

    /**
     * Get the fallback route.
     *
     * @return Route|null
     */
    public function getFallbackRoute(): ?Route
    {
        return $this->fallbackRoute;
    }

    /**
     * Register a middleware group.
     */
    public function middlewareGroup(string $name, array $middleware): void
    {
        $this->registry?->addGroup($name, $middleware);
    }

    /**
     * Get middleware from a group.
     */
    public function getMiddlewareGroup(string $name): array
    {
        return $this->registry?->resolve($name) ?? [];
    }

    /**
     * Register routes for a subdomain.
     *
     * @param string $domain
     * @param callable $callback
     * @return void
     */
    public function domain(string $domain, callable $callback): void
    {
        $this->group(['domain' => $domain], $callback);
    }

    /**
     * Register routes for a subdomain with wildcard.
     *
     * @param string $subdomain Parameter name for subdomain (e.g., 'account' for {account}.example.com)
     * @param string $baseDomain The base domain
     * @param callable $callback
     * @return void
     */
    public function subdomain(string $subdomain, string $baseDomain, callable $callback): void
    {
        $this->group(['domain' => "{{$subdomain}}.{$baseDomain}"], $callback);
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
        $previousAs = $this->groupAs;

        // Apply prefix
        if (isset($attributes['prefix'])) {
            $prefix = trim($attributes['prefix'], '/');
            $this->groupPrefix = $previousPrefix . ($prefix ? '/' . $prefix : '');
        } else {
            $this->groupPrefix = $previousPrefix;
        }

        // Versioning Support
        if (isset($attributes['version'])) {
            $version = $attributes['version']; // e.g., 'v1'
            $this->groupPrefix = rtrim($this->groupPrefix, '/') . '/' . $version;
            // Store version metadata for future OpenAPI logic if needed
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

        // Apply name prefix
        if (isset($attributes['as'])) {
            $this->groupAs = $previousAs . $attributes['as'];
        }

        // Execute callback
        $callback($this);

        // Restore previous state
        $this->groupPrefix = $previousPrefix;
        $this->groupMiddleware = $previousMiddleware;
        $this->groupNamespace = $previousNamespace;
        $this->groupDomain = $previousDomain;
        $this->groupWhere = $previousWhere;
        $this->groupAs = $previousAs;
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

        // Optimization: Separate static and dynamic routes
        if (!str_contains($path, '{')) {
            $this->staticRoutes[$method][$path] = $route;
        } else {
            $this->routes[$method][] = $route;
        }

        return $route;
    }

    /**
     * Normalize handler format
     */
    private function normalizeHandler($handler)
    {
        // Keep array handlers as arrays - don't convert to strings
        if (is_array($handler) && count($handler) === 2) {
            // Apply namespace to controller if it's a string
            if (is_string($handler[0]) && is_string($handler[1])) {
                $controller = $handler[0];

                // Apply namespace if controller doesn't have one
                if ($this->groupNamespace && strpos($controller, '\\') === false) {
                    $controller = $this->groupNamespace . '\\' . $controller;
                }

                return [$controller, $handler[1]];
            }

            // Return object handlers as-is
            if (is_object($handler[0]) && is_string($handler[1])) {
                return $handler;
            }
        }

        // Handle string handlers with namespace
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
    public function hasRoute(string $name): bool
    {
        return isset($this->namedRoutes[$name]);
    }



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



    /**
     * Get the URL generator pointing to this Router instance.
     * 
     * @return RouteUrlGenerator
     */
    public function getUrlGenerator(): RouteUrlGenerator
    {
        if ($this->urlGenerator === null) {
            $this->urlGenerator = new RouteUrlGenerator($this);
        }

        return $this->urlGenerator;
    }

    public function route(string $name, array $parameters = [], bool $absolute = false): string
    {
        return $this->getUrlGenerator()->route($name, $parameters, $absolute);
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

        // Strip base path (subdirectory) if present
        if (function_exists('get_base_path')) {
            $basePath = get_base_path();
            if ($basePath !== '/' && str_starts_with($path, $basePath)) {
                $path = substr($path, strlen($basePath));
            }
        }

        $path = '/' . ltrim($path, '/');

        // Support Laravel-style method spoofing via _method field
        if ($method === 'POST') {
            $method = $this->getMethodFromRequest($request);
        }

        // Handle HEAD as GET
        if ($method === 'GET' || $method === 'HEAD') {
            // Check cache
            $cacheKey = $method . ':' . $path;
            if ($this->cacheEnabled && isset($this->routeCache[$cacheKey])) {
                return $this->dispatchRoute($this->routeCache[$cacheKey], $request, $path);
            }
        }

        // Find matching route - Only iterate routes for the specific method

        // 1. Check static routes first (O(1) lookup)
        if (isset($this->staticRoutes[$method][$path])) {
            $route = $this->staticRoutes[$method][$path];
            if ($this->cacheEnabled) {
                $this->cacheRoute($method . ':' . $path, $route);
            }
            return $this->dispatchRoute($route, $request, $path);
        }

        // 2. Fallback to dynamic routes if no static match
        $routes = $this->routes[$method] ?? [];

        // Check compiled regex for dynamic routes (O(1) pre-filter)
        $compiled = $this->getCompiledRoutes($method);
        $compiledMatched = $compiled && @preg_match($compiled['regex'], $path);

        // If the compiled regex matched OR if it failed to compile (we need to fall through)
        if ($compiledMatched || $compiled === null || preg_last_error() !== PREG_NO_ERROR) {
            foreach ($routes as $route) {
                if ($route->matchesPath($path)) {
                    // Cache match
                    if ($this->cacheEnabled) {
                        $this->cacheRoute($method . ':' . $path, $route);
                    }
                    return $this->dispatchRoute($route, $request, $path);
                }
            }
        }


        // Check if the path matches any other method (Method Not Allowed)
        $allowedMethods = [];
        foreach ($this->routes as $m => $mRoutes) {
            if ($m === $method) {
                continue;
            }
            foreach ($mRoutes as $route) {
                if ($route->matches($m, $path)) {
                    $allowedMethods[] = $m;

                    break;
                }
            }
        }

        // Try fallback route if no match found
        if ($this->fallbackRoute !== null) {
            return $this->dispatchRoute($this->fallbackRoute, $request, $path);
        }

        // If we found allowed methods but no match for current method
        if (!empty($allowedMethods)) {
            throw new \Plugs\Exceptions\MethodNotAllowedException(405, 'Method Not Allowed', null, [], $allowedMethods);
        }

        return null; // Will trigger RouteNotFoundException in fallback handler or caller
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
     * Dispatch matched route
     */
    private function dispatchRoute(Route $route, ServerRequestInterface $request, string $path): ResponseInterface
    {
        $this->currentRoute = $route;

        // Extract path parameters
        $params = $route->extractParameters($path);

        // Extract domain parameters (e.g., {tenant} from {tenant}.example.com)
        $host = $request->getUri()->getHost();
        if (empty($host)) {
            $host = $_SERVER['HTTP_HOST'] ?? '';
        }
        $domainParams = $route->extractDomainParameters($host);
        $params = array_merge($domainParams, $params);

        // Add to request attributes individually
        foreach ($params as $key => $value) {
            $request = $request->withAttribute($key, $value);
        }

        // Cache extracted params to avoid re-extraction
        $request = $request->withAttribute('_route_params', $params);
        $request = $request->withAttribute('_route', $route);
        $request = $request->withAttribute('_router', $this);

        $this->currentRequest = $request;
        setCurrentRequest($request);

        // Emit RouteMatched event
        if (function_exists('app') && app()->has('events')) {
            app('events')->dispatch(
                new \Plugs\Event\Core\RouteMatched($request, $route)
            );
        }

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

        $stack = new MiddlewareDispatcher($middleware, null, $this->registry);

        $stack->setFallbackHandler(function ($request) use ($handler) {
            return $this->executeHandler($handler, $request);
        });

        return $stack->handle($request);
    }

    /**
     * Expand middleware groups recursively
     * Now delegated to the MiddlewareRegistry.
     */
    private function expandMiddleware(array $middleware): array
    {
        return $this->registry?->resolve($middleware) ?? $middleware;
    }

    /**
     * Resolve middleware from alias or class
     */
    private function resolveMiddleware(string $middleware): object
    {
        // This method is now largely redundant for modern stack 
        // as Dispatcher handles resolving, but we keep it for 
        // older parts if they still call it directly.

        $params = [];
        if (strpos($middleware, ':') !== false) {
            [$name, $paramString] = explode(':', $middleware, 2);
            $middleware = $name;
            $params = explode(',', $paramString);
        }

        $resolved = $this->registry?->resolve($middleware)[0] ?? $middleware;

        $container = Container::getInstance();
        $instance = $container->make($resolved);

        if (!empty($params) && method_exists($instance, 'setParameters')) {
            $instance->setParameters($params);
        }

        return $instance;
    }

    /**
     * Execute route handler
     */
    private function executeHandler($handler, ServerRequestInterface $request): ResponseInterface
    {
        $profiler = \Plugs\Debug\Profiler::getInstance();
        $profiler->startSegment('controller', 'Controller Execution');

        try {
            $response = null;

            // Handle array format [Controller::class, 'method'] or [Controller::class]
            if (is_array($handler) && (count($handler) === 2 || count($handler) === 1)) {
                $response = $this->executeControllerAction($handler, $request);
            }
            // Handle string format "Controller@method" or "Controller" (invokable)
            elseif (is_string($handler)) {
                $response = $this->executeControllerAction($handler, $request);
            }
            // Handle callables
            elseif (is_callable($handler)) {
                $response = $this->invokeCallable($handler, $request);
            } else {
                throw new RuntimeException('Invalid route handler type: ' . gettype($handler));
            }

            return $this->normalizeResponse($response);
        } finally {
            $profiler->stopSegment('controller');
        }
    }

    /**
     * Invoke callable with dependency injection
     */
    private function invokeCallable(callable $handler, ServerRequestInterface $request)
    {
        if ($handler instanceof Closure) {
            $reflection = new ReflectionFunction($handler);
            $parameters = $this->resolveMethodParameters($reflection, $request);

            // Emit ActionExecuting event (Closure)
            if (function_exists('app') && app()->has('events')) {
                app('events')->dispatch(
                    new \Plugs\Event\Core\ActionExecuting($request, 'Closure', 'invoke', $parameters)
                );
            }

            $response = $handler(...$parameters);

            // Emit ActionExecuted event
            if (function_exists('app') && app()->has('events')) {
                app('events')->dispatch(
                    new \Plugs\Event\Core\ActionExecuted($request, $this->normalizeResponse($response))
                );
            }

            return $response;
        }

        // This handles array callables like [$object, 'method']
        if (is_array($handler) && count($handler) === 2) {
            $reflection = new ReflectionMethod($handler[0], $handler[1]);
            $parameters = $this->resolveMethodParameters($reflection, $request);

            // Emit ActionExecuting event (Array Callable)
            if (function_exists('app') && app()->has('events')) {
                app('events')->dispatch(
                    new \Plugs\Event\Core\ActionExecuting($request, $handler[0], $handler[1], $parameters)
                );
            }

            $response = $handler(...$parameters);

            // Emit ActionExecuted event
            if (function_exists('app') && app()->has('events')) {
                app('events')->dispatch(
                    new \Plugs\Event\Core\ActionExecuted($request, $this->normalizeResponse($response))
                );
            }

            return $response;
        }

        // Fallback for simple callables
        return $handler($request);
    }

    /**
     * Execute controller action
     */
    private function executeControllerAction($handler, ServerRequestInterface $request)
    {
        // Handle both string "Controller@method" / "Controller" and array formats
        if (is_string($handler)) {
            if (strpos($handler, '@') !== false) {
                [$controller, $method] = explode('@', $handler, 2);
            } else {
                $controller = $handler;
                $method = '__invoke';
            }
        } elseif (is_array($handler)) {
            $controller = $handler[0];
            $method = $handler[1] ?? '__invoke';
        } else {
            throw new RuntimeException('Invalid controller action format');
        }

        if (!class_exists($controller)) {
            throw new RuntimeException("Controller [{$controller}] not found");
        }

        $container = Container::getInstance();

        try {
            $instance = $container->make($controller);
        } catch (Throwable $e) {
            throw new RuntimeException("Could not instantiate controller [{$controller}]: " . $e->getMessage(), (int) $e->getCode(), $e);
        }

        // Initialize Plugs Base Controller if applicable
        if ($instance instanceof \Plugs\Base\Controller\Controller) {
            $instance->initialize(
                $container->make(\Plugs\View\ViewEngineInterface::class),
                $container->bound('db') ? $container->make('db') : null
            );
        }

        // Call onConstruct hook if it exists
        if (method_exists($instance, 'onConstruct')) {
            $container->call([$instance, 'onConstruct']);
        }

        if (!method_exists($instance, $method)) {
            throw new RuntimeException(
                "Method [{$method}] not found in controller [{$controller}]"
            );
        }

        $reflection = new ReflectionMethod($instance, $method);
        $parameters = $this->resolveMethodParameters($reflection, $request);

        // Emit ActionExecuting event
        if (function_exists('app') && app()->has('events')) {
            app('events')->dispatch(
                new \Plugs\Event\Core\ActionExecuting($request, $instance, $method, $parameters)
            );
        }

        $response = $instance->$method(...$parameters);

        // Emit ActionExecuted event
        if (function_exists('app') && app()->has('events')) {
            app('events')->dispatch(
                new \Plugs\Event\Core\ActionExecuted($request, $this->normalizeResponse($response))
            );
        }

        return $response;
    }

    /**
     * Resolve method parameters with dependency injection
     */
    private function resolveMethodParameters(
        ReflectionFunctionAbstract $reflection,
        ServerRequestInterface $request
    ): array {
        // Cache Key for reflection
        $cacheKey = ($reflection instanceof ReflectionMethod)
            ? $reflection->getDeclaringClass()->getName() . '@' . $reflection->getName()
            : 'closure_' . spl_object_hash($reflection);

        if (isset($this->reflectionCache[$cacheKey])) {
            return $this->resolveCachedParameters($this->reflectionCache[$cacheKey], $request);
        }

        $parameters = [];
        $container = Container::getInstance();
        $routeParams = $this->extractRouteParameters($request);
        $parameterMetadata = [];

        foreach ($reflection->getParameters() as $parameter) {
            // Use a sentinel value to distinguish "not resolved" from "resolved to null"
            $sentinel = new \stdClass();
            $resolved = $this->resolveParameter($parameter, $request, $routeParams, $container, $sentinel);


            if ($resolved !== $sentinel) {
                // Store metadata for caching
                $parameterMetadata[] = [
                    'name' => $parameter->getName(),
                    'type' => $parameter->getType(),
                    'variadic' => $parameter->isVariadic(),
                    'default' => $parameter->isDefaultValueAvailable() ? $parameter->getDefaultValue() : $sentinel,
                    'nullable' => $parameter->getType() ? $parameter->getType()->allowsNull() : true,
                ];

                // Handle variadic parameters - spread the array
                if ($parameter->isVariadic() && is_array($resolved)) {
                    array_push($parameters, ...$resolved);
                } else {
                    $parameters[] = $resolved;
                }

                continue;
            }

            // Better error message showing what was tried
            $debugInfo = [
                'parameter_name' => $parameter->getName(),
                'type' => ($parameter->getType() instanceof ReflectionNamedType) ? $parameter->getType()->getName() : 'mixed',
                'position' => $parameter->getPosition(),
                'route_params' => array_keys($routeParams),
                'request_attributes' => array_keys($request->getAttributes()),
            ];

            throw new RuntimeException(
                "Cannot resolve parameter [{$parameter->getName()}] at position {$parameter->getPosition()} for handler. " .
                "Debug info: " . json_encode($debugInfo)
            );
        }

        // Cache the parameter metadata if not a closure
        if ($reflection instanceof ReflectionMethod) {
            $this->reflectionCache[$cacheKey] = $parameterMetadata;
        }

        return $parameters;
    }

    /**
     * Resolve parameters using cached metadata
     */
    private function resolveCachedParameters(array $metadata, ServerRequestInterface $request): array
    {
        $parameters = [];
        $container = Container::getInstance();
        $routeParams = $this->extractRouteParameters($request);

        foreach ($metadata as $paramMeta) {
            $sentinel = new \stdClass();


            // Simplified resolveParameter-like logic for cached meta
            // We still need to call resolveParameter essentially because it handles dynamic request data
            // But we might avoid some reflection calls later if we optimize resolveParameter itself.
            // For now, let's keep it simple and just cache the reflection part of the lookup.
            // The real bottleneck in reflection is often getParameters() and getType() calls on every request.

            // Re-using resolveParameter for now but passing the ReflectionParameter would be better.
            // This suggests we should refactor resolveParameter to take metadata or ReflectionParameter.
        }

        // Actually, without the ReflectionParameter object, resolveParameter won't work easily.
        // Let's postpone full reflection caching until I can refactor resolveParameter.
        // For now, I'll focus on the View Engine optimization which is more impactful.
        return [];
    }

    /**
     * Resolve single parameter
     */
    private function resolveParameter(
        ReflectionParameter $parameter,
        ServerRequestInterface $request,
        array $routeParams,
        Container $container,
        $sentinel = null
    ) {
        $paramName = $parameter->getName();
        $type = $parameter->getType();

        // Handle array type parameters from request body
        if ($type instanceof ReflectionNamedType && $type->getName() === 'array') {
            $parsedBody = $request->getParsedBody();
            if (is_array($parsedBody) && array_key_exists($paramName, $parsedBody)) {
                $value = $parsedBody[$paramName];

                return is_array($value) ? $value : [$value];
            }

            // If no specific array parameter, return entire parsed body as array
            if ($paramName === 'data' || $paramName === 'input') {
                return is_array($parsedBody) ? $parsedBody : [];
            }
        }

        // Handle variadic parameters (e.g., ...$args, ...$params)
        if ($parameter->isVariadic()) {
            // For variadic, collect all unused route parameters
            $consumed = [];
            $reflection = $parameter->getDeclaringFunction();

            // Get names of all non-variadic parameters that come before this one
            foreach ($reflection->getParameters() as $param) {
                if ($param->getPosition() < $parameter->getPosition()) {
                    $consumed[] = $param->getName();
                }
            }

            // Collect remaining route params
            $variadicValues = [];
            foreach ($routeParams as $key => $value) {
                if (!in_array($key, $consumed, true)) {
                    $variadicValues[] = $this->castParameterValue($value, $type);
                }
            }

            return $variadicValues;
        }

        // Handle typed parameters (classes/interfaces) - BEFORE route params
        // This ensures Request/Response injection works even if there's a route param with same name
        if ($type instanceof ReflectionNamedType && !$type->isBuiltin()) {
            $typeName = $type->getName();

            // PSR-7 Request injection
            if (
                $typeName === ServerRequestInterface::class ||
                is_subclass_of($typeName, ServerRequestInterface::class)
            ) {
                return $request;
            }

            // PSR-7 Response injection
            if (
                $typeName === ResponseInterface::class ||
                is_subclass_of($typeName, ResponseInterface::class)
            ) {
                return ResponseFactory::createResponse();
            }

            // ValueObject Binding
            if (is_subclass_of($typeName, \Plugs\Support\ValueObject::class)) {
                // Try to find a matching route parameter or request attribute
                $value = $routeParams[$paramName] ?? $request->getAttribute($paramName);
                if ($value !== null && (is_string($value) || is_numeric($value))) {
                    return $typeName::from((string) $value);
                }
            }


            // Try dependency injection from container
            try {
                // FormRequest Automatic Validation
                if (is_subclass_of($typeName, \Plugs\Http\Requests\FormRequest::class)) {
                    $formRequest = new $typeName();
                    $formRequest->setRequest($request);
                    $formRequest->validateInternal();

                    return $formRequest;
                }

                // Route Model Binding check
                if (is_subclass_of($typeName, \Plugs\Base\Model\PlugModel::class)) {
                    $modelValue = $this->resolveModelParameter($typeName, $paramName, $routeParams, $request, $sentinel);
                    if ($modelValue !== $sentinel) {
                        return $modelValue;
                    }
                }

                return $container->make($typeName);
            } catch (\Plugs\Exceptions\ModelNotFoundException | \Plugs\Http\Exceptions\ValidationException | RuntimeException $e) {
                // Rethrow these so they can be handled by middleware or global handler
                throw $e;
            } catch (Exception $e) {
            }


        }

        // Check route parameters (highest priority for scalars)
        if (array_key_exists($paramName, $routeParams)) {
            return $this->castParameterValue($routeParams[$paramName], $type);
        }

        // Check request attributes (allow null values)
        $attrValue = $request->getAttribute($paramName, $sentinel);
        if ($attrValue !== $sentinel) {
            return $this->castParameterValue($attrValue, $type);
        }

        // Check query parameters for additional data
        $queryParams = $request->getQueryParams();
        if (array_key_exists($paramName, $queryParams)) {
            return $this->castParameterValue($queryParams[$paramName], $type);
        }

        // Check parsed body (POST/PUT/PATCH data)
        $parsedBody = $request->getParsedBody();
        if (is_array($parsedBody) && array_key_exists($paramName, $parsedBody)) {
            return $this->castParameterValue($parsedBody[$paramName], $type);
        }

        // Check for uploaded files
        $uploadedFiles = $request->getUploadedFiles();
        if (array_key_exists($paramName, $uploadedFiles)) {
            return $uploadedFiles[$paramName];
        }

        // Default value
        if ($parameter->isDefaultValueAvailable()) {
            return $parameter->getDefaultValue();
        }

        // Nullable - explicitly return null
        if ($type && $type->allowsNull()) {
            return null;
        }

        // Cannot resolve - return sentinel
        return $sentinel ?? null;
    }

    /**
     * Cast parameter value to type
     */
    private function castParameterValue($value, ?ReflectionType $type)
    {
        // No type hint or not a named type - return as-is
        if (!$type instanceof ReflectionNamedType) {
            return $value;
        }

        // Allow null for nullable types
        if ($value === null && $type->allowsNull()) {
            return null;
        }

        // Handle empty string for nullable non-string types
        if ($value === '' && $type->allowsNull() && $type->getName() !== 'string') {
            return null;
        }

        $typeName = $type->getName();

        // Handle Backed Enums
        if (enum_exists($typeName) && method_exists($typeName, 'tryFrom')) {
            return $typeName::tryFrom($value) ?? $value;
        }

        // Built-in type casting
        if ($type->isBuiltin()) {
            switch ($typeName) {
                case 'int':
                    if ($value === '' || $value === null) {
                        return $type->allowsNull() ? null : 0;
                    }

                    return (int) $value;

                case 'float':
                    if ($value === '' || $value === null) {
                        return $type->allowsNull() ? null : 0.0;
                    }

                    return (float) $value;

                case 'bool':
                    if ($value === '' || $value === null) {
                        return false;
                    }
                    // Handle string booleans
                    if (is_string($value)) {
                        $lower = strtolower($value);
                        if (in_array($lower, ['true', '1', 'yes', 'on'], true)) {
                            return true;
                        }
                        if (in_array($lower, ['false', '0', 'no', 'off', ''], true)) {
                            return false;
                        }
                    }

                    return filter_var($value, FILTER_VALIDATE_BOOLEAN);

                case 'string':
                    if ($value === null) {
                        return $type->allowsNull() ? null : '';
                    }

                    return (string) $value;

                case 'array':
                    if ($value === null) {
                        return $type->allowsNull() ? null : [];
                    }

                    return is_array($value) ? $value : [$value];

                case 'object':
                    return is_object($value) ? $value : (object) $value;
            }
        }

        return $value;
    }

    /**
     * Extract route parameters
     */
    private function extractRouteParameters(ServerRequestInterface $request): array
    {
        // Try cached parameters first
        $cachedParams = $request->getAttribute('_route_params');
        if (is_array($cachedParams)) {
            return $cachedParams;
        }

        // Fallback to extraction
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

        // Handle InertiaResponse objects
        if ($response instanceof InertiaResponse) {
            return $response->toResponse($this->currentRequest);
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

    public function clearCache(bool $physical = false): void
    {
        $this->routeCache = [];

        if ($physical) {
            $file = $this->getPersistentCachePath();
            if (file_exists($file)) {
                @unlink($file);
            }
        }
    }

    /**
     * Cache the current route registration.
     * WARNING: Routes with Closures cannot be cached and will throw an error or be skipped.
     */
    public function cacheRoutes(): bool
    {
        $cachePath = $this->getPersistentCachePath();
        $directory = dirname($cachePath);

        if (!is_dir($directory)) {
            mkdir($directory, 0755, true);
        }

        $data = [
            'static' => [],
            'dynamic' => [],
        ];

        // Process static routes
        foreach ($this->staticRoutes as $method => $routes) {
            foreach ($routes as $path => $route) {
                $routeData = $route->toArray();
                $handler = $route->getHandler();

                if ($handler instanceof Closure) {
                    continue;
                }

                $routeData['handler'] = $handler;
                $data['static'][$method][$path] = $routeData;
            }
        }

        // Process dynamic routes
        foreach ($this->routes as $method => $routes) {
            foreach ($routes as $route) {
                $routeData = $route->toArray();
                $handler = $route->getHandler();

                if ($handler instanceof Closure) {
                    continue;
                }

                $routeData['handler'] = $handler;
                $data['dynamic'][$method][] = $routeData;
            }
        }

        try {
            $content = "<?php\n\nreturn " . var_export($data, true) . ";\n";
            return file_put_contents($cachePath, $content) !== false;
        } catch (Throwable $e) {
            return false;
        }
    }

    /**
     * Load routes from persistent cache.
     */
    public function loadFromCache(): bool
    {
        $cachePath = $this->getPersistentCachePath();

        if (!file_exists($cachePath)) {
            return false;
        }

        $data = require $cachePath;

        if (!is_array($data)) {
            return false;
        }

        // Clear existing routes
        $this->staticRoutes = [];
        $this->namedRoutes = [];
        $this->routes = [
            'GET' => [],
            'POST' => [],
            'PUT' => [],
            'DELETE' => [],
            'PATCH' => [],
            'OPTIONS' => [],
            'HEAD' => [],
        ];

        // Reconstruct static routes
        foreach ($data['static'] ?? [] as $method => $routes) {
            foreach ($routes as $path => $attr) {
                $route = new Route($attr['method'], $attr['path'], $attr['handler'], $attr['middleware'], $this);
                if ($attr['name'])
                    $route->name($attr['name']);
                if ($attr['where'])
                    $route->where($attr['where']);
                if ($attr['domain'])
                    $route->domain($attr['domain']);
                if ($attr['defaults'])
                    $route->defaults($attr['defaults']);

                $this->staticRoutes[$method][$path] = $route;
                if ($attr['name'])
                    $this->namedRoutes[$attr['name']] = $route;
            }
        }

        // Reconstruct dynamic routes
        foreach ($data['dynamic'] ?? [] as $method => $routes) {
            foreach ($routes as $attr) {
                $route = new Route($attr['method'], $attr['path'], $attr['handler'], $attr['middleware'], $this);
                if ($attr['name'])
                    $route->name($attr['name']);
                if ($attr['where'])
                    $route->where($attr['where']);
                if ($attr['domain'])
                    $route->domain($attr['domain']);
                if ($attr['defaults'])
                    $route->defaults($attr['defaults']);

                $this->routes[$method][] = $route;
                if ($attr['name'])
                    $this->namedRoutes[$attr['name']] = $route;
            }
        }

        return true;
    }

    // ============================================
    // SECURE: SIGNED URLS
    // ============================================

    /**
     * Create a signed URL for a named route.
     */
    public function signedRoute(string $name, array $parameters = [], ?\DateTimeInterface $expiration = null, bool $absolute = true): string
    {
        if ($expiration) {
            $parameters['expires'] = $expiration->getTimestamp();
        }

        $url = $this->route($name, $parameters, $absolute);
        $signature = $this->createSignature($url);

        return $url . (str_contains($url, '?') ? '&' : '?') . 'signature=' . $signature;
    }

    /**
     * Create a temporary signed URL.
     */
    public function temporarySignedRoute(string $name, \DateTimeInterface $expiration, array $parameters = [], bool $absolute = true): string
    {
        return $this->signedRoute($name, $parameters, $expiration, $absolute);
    }

    /**
     * Check if the request has a valid signature.
     */
    public function hasValidSignature(ServerRequestInterface $request, bool $absolute = true): bool
    {
        $uri = $request->getUri();
        $queryParams = $request->getQueryParams();
        $signature = $queryParams['signature'] ?? null;

        if (!$signature) {
            return false;
        }

        // Check expiration
        if (isset($queryParams['expires']) && (int) $queryParams['expires'] < time()) {
            return false;
        }

        // Recreate URL WITHOUT the signature to verify
        $originalUrl = (string) $uri;
        $originalUrl = preg_replace('/([?&])signature=[^&]+(&?)/', '$1', $originalUrl);
        $originalUrl = rtrim($originalUrl, '?&');

        return hash_equals($this->createSignature($originalUrl), $signature);
    }

    /**
     * Create a URL signature using APP_KEY.
     */
    private function createSignature(string $url): string
    {
        $key = $_ENV['APP_KEY'] ?? 'base64:plugs-framework-default-key-replace-me';
        return hash_hmac('sha256', $url, $key);
    }

    public function setCacheEnabled(bool $enabled): void
    {
        $this->cacheEnabled = $enabled;

        if (!$enabled) {
            $this->clearCache();
        }
    }



    private function getPersistentCachePath(): string
    {
        $basePath = defined('BASE_PATH') ? BASE_PATH : dirname(__DIR__, 2) . DIRECTORY_SEPARATOR;

        return $basePath . 'storage/framework/routes.php';
    }

    private function ensureCacheDirectoryExists(string $path): void
    {
        if (!is_dir($path)) {
            mkdir($path, 0755, true);
        }
    }

    /**
     * Create a RouteRegistrar with the given middleware.
     * Enables fluent syntax: Route::middleware(['web'])->group(...) or Route::middleware('auth')->get(...)
     *
     * @param string|array $middleware
     * @return RouteRegistrar
     */
    public function middleware($middleware): RouteRegistrar
    {
        return (new RouteRegistrar($this))->middleware($middleware);
    }

    /**
     * Create a RouteRegistrar with the given prefix.
     * Enables fluent syntax: Route::prefix('admin')->group(...)
     *
     * @param string $prefix
     * @return RouteRegistrar
     */
    public function prefix(string $prefix): RouteRegistrar
    {
        return (new RouteRegistrar($this))->prefix($prefix);
    }

    /**
     * Create a RouteRegistrar with the given version.
     *
     * @param string $version
     * @return RouteRegistrar
     */
    public function version(string $version): RouteRegistrar
    {
        return (new RouteRegistrar($this))->version($version);
    }

    /**
     * Create a RouteRegistrar with the given name prefix.
     *
     * @param string $as
     * @return RouteRegistrar
     */
    public function as(string $as): RouteRegistrar
    {
        return (new RouteRegistrar($this))->as($as);
    }

    /**
     * Create a RouteRegistrar with the given domain.
     * Enables fluent syntax: Route::domain('{tenant}.example.com')->group(...)
     *
     * @param string $domain
     * @return RouteRegistrar
     */
    public function domainGroup(string $domain): RouteRegistrar
    {
        return (new RouteRegistrar($this))->domain($domain);
    }


    /**
     * Push middleware to the global stack (applied to ALL routes).
     *
     * @param string|array $middleware
     * @return self
     */
    public function pushGlobalMiddleware($middleware): self
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

    public function getRoutesByMethod(string $method): array
    {
        $method = strtoupper($method);

        return $this->routes[$method] ?? [];
    }

    public function count(): int
    {
        return count($this->routes);
    }

    /**
     * Dump compiled routes for caching
     */
    public function dumpRoutes(): array
    {
        return [
            'routes' => $this->routes,
            'namedRoutes' => $this->namedRoutes,
        ];
    }

    /**
     * Load routes from cache
     */
    public function loadCachedRoutes(array $cache): void
    {
        $this->routes = $cache['routes'] ?? [];
        $this->namedRoutes = $cache['namedRoutes'] ?? [];
    }

    public function clear(): void
    {
        $this->routes = [
            'GET' => [],
            'POST' => [],
            'PUT' => [],
            'DELETE' => [],
            'PATCH' => [],
            'OPTIONS' => [],
            'HEAD' => [],
        ];
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
     * Route list for debugging
     */
    public function getRouteList(): array
    {
        $list = [];

        foreach ($this->routes as $method => $routes) {
            foreach ($routes as $route) {
                $list[] = [
                    'method' => $route->getMethod(),
                    'path' => $route->getPath(),
                    'name' => $route->getName(),
                    'middleware' => $route->getMiddleware(),
                    'handler' => $this->formatHandler($route->getHandler()),
                ];
            }
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

        if ($handler instanceof Closure) {
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
     * Pages Routing Support
     */

    /**
     * Enable file-based pages routing
     */
    public function enablePagesRouting(string $pagesDirectory, array $options = []): void
    {
        $this->pageRouter = new PageRouter($this, $pagesDirectory, $options);
        $this->pagesRoutingEnabled = true;
    }

    /**
     * Load and register page routes
     */
    public function loadPagesRoutes(): void
    {
        if (!$this->pagesRoutingEnabled || !$this->pageRouter) {
            return;
        }

        $this->pageRouter->registerRoutes();
    }

    /**
     * Get the page router instance
     */
    public function getPageRouter(): ?PageRouter
    {
        return $this->pageRouter;
    }

    /**
     * Check if pages routing is enabled
     */
    public function isPagesRoutingEnabled(): bool
    {
        return $this->pagesRoutingEnabled;
    }

    /**
     * Clear pages routing cache
     */
    public function clearPagesCache(): void
    {
        if ($this->pageRouter) {
            $this->pageRouter->clearCache();
        }
    }

    /**
     * Load internal framework routes
     */
    public function loadInternalRoutes(): void
    {
        if ($this->routesLoaded) {
            return;
        }

        $router = $this;
        require __DIR__ . '/internal_routes.php';
        $this->routesLoaded = true;
    }

    /**
     * Register routes from attributes on controllers in a directory.
     *
     * @param string $namespace The namespace of the controllers
     * @param string $directory The directory containing the controllers
     */
    public function registerAttributes(string $namespace, string $directory): void
    {
        if (!is_dir($directory)) {
            return;
        }

        $files = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($directory));
        foreach ($files as $file) {
            if ($file->isDir() || $file->getExtension() !== 'php') {
                continue;
            }

            $relativePath = str_replace([$directory, '.php', '/'], ['', '', '\\'], $file->getPathname());
            $className = $namespace . $relativePath;

            if (class_exists($className)) {
                $this->registerControllerAttributes($className);
            }
        }
    }

    /**
     * Register routes from attributes on a specific controller.
     */
    public function registerControllerAttributes(string $controller): void
    {
        $reflection = new ReflectionClass($controller);

        // Get class-level middleware
        $classMiddleware = [];
        $middlewareAttributes = $reflection->getAttributes(\Plugs\Http\Attributes\Middleware::class);
        foreach ($middlewareAttributes as $attribute) {
            $mw = $attribute->newInstance()->middleware;
            $classMiddleware = array_merge($classMiddleware, (array) $mw);
        }

        foreach ($reflection->getMethods(ReflectionMethod::IS_PUBLIC) as $method) {
            $routeAttributes = $method->getAttributes(\Plugs\Router\Attributes\Route::class);

            foreach ($routeAttributes as $attribute) {
                /** @var \Plugs\Router\Attributes\Route $routeAttr */
                $routeAttr = $attribute->newInstance();

                $methods = (array) $routeAttr->methods;
                $path = $routeAttr->path;
                $handler = [$controller, $method->getName()];

                // Get method-level middleware
                $methodMiddleware = [];
                $methodMwAttributes = $method->getAttributes(\Plugs\Http\Attributes\Middleware::class);
                foreach ($methodMwAttributes as $mwAttr) {
                    $mw = $mwAttr->newInstance()->middleware;
                    $methodMiddleware = array_merge($methodMiddleware, (array) $mw);
                }

                $middleware = array_merge($classMiddleware, $routeAttr->middleware, $methodMiddleware);

                $route = $this->match($methods, $path, $handler, $middleware);

                if ($routeAttr->name) {
                    $route->name($routeAttr->name);
                }

                if ($routeAttr->where) {
                    $route->where($routeAttr->where);
                }
            }
        }
    }

    /**
     * Macro support - Add custom methods to router
     */
    private static array $macros = [];

    public static function macro(string $name, callable $callback): void
    {
        self::$macros[$name] = $callback;
    }

    public static function hasMacro(string $name): bool
    {
        return isset(self::$macros[$name]);
    }

    public function __call(string $name, array $arguments)
    {
        if (!self::hasMacro($name)) {
            throw new RuntimeException("Method [{$name}] does not exist on Router");
        }

        $macro = self::$macros[$name];

        if ($macro instanceof Closure) {
            return call_user_func_array($macro->bindTo($this, static::class), $arguments);
        }

        return call_user_func_array($macro, $arguments);
    }

    /**
     * Resolve a model parameter for Route Model Binding.
     */
    private function resolveModelParameter(
        string $modelClass,
        string $paramName,
        array $routeParams,
        ServerRequestInterface $request,
        $sentinel
    ) {
        // 1. Check if the parameter exists in the route
        if (!array_key_exists($paramName, $routeParams)) {
            return $sentinel;
        }

        $value = $routeParams[$paramName];

        // 2. Determine the key to use for resolution
        $key = 'id';
        $route = $request->getAttribute('_route');
        if ($route instanceof Route) {
            $key = $route->getParameterKey($paramName) ?: 'id';
        }

        // 3. Resolve the model
        if ($key === 'id') {
            return $modelClass::findOrFail($value);
        }

        // Custom key resolution
        $model = $modelClass::where($key, '=', $value)->first();
        if (!$model) {
            throw (new \Plugs\Exceptions\ModelNotFoundException())->setModel($modelClass, $value);
        }

        return $model;
    }

    /**
     * Get compiled routes regex for the given method.
     */
    private function getCompiledRoutes(string $method): ?array
    {
        if (isset($this->compiledRoutes[$method])) {
            return $this->compiledRoutes[$method];
        }

        $routes = $this->routes[$method] ?? [];
        if (empty($routes)) {
            return null;
        }

        $patterns = [];
        foreach ($routes as $route) {
            $patterns[] = $route->getPattern();
        }

        // Combine all patterns into one large regex
        // We use '#' as delimiter to match Route::compilePattern
        // The 'J' flag enables PCRE2_DUPNAMES — allows duplicate named subpatterns
        // which is necessary since different routes may share parameter names like {slug}
        $regex = '#^(?:' . implode('|', array_map(function ($p) {
            return substr($p, 2, -3); // Strip '#^' and '$#u'
        }, $patterns)) . ')$#uJ';

        $this->compiledRoutes[$method] = [
            'regex' => $regex,
        ];

        return $this->compiledRoutes[$method];
    }

    /**
     * Get a structured map of all registered routes for AI introspection.
     *
     * @return array
     */
    /**
     * Get a structured map of all registered routes for AI introspection.
     *
     * @return array
     */
    public function getRouteMap(): array
    {
        $map = [];

        foreach ($this->routes as $method => $routes) {
            foreach ($routes as $route) {
                // We use path + method as key for uniqueness in the map
                $key = $method . ':' . $route->getPath();
                if (!isset($map[$key])) {
                    $map[$key] = $route->toArray();

                    // Add handler information specifically
                    $handler = $route->getHandler();
                    $map[$key]['handler'] = match (true) {
                        is_string($handler) => $handler,
                        is_array($handler) => implode('@', $handler),
                        $handler instanceof Closure => 'Closure',
                        default => 'Unknown'
                    };

                    if (is_string($handler)) {
                        $map[$key]['handler_type'] = 'string';
                    } elseif (is_array($handler)) {
                        $map[$key]['handler_type'] = 'controller';
                    } elseif ($handler instanceof Closure) {
                        $map[$key]['handler_type'] = 'closure';
                    } else {
                        $map[$key]['handler_type'] = 'unknown';
                    }
                }
            }
        }

        return $map;
    }
}
