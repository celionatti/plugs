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
use InvalidArgumentException;


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
    private array $defaults = [];
    private ?string $domain = null;
    private ?string $scheme = null;

    /** @var array Common parameter patterns */
    private const COMMON_PATTERNS = [
        'id' => '[0-9]+',
        'uuid' => '[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}',
        'slug' => '[a-z0-9-]+',
        'alpha' => '[a-zA-Z]+',
        'alphanum' => '[a-zA-Z0-9]+',
        'any' => '.*',
    ];

    public function __construct(
        string $method,
        string $path,
        $handler,
        array $middleware = [],
        ?Router $router = null
    ) {
        $this->validateMethod($method);
        $this->method = strtoupper($method);
        $this->path = $path;
        $this->handler = $handler;
        $this->middleware = $middleware;
        $this->router = $router;
        $this->pattern = $this->compilePattern($path);
    }

    /**
     * Validate HTTP method
     */
    private function validateMethod(string $method): void
    {
        $validMethods = ['GET', 'POST', 'PUT', 'DELETE', 'PATCH', 'HEAD', 'OPTIONS', 'TRACE', 'CONNECT'];

        if (!in_array(strtoupper($method), $validMethods, true)) {
            throw new InvalidArgumentException("Invalid HTTP method: {$method}");
        }
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
     * Set route name for URL generation
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
     * Add parameter constraint(s)
     */
    public function where($key, ?string $pattern = null): self
    {
        if (is_array($key)) {
            foreach ($key as $k => $v) {
                $this->where[$k] = $v;
            }
        } else {
            $this->where[$key] = $pattern;
        }

        // Recompile pattern with new constraints
        $this->pattern = $this->compilePattern($this->path);

        return $this;
    }

    /**
     * Add common parameter constraints
     */
    public function whereNumber(string $parameter): self
    {
        return $this->where($parameter, '[0-9]+');
    }

    public function whereAlpha(string $parameter): self
    {
        return $this->where($parameter, '[a-zA-Z]+');
    }

    public function whereAlphaNumeric(string $parameter): self
    {
        return $this->where($parameter, '[a-zA-Z0-9]+');
    }

    public function whereUuid(string $parameter): self
    {
        return $this->where($parameter, self::COMMON_PATTERNS['uuid']);
    }

    public function whereSlug(string $parameter): self
    {
        return $this->where($parameter, self::COMMON_PATTERNS['slug']);
    }

    public function whereIn(string $parameter, array $values): self
    {
        $pattern = '(' . implode('|', array_map('preg_quote', $values)) . ')';
        return $this->where($parameter, $pattern);
    }

    /**
     * Set default values for optional parameters
     */
    public function defaults(array $defaults): self
    {
        $this->defaults = array_merge($this->defaults, $defaults);
        return $this;
    }

    /**
     * Set domain constraint
     */
    public function domain(string $domain): self
    {
        $this->domain = $domain;
        return $this;
    }

    /**
     * Set scheme constraint (http/https)
     */
    public function scheme(string $scheme): self
    {
        if (!in_array($scheme, ['http', 'https'], true)) {
            throw new InvalidArgumentException("Invalid scheme: {$scheme}");
        }

        $this->scheme = $scheme;
        return $this;
    }

    /**
     * Require HTTPS
     */
    public function secure(): self
    {
        return $this->scheme('https');
    }

    /**
     * Compile route pattern to regex with parameter constraints
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
     * Make this route a proxy to an external API endpoint
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

            // Replace URL parameters
            $params = $request->getAttributes();
            foreach ($params as $key => $value) {
                if (!str_starts_with($key, '_')) {
                    $targetUrl = str_replace('{' . $key . '}', (string) $value, $targetUrl);
                }
            }

            // Forward the request with appropriate method
            $method = strtolower($request->getMethod());

            // Build query parameters or body data
            if ($method === 'get') {
                $data = $request->getQueryParams();
            } else {
                $parsedBody = $request->getParsedBody();
                $data = is_array($parsedBody) ? $parsedBody : [];
            }

            try {
                $response = $http->$method($targetUrl, $data);

                return \Plugs\Http\ResponseFactory::json(
                    $response->json(),
                    $response->status()
                );
            } catch (\Exception $e) {
                return \Plugs\Http\ResponseFactory::json(
                    [
                        'error' => 'Proxy request failed',
                        'message' => $e->getMessage()
                    ],
                    502
                );
            }
        };

        return $this;
    }

    /**
     * Fetch data from URL before handling route
     */
    public function fetch(string $url, string $attribute = 'fetched_data'): self
    {
        $originalHandler = $this->handler;

        $this->handler = function (ServerRequestInterface $request) use ($originalHandler, $url, $attribute) {
            // Replace URL parameters
            $params = $request->getAttributes();
            foreach ($params as $key => $value) {
                if (!str_starts_with($key, '_')) {
                    $url = str_replace('{' . $key . '}', (string) $value, $url);
                }
            }

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
     * Cache the response for specified duration
     */
    public function cache(int $seconds, ?string $key = null): self
    {
        $originalHandler = $this->handler;

        $this->handler = function (ServerRequestInterface $request) use ($originalHandler, $seconds, $key) {
            // Generate cache key if not provided
            if ($key === null) {
                $key = 'route_cache:' . $this->method . ':' . $this->path;
                // Include parameters in cache key
                $params = $request->getAttributes();
                foreach ($params as $k => $v) {
                    if (!str_starts_with($k, '_')) {
                        $key .= ':' . $k . '=' . $v;
                    }
                }
            }

            // Check if caching functions are available
            if (function_exists('cache')) {
                $cached = cache($key);
                if ($cached !== null) {
                    return $cached;
                }
            }

            // Execute handler
            $response = $this->executeOriginalHandler($originalHandler, $request);

            // Store in cache
            if (function_exists('cache')) {
                cache([$key => $response], $seconds);
            }

            return $response;
        };

        return $this;
    }

    /**
     * Execute the original handler with the modified request
     */
    private function executeOriginalHandler($handler, ServerRequestInterface $request)
    {
        if (is_callable($handler)) {
            return $handler($request);
        }

        if (is_string($handler) && strpos($handler, '@') !== false) {
            [$controller, $method] = explode('@', $handler, 2);

            if (!class_exists($controller)) {
                throw new RuntimeException("Controller {$controller} not found");
            }

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

    public function getConstraints(): array
    {
        return $this->where;
    }

    public function getDefaults(): array
    {
        return $this->defaults;
    }

    public function getDomain(): ?string
    {
        return $this->domain;
    }

    public function getScheme(): ?string
    {
        return $this->scheme;
    }

    /**
     * Check if route matches given method and path
     */
    public function matches(string $method, string $path): bool
    {
        // Check method
        if ($this->method !== strtoupper($method)) {
            return false;
        }

        // Check domain if set
        if ($this->domain !== null) {
            $host = $_SERVER['HTTP_HOST'] ?? '';
            if (!preg_match('#^' . str_replace('\*', '.*', preg_quote($this->domain, '#')) . '$#', $host)) {
                return false;
            }
        }

        // Check scheme if set
        if ($this->scheme !== null) {
            $isSecure = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off');
            $requestScheme = $isSecure ? 'https' : 'http';

            if ($this->scheme !== $requestScheme) {
                return false;
            }
        }

        // Check path pattern
        return preg_match($this->pattern, $path) === 1;
    }

    /**
     * Extract parameters from path using route pattern
     */
    public function extractParameters(string $path): array
    {
        if (!preg_match($this->pattern, $path, $matches)) {
            return [];
        }

        $params = array_filter($matches, 'is_string', ARRAY_FILTER_USE_KEY);

        // Apply defaults for missing optional parameters
        foreach ($this->defaults as $key => $value) {
            if (!isset($params[$key]) || $params[$key] === '') {
                $params[$key] = $value;
            }
        }

        return $params;
    }

    /**
     * Generate URL for this route with given parameters
     */
    public function url(array $parameters = [], bool $absolute = false): string
    {
        $path = $this->path;

        // Replace parameters
        foreach ($parameters as $key => $value) {
            $path = str_replace('{' . $key . '}', (string) $value, $path);
            $path = str_replace('{' . $key . '?}', (string) $value, $path);
        }

        // Remove unfilled optional parameters
        $path = preg_replace('/\{[^}]+\?\}/', '', $path);

        // Check for unfilled required parameters
        if (preg_match('/\{([^}?]+)\}/', $path, $matches)) {
            throw new RuntimeException(
                "Missing required parameter [{$matches[1]}] for route"
            );
        }

        // Generate absolute URL if requested
        if ($absolute) {
            $scheme = $this->scheme ?? ((!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http');
            $host = $this->domain ?? ($_SERVER['HTTP_HOST'] ?? 'localhost');
            $path = $scheme . '://' . $host . $path;
        }

        return $path;
    }
}
