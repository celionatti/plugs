<?php

declare(strict_types=1);

namespace Plugs\Router;

/*
|--------------------------------------------------------------------------
| Route Registrar
|--------------------------------------------------------------------------
|
| Provides a fluent interface for building route groups with attributes
| like middleware, prefix, namespace, and domain.
|
| Usage:
|   Route::middleware(['web', 'auth'])->group(function () { ... });
|   Route::prefix('admin')->middleware('auth')->group(function () { ... });
|   Route::middleware('web')->get('/path', $handler);
*/

class RouteRegistrar
{
    /**
     * The router instance.
     */
    protected Router $router;

    /**
     * The accumulated attributes for routes.
     */
    protected array $attributes = [];

    /**
     * Passthru methods that register routes directly.
     */
    protected array $passthru = [
        'get',
        'post',
        'put',
        'patch',
        'delete',
        'options',
        'any',
        'match',
    ];

    /**
     * Allowed attributes for fluent building.
     */
    protected array $allowedAttributes = [
        'middleware',
        'prefix',
        'namespace',
        'domain',
        'where',
        'version',
        'as',
    ];



    public function __construct(Router $router)
    {
        $this->router = $router;
    }

    /**
     * Set the middleware for the route group.
     *
     * @param string|array $middleware
     * @return self
     */
    public function middleware($middleware): self
    {
        $existing = $this->attributes['middleware'] ?? [];

        if (is_string($middleware)) {
            $middleware = [$middleware];
        }

        $this->attributes['middleware'] = array_merge($existing, $middleware);

        return $this;
    }

    /**
     * Set the prefix for the route group.
     *
     * @param string $prefix
     * @return self
     */
    public function prefix(string $prefix): self
    {
        $this->attributes['prefix'] = $prefix;

        return $this;
    }

    /**
     * Set the namespace for the route group.
     *
     * @param string $namespace
     * @return self
     */
    public function namespace(string $namespace): self
    {
        $this->attributes['namespace'] = $namespace;

        return $this;
    }

    /**
     * Set the domain for the route group.
     *
     * @param string $domain
     * @return self
     */
    public function domain(string $domain): self
    {
        $this->attributes['domain'] = $domain;

        return $this;
    }

    /**
     * Set where constraints for the route group.
     *
     * @param array $where
     * @return self
     */
    public function where(array $where): self
    {
        $existing = $this->attributes['where'] ?? [];

        $this->attributes['where'] = array_merge($existing, $where);

        return $this;
    }

    /**
     * Set the version for the route group.
     *
     * @param string $version
     * @return self
     */
    public function version(string $version): self
    {
        $this->attributes['version'] = $version;

        return $this;
    }

    /**
     * Set the name prefix for the route group.
     *
     * @param string $name
     * @return self
     */
    public function as(string $name): self
    {
        $this->attributes['as'] = $name;

        return $this;
    }


    /**
     * Create a route group with the accumulated attributes.
     *
     * @param callable $callback
     * @return void
     */
    public function group(callable $callback): void
    {
        $this->router->group($this->attributes, $callback);
    }

    /**
     * Register a new route with the accumulated attributes applied.
     *
     * @param string $method
     * @param string $path
     * @param mixed $handler
     * @param array $middleware
     * @return Route
     */
    protected function registerRoute(string $method, string $path, $handler, array $middleware = []): Route
    {
        $route = null;

        $this->router->group($this->attributes, function () use ($method, $path, $handler, $middleware, &$route) {
            $route = $this->router->$method($path, $handler, $middleware);
        });

        return $route;
    }

    /**
     * Register a GET route.
     */
    public function get(string $path, $handler, array $middleware = []): Route
    {
        return $this->registerRoute('get', $path, $handler, $middleware);
    }

    /**
     * Register a POST route.
     */
    public function post(string $path, $handler, array $middleware = []): Route
    {
        return $this->registerRoute('post', $path, $handler, $middleware);
    }

    /**
     * Register a PUT route.
     */
    public function put(string $path, $handler, array $middleware = []): Route
    {
        return $this->registerRoute('put', $path, $handler, $middleware);
    }

    /**
     * Register a DELETE route.
     */
    public function delete(string $path, $handler, array $middleware = []): Route
    {
        return $this->registerRoute('delete', $path, $handler, $middleware);
    }

    /**
     * Register a PATCH route.
     */
    public function patch(string $path, $handler, array $middleware = []): Route
    {
        return $this->registerRoute('patch', $path, $handler, $middleware);
    }

    /**
     * Register an OPTIONS route.
     */
    public function options(string $path, $handler, array $middleware = []): Route
    {
        return $this->registerRoute('options', $path, $handler, $middleware);
    }

    /**
     * Register a route responding to any HTTP method.
     */
    public function any(string $path, $handler, array $middleware = []): Route
    {
        return $this->registerRoute('any', $path, $handler, $middleware);
    }

    /**
     * Register a route for multiple HTTP methods.
     */
    public function match(array $methods, string $path, $handler, array $middleware = []): Route
    {
        $route = null;

        $this->router->group($this->attributes, function () use ($methods, $path, $handler, $middleware, &$route) {
            $route = $this->router->match($methods, $path, $handler, $middleware);
        });

        return $route;
    }

    /**
     * Register a RESTful resource.
     */
    public function resource(string $name, string $controller, array $options = []): void
    {
        $this->router->group($this->attributes, function () use ($name, $controller, $options) {
            $this->router->resource($name, $controller, $options);
        });
    }

    /**
     * Register an API resource.
     */
    public function apiResource(string $name, string $controller, array $options = []): void
    {
        $this->router->group($this->attributes, function () use ($name, $controller, $options) {
            $this->router->apiResource($name, $controller, $options);
        });
    }
}
