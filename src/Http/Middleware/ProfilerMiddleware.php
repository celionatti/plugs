<?php

declare(strict_types=1);

namespace Plugs\Http\Middleware;

use Plugs\Debug\Profiler;
use Plugs\Debug\ProfilerBar;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;
use Plugs\Http\Middleware\MiddlewareLayer;
use Plugs\Http\Middleware\Middleware;

/**
 * Profiler Middleware
 *
 * Profiles request performance and optionally injects a profiler bar into HTML responses.
 */
#[Middleware(layer: MiddlewareLayer::PERFORMANCE, priority: 10)]
class ProfilerMiddleware implements MiddlewareInterface
{
    private bool $injectBar;

    public function __construct(bool $injectBar = true)
    {
        $this->injectBar = $injectBar;
    }

    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        $path = $request->getUri()->getPath();

        // Don't profile the profiler dashboard itself
        if (str_starts_with($path, '/plugs/profiler')) {
            return $handler->handle($request);
        }

        $profiler = Profiler::getInstance();
        $profiler->start();

        $response = $handler->handle($request);

        // Match route from router if attributes are missing (fallback)
        $routeName = $request->getAttribute('_route_name') ?? $request->getAttribute('_route')?->getName();
        $controller = $request->getAttribute('_controller');

        if ($routeName === null || $controller === null) {
            $router = \Plugs\Container\Container::getInstance()->get(\Plugs\Router\Router::class);
            $currentRoute = $router->getCurrentRoute();
            if ($currentRoute) {
                $routeName = $routeName ?? $currentRoute->getName();
                $controller = $controller ?? $currentRoute->getHandler();
            }
        }

        // Collect profile data
        $profile = $profiler->stop([
            'method' => $request->getMethod(),
            'uri' => (string) $request->getUri(),
            'path' => $path,
            'params' => $request->getQueryParams(),
            'body' => $request->getParsedBody(),
            'ip' => $this->getClientIp($request),
            'status_code' => $response->getStatusCode(),
            'route' => $routeName ?? 'unnamed',
            'controller' => is_string($controller) ? $controller : (
                is_array($controller) ? (
                    (is_object($controller[0] ?? null) ? get_class($controller[0]) : ($controller[0] ?? 'unknown')) .
                    '@' .
                    ($controller[1] ?? 'index')
                ) : (is_object($controller) ? get_class($controller) : 'Closure')
            ),
        ]);

        // Inject profiler bar into HTML responses if enabled
        if ($this->injectBar && $this->shouldInjectBar($response)) {
            // Get nonce from AssetManager if available
            $nonce = function_exists('asset_manager') ? asset_manager()->getNonce() : null;
            $response = $this->injectProfilerBar($response, $profile, $nonce);
        }

        return $response;
    }

    /**
     * Check if we should inject the profiler bar
     */
    private function shouldInjectBar(ResponseInterface $response): bool
    {
        $contentType = $response->getHeaderLine('Content-Type');

        // Only inject into HTML responses
        if (stripos($contentType, 'text/html') === false) {
            return false;
        }

        // Don't inject into redirects
        $statusCode = $response->getStatusCode();
        if ($statusCode >= 300 && $statusCode < 400) {
            return false;
        }

        return true;
    }

    /**
     * Inject profiler bar into response body
     */
    private function injectProfilerBar(ResponseInterface $response, array $profile, ?string $nonce = null): ResponseInterface
    {
        $body = (string) $response->getBody();

        // Only inject if there's a closing body tag
        if (stripos($body, '</body>') === false) {
            return $response;
        }

        $modifiedBody = ProfilerBar::injectIntoHtml($body, $profile, $nonce);

        // Create new response with modified body
        $resource = fopen('php://temp', 'r+');
        fwrite($resource, $modifiedBody);
        rewind($resource);
        $newBody = new \Plugs\Http\Message\Stream($resource);

        return $response
            ->withBody($newBody)
            ->withoutHeader('Content-Length'); // Remove content-length as body changed
    }

    /**
     * Get client IP from request
     */
    private function getClientIp(ServerRequestInterface $request): string
    {
        // Check for forwarded headers
        $forwardedFor = $request->getHeaderLine('X-Forwarded-For');
        if ($forwardedFor) {
            $ips = explode(',', $forwardedFor);

            return trim($ips[0]);
        }

        $realIp = $request->getHeaderLine('X-Real-IP');
        if ($realIp) {
            return $realIp;
        }

        $serverParams = $request->getServerParams();

        return $serverParams['REMOTE_ADDR'] ?? 'unknown';
    }
}
