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

use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;

class Router
{
    private $routes = [];
    private $groupPrefix = '';
    private $groupMiddleware = [];
    private $namedRoutes = [];

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
     * Register multiple HTTP methods
     */
    public function match(array $methods, string $path, $handler, array $middleware = []): Route
    {
        $route = null;
        foreach ($methods as $method) {
            $route = $this->addRoute(strtoupper($method), $path, $handler, $middleware);
        }
        return $route;
    }

    /**
     * Register all HTTP methods
     */
    public function any(string $path, $handler, array $middleware = []): Route
    {
        return $this->match(['GET', 'POST', 'PUT', 'DELETE', 'PATCH'], $path, $handler, $middleware);
    }

    /**
     * Group routes with common attributes
     */
    public function group(array $attributes, callable $callback): void
    {
        $previousPrefix = $this->groupPrefix;
        $previousMiddleware = $this->groupMiddleware;

        $this->groupPrefix = $previousPrefix . ($attributes['prefix'] ?? '');
        $this->groupMiddleware = array_merge($previousMiddleware, $attributes['middleware'] ?? []);

        $callback($this);

        $this->groupPrefix = $previousPrefix;
        $this->groupMiddleware = $previousMiddleware;
    }

    /**
     * Add a route
     */
    private function addRoute(string $method, string $path, $handler, array $middleware = []): Route
    {
        $path = $this->groupPrefix . $path;
        $middleware = array_merge($this->groupMiddleware, $middleware);

        // Convert array handler [Controller::class, 'method'] to string
        if (is_array($handler) && count($handler) === 2) {
            $handler = $handler[0] . '@' . $handler[1];
        }

        $route = new Route($method, $path, $handler, $middleware);
        $this->routes[] = $route;

        return $route;
    }

    /**
     * Get route by name
     */
    public function getRouteByName(string $name): ?Route
    {
        return $this->namedRoutes[$name] ?? null;
    }

    /**
     * Register named route
     */
    public function registerNamedRoute(string $name, Route $route): void
    {
        $this->namedRoutes[$name] = $route;
    }

    /**
     * Generate URL for named route
     */
    public function route(string $name, array $parameters = []): string
    {
        $route = $this->getRouteByName($name);

        if ($route === null) {
            throw new \RuntimeException("Route [{$name}] not found");
        }

        $path = $route->getPath();

        foreach ($parameters as $key => $value) {
            $path = str_replace('{' . $key . '}', $value, $path);
            $path = str_replace('{' . $key . '?}', $value, $path);
        }

        // Remove optional parameters that weren't provided
        $path = preg_replace('/\{[^}]+\?\}/', '', $path);

        return $path;
    }

    /**
     * Dispatch the request
     */
    public function dispatch(ServerRequestInterface $request): ?ResponseInterface
    {
        $method = $request->getMethod();
        $path = $request->getUri()->getPath();

        foreach ($this->routes as $route) {
            if ($route->getMethod() !== $method) {
                continue;
            }

            if (preg_match($route->getPattern(), $path, $matches)) {
                // Extract route parameters
                $params = array_filter($matches, 'is_string', ARRAY_FILTER_USE_KEY);

                // Add parameters to request attributes
                foreach ($params as $key => $value) {
                    $request = $request->withAttribute((string)$key, $value);
                }

                // Add route info
                $request = $request->withAttribute('_route', $route);

                // Execute middleware chain if any
                if (!empty($route->getMiddleware())) {
                    return $this->runMiddleware($route, $request);
                }

                // Execute handler directly
                return $this->executeHandler($route->getHandler(), $request);
            }
        }

        return null;
    }

    /**
     * Run middleware chain
     */
    private function runMiddleware(Route $route, ServerRequestInterface $request): ResponseInterface
    {
        $middleware = $route->getMiddleware();
        $handler = $route->getHandler();

        // Create middleware stack
        $stack = new \Plugs\Http\MiddlewareDispatcher();

        foreach ($middleware as $mw) {
            if (is_string($mw)) {
                // Resolve middleware from container
                $mw = app($mw);
            }
            $stack->add($mw);
        }

        // Set the route handler as fallback
        $stack->setFallbackHandler(function($request) use ($handler) {
            return $this->executeHandler($handler, $request);
        });

        return $stack->handle($request);
    }

    /**
     * Execute route handler
     */
    private function executeHandler($handler, ServerRequestInterface $request): ResponseInterface
    {
        if (is_string($handler) && strpos($handler, '@') !== false) {
            // Controller@method format
            [$controller, $method] = explode('@', $handler);

            if (!class_exists($controller)) {
                throw new \RuntimeException("Controller {$controller} not found");
            }

            // Use container to resolve controller with dependencies
            $container = \Plugs\Container\Container::getInstance();
            $instance = $container->make($controller);

            if (!method_exists($instance, $method)) {
                throw new \RuntimeException("Method {$method} not found in {$controller}");
            }

            return $instance->$method($request);
        } elseif (is_callable($handler)) {
            return $handler($request);
        }

        throw new \RuntimeException('Invalid route handler');
    }

    /**
     * Get all registered routes
     */
    public function getRoutes(): array
    {
        return $this->routes;
    }
}