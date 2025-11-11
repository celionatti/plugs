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
use Plugs\Container\Container;
use Psr\Http\Message\ServerRequestInterface;
use RuntimeException;


class Route
{
    private string $method;
    private string $path;
    private $handler;
    private array $middleware = [];
    private string $pattern;
    private ?string $name = null;
    private array $where = [];
    private ?Router $router = null;

    /**
     * Create a new Route instance.
     *
     * @param string $method HTTP method
     * @param string $path Route path pattern
     * @param callable|string|array $handler Route handler
     * @param array $middleware Middleware stack
     * @param Router|null $router Parent router instance
     */
    public function __construct(
        string $method,
        string $path,
        $handler,
        array $middleware = [],
        ?Router $router = null
    ) {
        $this->method = strtoupper($method);
        $this->path = $path;
        $this->handler = $handler;
        $this->middleware = $middleware;
        $this->router = $router;
        $this->pattern = $this->compilePattern($path);
    }

    /**
     * Add middleware to route.
     *
     * @param string|array $middleware Middleware class name(s)
     * @return self
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
     * Set route name for URL generation.
     *
     * @param string $name Unique route name
     * @return self
     */
    public function name(string $name): self
    {
        $this->name = $name;

        if ($this->router !== null) {
            $this->router->registerNamedRoute($name, $this);
        }

        return $this;
    }

    /**
     * Add parameter constraint(s).
     *
     * @param string|array $key Parameter name or array of constraints
     * @param string|null $pattern Regex pattern for single parameter
     * @return self
     */
    public function where($key, ?string $pattern = null): self
    {
        if (is_array($key)) {
            $this->where = array_merge($this->where, $key);
        } else {
            $this->where[$key] = $pattern;
        }

        // Recompile pattern with new constraints
        $this->pattern = $this->compilePattern($this->path);

        return $this;
    }

    /**
     * Compile route pattern to regex with parameter constraints.
     *
     * @param string $path Route path pattern
     * @return string Compiled regex pattern
     */
    private function compilePattern(string $path): string
    {
        // Escape special regex characters except for parameter placeholders
        $pattern = preg_quote($path, '#');

        // Restore escaped curly braces for parameters
        $pattern = str_replace(['\{', '\}', '\?'], ['{', '}', '?'], $pattern);

        // Replace required parameters with regex
        $pattern = preg_replace_callback(
            '/\{([a-zA-Z_][a-zA-Z0-9_]*)\}/',
            function ($matches) {
                $param = $matches[1];
                $constraint = $this->where[$param] ?? '[^/]+';
                return "(?P<{$param}>{$constraint})";
            },
            $pattern
        );

        // Replace optional parameters
        $pattern = preg_replace_callback(
            '/\{([a-zA-Z_][a-zA-Z0-9_]*)\?\}/',
            function ($matches) {
                $param = $matches[1];
                $constraint = $this->where[$param] ?? '[^/]*';
                return "(?P<{$param}>{$constraint})?";
            },
            $pattern
        );

        return '#^' . $pattern . '$#u';
    }

    /**
     * Make this route a proxy to an external API endpoint.
     *
     * @param string $targetUrl Target API endpoint
     * @param array $options Proxy options (base_uri, token, headers, timeout)
     * @return self
     */
    public function proxy(string $targetUrl, array $options = []): self
    {
        $this->handler = function (ServerRequestInterface $request) use ($targetUrl, $options) {
            $http = HTTP::make();

            // Apply configuration options
            if (isset($options['base_uri'])) {
                $http->baseUri($options['base_uri']);
            }
            if (isset($options['token'])) {
                $http->bearerToken($options['token']);
            }
            if (isset($options['headers']) && is_array($options['headers'])) {
                $http->headers($options['headers']);
            }
            if (isset($options['timeout'])) {
                $http->timeout((int) $options['timeout']);
            }

            // Forward the request with appropriate method
            $method = strtolower($request->getMethod());

            // Build query parameters or body data
            $data = $method === 'get'
                ? $request->getQueryParams()
                : (array) $request->getParsedBody();

            try {
                $response = $http->$method($targetUrl, $data);

                return \Plugs\Http\ResponseFactory::json(
                    $response->json(),
                    $response->status()
                );
            } catch (\Exception $e) {
                return \Plugs\Http\ResponseFactory::json(
                    ['error' => 'Proxy request failed', 'message' => $e->getMessage()],
                    502
                );
            }
        };

        return $this;
    }

    /**
     * Fetch data from URL before handling route.
     *
     * @param string $url URL to fetch data from
     * @param string $attribute Request attribute name for fetched data
     * @return self
     */
    public function fetch(string $url, string $attribute = 'fetched_data'): self
    {
        $originalHandler = $this->handler;

        $this->handler = function (ServerRequestInterface $request) use ($originalHandler, $url, $attribute) {
            try {
                // Fetch data from URL
                $response = HTTP::make()->get($url);

                // Add to request attributes
                $request = $request->withAttribute($attribute, $response->json());
            } catch (\Exception $e) {
                // Add error to request attributes instead of failing
                $request = $request->withAttribute(
                    $attribute . '_error',
                    $e->getMessage()
                );
            }

            // Continue with original handler
            return $this->executeOriginalHandler($originalHandler, $request);
        };

        return $this;
    }

    /**
     * Execute the original handler with the modified request.
     *
     * @param mixed $handler Original route handler
     * @param ServerRequestInterface $request PSR-7 request
     * @return mixed Handler response
     * @throws RuntimeException If handler format is invalid
     */
    private function executeOriginalHandler($handler, ServerRequestInterface $request)
    {
        if (is_callable($handler)) {
            return $handler($request);
        }

        if (is_string($handler) && strpos($handler, '@') !== false) {
            [$controller, $method] = explode('@', $handler, 2);

            $container = Container::getInstance();
            $instance = $container->make($controller);

            if (!method_exists($instance, $method)) {
                throw new RuntimeException(
                    "Method {$method} not found in controller {$controller}"
                );
            }

            return $instance->$method($request);
        }

        throw new RuntimeException('Invalid route handler format');
    }

    // Getters with proper return types

    public function getMethod(): string
    {
        return $this->method;
    }

    public function getPath(): string
    {
        return $this->path;
    }

    /**
     * @return callable|string|array
     */
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

    public function getConstraints(): array
    {
        return $this->where;
    }

    /**
     * Check if route matches given method and path.
     *
     * @param string $method HTTP method
     * @param string $path Request path
     * @return bool
     */
    public function matches(string $method, string $path): bool
    {
        return $this->method === strtoupper($method)
            && preg_match($this->pattern, $path) === 1;
    }

    /**
     * Extract parameters from path using route pattern.
     *
     * @param string $path Request path
     * @return array Extracted parameters
     */
    public function extractParameters(string $path): array
    {
        if (preg_match($this->pattern, $path, $matches)) {
            return array_filter($matches, 'is_string', ARRAY_FILTER_USE_KEY);
        }

        return [];
    }
}
