<?php

declare(strict_types=1);

namespace Plugs\Base;

/*
|--------------------------------------------------------------------------
| Base Page Class
|--------------------------------------------------------------------------
|
| Base class for all file-based page routes.
| Provides HTTP method routing, response helpers, and middleware support.
|
| Page classes should extend this and implement HTTP method handlers
| like get(), post(), put(), delete(), etc.
*/

use Plugs\Http\ResponseFactory;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use RuntimeException;

abstract class Page
{
    /**
     * Request instance
     */
    protected ?ServerRequestInterface $request = null;

    /**
     * Invoke the page - called by the router
     */
    public function __invoke(ServerRequestInterface $request): ResponseInterface
    {
        $this->request = $request;

        // Get the HTTP method
        $method = strtolower($request->getMethod());

        // Map HEAD to GET
        if ($method === 'head') {
            $method = 'get';
        }

        // Check if the method handler exists
        if (method_exists($this, $method)) {
            return $this->callMethodWithParameters($method, $request);
        }

        // Check for a fallback handle() method
        if (method_exists($this, 'handle')) {
            return $this->callMethodWithParameters('handle', $request);
        }

        // Method not allowed
        return $this->methodNotAllowed($method);
    }

    /**
     * Call a method with automatic parameter injection
     */
    private function callMethodWithParameters(string $method, ServerRequestInterface $request): ResponseInterface
    {
        $reflection = new \ReflectionMethod($this, $method);
        $parameters = [];

        foreach ($reflection->getParameters() as $param) {
            $paramName = $param->getName();
            $paramType = $param->getType();

            // Inject ServerRequestInterface
            if ($paramType instanceof \ReflectionNamedType && !$paramType->isBuiltin()) {
                $typeName = $paramType->getName();

                if ($typeName === ServerRequestInterface::class || is_subclass_of($typeName, ServerRequestInterface::class)) {
                    $parameters[] = $request;

                    continue;
                }
            }

            // Get route parameters
            $routeParams = $request->getAttribute('_route_params', []);

            // Check if parameter exists in route
            if (array_key_exists($paramName, $routeParams)) {
                $value = $routeParams[$paramName];

                // Type casting
                if ($paramType instanceof \ReflectionNamedType && $paramType->isBuiltin()) {
                    $value = $this->castValue($value, $paramType->getName());
                }

                $parameters[] = $value;

                continue;
            }

            // Check request attributes
            $attrValue = $request->getAttribute($paramName);
            if ($attrValue !== null) {
                $parameters[] = $attrValue;

                continue;
            }

            // Check if parameter has default value
            if ($param->isDefaultValueAvailable()) {
                $parameters[] = $param->getDefaultValue();

                continue;
            }

            // Check if parameter is nullable
            if ($paramType && $paramType->allowsNull()) {
                $parameters[] = null;

                continue;
            }

            // Cannot resolve parameter
            throw new RuntimeException(
                "Cannot resolve parameter [{$paramName}] for method [{$method}] in " . static::class
            );
        }

        return $this->$method(...$parameters);
    }

    /**
     * Cast value to specified type
     */
    private function castValue($value, string $type)
    {
        return match ($type) {
            'int' => (int) $value,
            'float' => (float) $value,
            'string' => (string) $value,
            'bool' => (bool) $value,
            default => $value,
        };
    }

    /**
     * HTTP Method Handlers (Optional - override in child classes)
     */

    /**
     * Handle OPTIONS requests
     */
    protected function options(): ResponseInterface
    {
        return $this->json([
            'allowed_methods' => $this->getAllowedMethods(),
        ]);
    }

    /**
     * Middleware to apply to this page
     */
    public function middleware(): array
    {
        return [];
    }

    /**
     * Get allowed HTTP methods for this page
     */
    protected function getAllowedMethods(): array
    {
        $methods = [];

        foreach (['get', 'post', 'put', 'delete', 'patch', 'options', 'head'] as $method) {
            if (method_exists($this, $method)) {
                $methods[] = strtoupper($method);
            }
        }

        return $methods ?: ['OPTIONS'];
    }

    /**
     * Response Helpers
     */

