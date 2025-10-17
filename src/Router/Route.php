<?php

declare(strict_types=1);

namespace Plugs\Router;

/*
|--------------------------------------------------------------------------
| Route Class
|--------------------------------------------------------------------------
|
| Route class for defining individual routes.
*/

class Route
{
    private $method;
    private $path;
    private $handler;
    private $middleware = [];
    private $pattern;
    private $name;
    private $where = [];

    public function __construct(string $method, string $path, $handler, array $middleware = [])
    {
        $this->method = $method;
        $this->path = $path;
        $this->handler = $handler;
        $this->middleware = $middleware;
        $this->pattern = $this->compilePattern($path);
    }

    /**
     * Add middleware to route
     */
    public function middleware($middleware): self
    {
        if (is_array($middleware)) {
            $this->middleware = array_merge($this->middleware, $middleware);
        } else {
            $this->middleware[] = $middleware;
        }

        return $this;
    }

    /**
     * Set route name
     */
    public function name(string $name): self
    {
        $this->name = $name;

        // Register with router
        $router = app(Router::class);
        $router->registerNamedRoute($name, $this);

        return $this;
    }

    /**
     * Add parameter constraint
     */
    public function where($key, $pattern = null): self
    {
        if (is_array($key)) {
            $this->where = array_merge($this->where, $key);
        } else {
            $this->where[$key] = $pattern;
        }

        // Recompile pattern with constraints
        $this->pattern = $this->compilePattern($this->path);

        return $this;
    }

    /**
     * Compile route pattern
     */
    private function compilePattern(string $path): string
    {
        $pattern = $path;

        // Replace parameters with regex
        $pattern = preg_replace_callback('/\{([a-zA-Z0-9_]+)\}/', function($matches) {
            $param = $matches[1];
            $constraint = $this->where[$param] ?? '[^/]+';
            return "(?P<{$param}>{$constraint})";
        }, $pattern);

        // Replace optional parameters
        $pattern = preg_replace_callback('/\{([a-zA-Z0-9_]+)\?\}/', function($matches) {
            $param = $matches[1];
            $constraint = $this->where[$param] ?? '[^/]*';
            return "(?P<{$param}>{$constraint})";
        }, $pattern);

        return '#^' . $pattern . '$#';
    }

    // Getters
    public function getMethod(): string { return $this->method; }
    public function getPath(): string { return $this->path; }
    public function getHandler() { return $this->handler; }
    public function getMiddleware(): array { return $this->middleware; }
    public function getPattern(): string { return $this->pattern; }
    public function getName(): ?string { return $this->name; }
}