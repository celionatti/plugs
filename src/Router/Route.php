<?php

declare(strict_types=1);

namespace Plugs\Router;

/*
|--------------------------------------------------------------------------
| Route Class
|--------------------------------------------------------------------------
|
| Route class for defining individual routes.
| Represents a single route with its constraints, middleware, and handler.
| Supports parameter constraints, caching, proxying, and more.
*/

use InvalidArgumentException;
use Plugs\Container\Container;
use Plugs\Http\HTTPClient as HTTP;
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
    private array $defaults = [];
    private ?string $domain = null;
    private ?string $domainPattern = null;
    private ?string $scheme = null;
    private array $metadata = [];
    private array $parameterKeys = [];

    /** @var array Common parameter patterns */
    private const COMMON_PATTERNS = [
        'id' => '[0-9]+',
        'uuid' => '[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}',
        'slug' => '[a-z0-9]+(?:-[a-z0-9]+)*',
        'alpha' => '[a-zA-Z]+',
        'alphanum' => '[a-zA-Z0-9]+',
        'any' => '.*',
        'email' => '[a-zA-Z0-9._%+-]+@[a-zA-Z0-9.-]+\.[a-zA-Z]{2,}',
    ];

    /** @var string|null Route name prefix */
    private ?string $namePrefix = null;

    /** @var array Valid HTTP methods */

    private const VALID_METHODS = [
        'GET',
        'POST',
        'PUT',
        'DELETE',
        'PATCH',
        'HEAD',
        'OPTIONS',
        'TRACE',
        'CONNECT',
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

        if ($router) {
            $this->namePrefix = $router->getGroupAs();
        }

        $this->pattern = $this->compilePattern($path);
    }


    private function validateMethod(string $method): void
    {
        if (!in_array(strtoupper($method), self::VALID_METHODS, true)) {
            throw new InvalidArgumentException("Invalid HTTP method: {$method}");
        }
    }

    public function middleware($middleware): self
    {
        if (is_array($middleware)) {
            $this->middleware = array_merge($this->middleware, $middleware);
        } else {
            $this->middleware[] = $middleware;
        }

        return $this;
    }

    public function withoutMiddleware($middleware): self
    {
        $middlewareToRemove = is_array($middleware) ? $middleware : [$middleware];

        $this->middleware = array_filter(
            $this->middleware,
            fn($mw) => !in_array($mw, $middlewareToRemove, true)
        );

        return $this;
    }

    public function name(string $name): self
    {
        if ($this->namePrefix) {
            $name = $this->namePrefix . $name;
        }

        $this->name = $name;

        if ($this->router !== null) {
            $this->router->registerNamedRoute($name, $this);
        }


        return $this;
    }


    public function getParameterKey(string $name): ?string
    {
        return $this->parameterKeys[$name] ?? null;
    }

    public function where($key, ?string $pattern = null): self
    {
        if (is_array($key)) {
            foreach ($key as $k => $v) {
                $this->where[$k] = $v;
            }
        } else {
            $this->where[$key] = $pattern;
        }

        $this->pattern = $this->compilePattern($this->path);

        return $this;
    }

    public function whereNumber(...$parameters): self
    {
        foreach ($parameters as $parameter) {
            $this->where($parameter, '[0-9]+');
        }

        return $this;
    }

    public function whereAlpha(...$parameters): self
    {
        foreach ($parameters as $parameter) {
            $this->where($parameter, '[a-zA-Z]+');
        }

        return $this;
    }

    public function whereAlphaNumeric(...$parameters): self
    {
        foreach ($parameters as $parameter) {
            $this->where($parameter, '[a-zA-Z0-9]+');
        }

        return $this;
    }


    public function whereUuid(string $parameter): self
    {
        return $this->where($parameter, self::COMMON_PATTERNS['uuid']);
    }

    public function whereSlug(string $parameter): self
    {
        return $this->where($parameter, self::COMMON_PATTERNS['slug']);
    }

    public function whereEmail(string $parameter): self
    {
        return $this->where($parameter, self::COMMON_PATTERNS['email']);
    }

    public function whereIn(string $parameter, array $values): self
    {
        $pattern = '(' . implode('|', array_map('preg_quote', $values)) . ')';

        return $this->where($parameter, $pattern);
    }

    public function defaults(array $defaults): self
    {
        $this->defaults = array_merge($this->defaults, $defaults);

        return $this;
    }

    public function domain(string $domain): self
    {
        $this->domain = $domain;
        $this->domainPattern = $this->compileDomainPattern($domain);

        return $this;
    }

    /**
     * Compile a domain string into a regex pattern.
     * Supports {param} placeholders for dynamic subdomain extraction.
     *
     * Examples:
     *   'admin.example.com'            → static match
     *   '{tenant}.example.com'         → captures 'tenant'
     *   '{sub}.{region}.example.com'   → captures 'sub' and 'region'
     *   '*.example.com'                → wildcard match
     */
    private function compileDomainPattern(string $domain): string
    {
        $pattern = preg_quote($domain, '#');
        // Restore escaped braces for parameter replacement
        $pattern = str_replace(['\{', '\}'], ['{', '}'], $pattern);

        // Replace {param} with named capture groups
        $pattern = preg_replace(
            '/\{([a-zA-Z_][a-zA-Z0-9_]*)\}/',
            '(?P<$1>[a-zA-Z0-9_-]+)',
            $pattern
        );

        // Support wildcard (*) domains
        $pattern = str_replace('\*', '[a-zA-Z0-9_-]+', $pattern);

        return '#^' . $pattern . '$#i';
    }

    /**
     * Extract parameters from the domain/host.
     * Returns an associative array of param => value.
     */
    public function extractDomainParameters(string $host): array
    {
        if ($this->domain === null || $this->domainPattern === null) {
            return [];
        }

        if (!preg_match($this->domainPattern, $host, $matches)) {
            return [];
        }

        return array_filter($matches, 'is_string', ARRAY_FILTER_USE_KEY);
    }

    /**
     * Check if the domain string contains dynamic parameters.
     */
    public function hasDomainParameters(): bool
    {
        return $this->domain !== null && str_contains($this->domain, '{');
    }

    public function scheme(string $scheme): self
    {
        if (!in_array($scheme, ['http', 'https'], true)) {
            throw new InvalidArgumentException("Invalid scheme: {$scheme}");
        }
        $this->scheme = $scheme;

        return $this;
    }

    public function secure(): self
    {
        return $this->scheme('https');
    }

    public function meta(string $key, $value): self
    {
        $this->metadata[$key] = $value;

        return $this;
    }

    public function getMeta(string $key, $default = null)
    {
        return $this->metadata[$key] ?? $default;
    }

    private function compilePattern(string $path): string
    {
        $pattern = preg_quote($path, '#');
        $pattern = str_replace(['\{', '\}', '\?', '\:'], ['{', '}', '?', ':'], $pattern);

        // Support {param} and {param:key}
        $pattern = preg_replace_callback(
            '/\{([a-zA-Z_][a-zA-Z0-9_]*)(?::([a-zA-Z0-9_]+))?\}/',
            function ($matches) {
                $param = $matches[1];
                $key = $matches[2] ?? null;

                if ($key) {
                    $this->parameterKeys[$param] = $key;
                }

                $constraint = $this->where[$param] ?? '[^/]+';

                return "(?P<{$param}>{$constraint})";
            },
            $pattern
        );

        // Optional parameters: {param?}
        // Also handle the preceding slash for optional parameters
        $pattern = preg_replace_callback(
            '/\/?\{([a-zA-Z_][a-zA-Z0-9_]*)\?\}/',
            function ($matches) {
                $param = $matches[1];
                $constraint = $this->where[$param] ?? '[^/]*';
                $hasSlash = str_starts_with($matches[0], '/');

                if ($hasSlash) {
                    return "(?:\/(?P<{$param}>{$constraint}))?";
                }

                return "(?P<{$param}>{$constraint})?";
            },
            $pattern
        );

        return '#^' . $pattern . '$#u';
    }

    public function redirect(string $destination, int $status = 302): self
    {
        $this->handler = function () use ($destination, $status) {
            return \Plugs\Http\ResponseFactory::redirect($destination, $status);
        };

        return $this;
    }

    public function permanentRedirect(string $destination): self
    {
        return $this->redirect($destination, 301);
    }

    public function proxy(string $targetUrl, array $options = []): self
    {
        $this->handler = function (ServerRequestInterface $request) use ($targetUrl, $options) {
            $http = HTTP::make();

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

            $params = $request->getAttributes();
            foreach ($params as $key => $value) {
                if (!str_starts_with($key, '_')) {
                    $targetUrl = str_replace('{' . $key . '}', (string) $value, $targetUrl);
                }
            }

            $method = strtolower($request->getMethod());

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
                        'message' => $e->getMessage(),
                    ],
                    502
                );
            }
        };

        return $this;
    }

    public function fetch(string $url, string $attribute = 'fetched_data'): self
    {
        $originalHandler = $this->handler;

        $this->handler = function (ServerRequestInterface $request) use ($originalHandler, $url, $attribute) {
            $params = $request->getAttributes();
            foreach ($params as $key => $value) {
                if (!str_starts_with($key, '_')) {
                    $url = str_replace('{' . $key . '}', (string) $value, $url);
                }
            }

            try {
                $response = HTTP::make()->get($url);
                $request = $request->withAttribute($attribute, $response->json());
            } catch (\Exception $e) {
                $request = $request->withAttribute(
                    $attribute . '_error',
                    $e->getMessage()
                );
            }

            return $this->executeOriginalHandler($originalHandler, $request);
        };

        return $this;
    }

    public function cache(int $seconds, ?string $key = null): self
    {
        $originalHandler = $this->handler;

        $this->handler = function (ServerRequestInterface $request) use ($originalHandler, $seconds, $key) {
            if ($key === null) {
                $key = 'route_cache:' . $this->method . ':' . $this->path;
                $params = $request->getAttributes();
                foreach ($params as $k => $v) {
                    if (!str_starts_with($k, '_')) {
                        $key .= ':' . $k . '=' . $v;
                    }
                }
            }

            if (function_exists('cache')) {
                $cached = cache($key);
                if ($cached !== null) {
                    return $cached;
                }
            }

            $response = $this->executeOriginalHandler($originalHandler, $request);

            if (function_exists('cache')) {
                cache([$key => $response], $seconds);
            }

            return $response;
        };

        return $this;
    }

    public function throttle(int $maxAttempts = 60, int $decayMinutes = 1, ?string $key = null): self
    {
        return $this->middleware("throttle:{$maxAttempts},{$decayMinutes}" . ($key ? ",{$key}" : ''));
    }

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

    public function getAllMetadata(): array
    {
        return $this->metadata;
    }

    public function matches(string $method, string $path): bool
    {
        if ($this->method !== strtoupper($method)) {
            return false;
        }

        return $this->matchesPath($path);
    }

    /**
     * Match only on path (skip method check).
     * Use when routes are already grouped by HTTP method in the Router.
     */
    public function matchesPath(string $path): bool
    {
        if ($this->domain !== null && $this->domainPattern !== null) {
            $host = $_SERVER['HTTP_HOST'] ?? '';
            if (!preg_match($this->domainPattern, $host)) {
                return false;
            }
        }

        if ($this->scheme !== null) {
            $isSecure = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
                || ($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '') === 'https'
                || (int) ($_SERVER['HTTP_X_FORWARDED_PORT'] ?? 0) === 443;

            $requestScheme = $isSecure ? 'https' : 'http';

            if ($this->scheme !== $requestScheme) {
                return false;
            }
        }

        return preg_match($this->pattern, $path) === 1;
    }

    public function extractParameters(string $path): array
    {
        if (!preg_match($this->pattern, $path, $matches)) {
            return [];
        }

        $params = array_filter($matches, 'is_string', ARRAY_FILTER_USE_KEY);

        foreach ($this->defaults as $key => $value) {
            if (!isset($params[$key]) || $params[$key] === '') {
                $params[$key] = $value;
            }
        }

        return $params;
    }

    public function url(array $parameters = [], bool $absolute = false): string
    {
        if ($this->router !== null) {
            return $this->router->getUrlGenerator()->generate($this, $parameters, $absolute);
        }

        $generator = new RouteUrlGenerator();
        return $generator->generate($this, $parameters, $absolute);
    }

    public function hasMiddleware(string $middleware): bool
    {
        return in_array($middleware, $this->middleware, true);
    }

    public function getSignature(): string
    {
        $name = $this->name ? " [{$this->name}]" : '';

        return "{$this->method} {$this->path}{$name}";
    }

    public function isNamed(): bool
    {
        return $this->name !== null;
    }

    public function toArray(): array
    {
        return [
            'method' => $this->method,
            'path' => $this->path,
            'name' => $this->name,
            'middleware' => $this->middleware,
            'where' => $this->where,
            'defaults' => $this->defaults,
            'domain' => $this->domain,
            'scheme' => $this->scheme,
            'metadata' => $this->metadata,
        ];
    }
}