    /**
     * Render a view
     */
    protected function render(string $view, array $data = [], int $status = 200): ResponseInterface
    {
        // Use the View system
        if (function_exists('view')) {
            $content = view($view, $data);

            return ResponseFactory::html($content, $status);
        }

        throw new RuntimeException('View helper function not available');
    }

    /**
     * Return JSON response
     */
    protected function json(array $data, int $status = 200, array $headers = []): ResponseInterface
    {
        return ResponseFactory::json($data, $status, $headers);
    }

    /**
     * Return plain text response
     */
    protected function text(string $content, int $status = 200): ResponseInterface
    {
        return ResponseFactory::text($content, $status);
    }

    /**
     * Return HTML response
     */
    protected function html(string $content, int $status = 200): ResponseInterface
    {
        return ResponseFactory::html($content, $status);
    }

    /**
     * Redirect to a URL
     */
    protected function redirect(string $url, int $status = 302): ResponseInterface
    {
        return ResponseFactory::redirect($url, $status);
    }

    /**
     * Redirect to a named route
     */
    protected function redirectToRoute(string $name, array $parameters = [], int $status = 302): ResponseInterface
    {
        if (function_exists('route')) {
            $url = route($name, $parameters);

            return $this->redirect($url, $status);
        }

        throw new RuntimeException('Route helper function not available');
    }

    /**
     * Return a 404 Not Found response
     */
    protected function notFound(string $message = 'Not Found'): ResponseInterface
    {
        return $this->json([
            'error' => $message,
        ], 404);
    }

    /**
     * Return a 403 Forbidden response
     */
    protected function forbidden(string $message = 'Forbidden'): ResponseInterface
    {
        return $this->json([
            'error' => $message,
        ], 403);
    }

    /**
     * Return a 401 Unauthorized response
     */
    protected function unauthorized(string $message = 'Unauthorized'): ResponseInterface
    {
        return $this->json([
            'error' => $message,
        ], 401);
    }

    /**
     * Return a 500 Internal Server Error response
     */
    protected function error(string $message = 'Internal Server Error', int $status = 500): ResponseInterface
    {
        return $this->json([
            'error' => $message,
        ], $status);
    }

    /**
     * Return a 405 Method Not Allowed response
     */
    protected function methodNotAllowed(string $method): ResponseInterface
    {
        $allowed = $this->getAllowedMethods();
        $jsonData = json_encode([
            'error' => "Method {$method} not allowed",
            'allowed_methods' => $allowed,
        ]);

        $stream = new \Plugs\Http\Message\Stream(fopen('php://temp', 'r+'));
        $stream->write($jsonData);
        $stream->rewind();

        return ResponseFactory::createResponse(405)
            ->withHeader('Allow', implode(', ', $allowed))
            ->withHeader('Content-Type', 'application/json')
            ->withBody($stream);
    }

    /**
     * Request Helpers
     */

    /**
     * Get a request parameter (supports route params, query params, and body params)
     */
    protected function input(string $key, $default = null)
    {
        if (!$this->request) {
            return $default;
        }

        // Check route parameters first
        $routeParams = $this->request->getAttribute('_route_params', []);
        if (array_key_exists($key, $routeParams)) {
            return $routeParams[$key];
        }

        // Check query parameters
        $queryParams = $this->request->getQueryParams();
        if (array_key_exists($key, $queryParams)) {
            return $queryParams[$key];
        }

        // Check body parameters
        $parsedBody = $this->request->getParsedBody();
        if (is_array($parsedBody) && array_key_exists($key, $parsedBody)) {
            return $parsedBody[$key];
        }

        return $default;
    }

    /**
     * Get all request data
     */
    protected function all(): array
    {
        if (!$this->request) {
            return [];
        }

        $data = [];

        // Merge all sources
        $data = array_merge(
            $data,
            $this->request->getAttribute('_route_params', []),
            $this->request->getQueryParams(),
            is_array($this->request->getParsedBody()) ? $this->request->getParsedBody() : []
        );

        return $data;
    }

    /**
     * Check if request has a parameter
     */
    protected function has(string $key): bool
    {
        return $this->input($key) !== null;
    }

    /**
     * Get the current request
     */
    protected function request(): ?ServerRequestInterface
    {
        return $this->request;
    }
}
