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

use Plugs\Http\HTTP;


class Route
{
    private $method;
    private $path;
    private $handler;
    private $middleware = [];
    private $pattern;
    private $name;
    private $where = [];
    private $router;

    public function __construct(string $method, string $path, $handler, array $middleware = [], ?Router $router = null)
    {
        $this->method = $method;
        $this->path = $path;
        $this->handler = $handler;
        $this->middleware = $middleware;
        $this->router = $router;
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

        // Register with router if available
        if ($this->router !== null) {
            $this->router->registerNamedRoute($name, $this);
        }

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
        $pattern = preg_replace_callback('/\{([a-zA-Z0-9_]+)\}/', function ($matches) {
            $param = $matches[1];
            $constraint = $this->where[$param] ?? '[^/]+';
            return "(?P<{$param}>{$constraint})";
        }, $pattern);

        // Replace optional parameters
        $pattern = preg_replace_callback('/\{([a-zA-Z0-9_]+)\?\}/', function ($matches) {
            $param = $matches[1];
            $constraint = $this->where[$param] ?? '[^/]*';
            return "(?P<{$param}>{$constraint})";
        }, $pattern);

        return '#^' . $pattern . '$#';
    }

    // Getters
    public function getMethod(): string
    {
        return $this->method;
    }
    public function getPath(): string
    {
        return $this->path;
    }
    public function getHandler()
    {
        return $this->handler;
    }
    public function getMiddleware(): array
    {
        return $this->middleware;
    }
    public function getPattern(): string
    {
        return $this->pattern;
    }
    public function getName(): ?string
    {
        return $this->name;
    }

    /**
     * Make this route a proxy to an external API endpoint
     * @var array options: base_uri, token, headers, timeout
     */
    public function proxy(string $targetUrl, array $options = []): self
    {
        $originalHandler = $this->handler;
        
        $this->handler = function($request) use ($targetUrl, $options) {
            $http = HTTP::make();
            
            // Apply options
            if (isset($options['base_uri'])) {
                $http->baseUri($options['base_uri']);
            }
            if (isset($options['token'])) {
                $http->bearerToken($options['token']);
            }
            if (isset($options['headers'])) {
                $http->headers($options['headers']);
            }
            if (isset($options['timeout'])) {
                $http->timeout($options['timeout']);
            }

            // Forward the request
            $method = strtolower($request->getMethod());
            $response = $http->$method($targetUrl, $request->getQueryParams());

            return \Plugs\Http\ResponseFactory::json(
                $response->json(),
                $response->status()
            );
        };
        
        return $this;
    }

    /**
     * Fetch data from URL before handling route
     */
    /**
     * Fetch data from URL before handling route
     */
    public function fetch(string $url, string $attribute = 'fetched_data'): self
    {
        $originalHandler = $this->handler;
        
        $this->handler = function($request) use ($originalHandler, $url, $attribute) {
            // Fetch data
            $response = HTTP::make()->get($url);
            
            // Add to request attributes
            $request = $request->withAttribute($attribute, $response->json());
            
            // Continue with original handler
            if (is_callable($originalHandler)) {
                return $originalHandler($request);
            }
            
            // Handle Controller@method format
            if (is_string($originalHandler) && strpos($originalHandler, '@') !== false) {
                [$controller, $method] = explode('@', $originalHandler);
                $container = \Plugs\Container\Container::getInstance();
                $instance = $container->make($controller);
                return $instance->$method($request);
            }
            
            throw new \RuntimeException('Invalid route handler');
        };
        
        return $this;
    }
}
