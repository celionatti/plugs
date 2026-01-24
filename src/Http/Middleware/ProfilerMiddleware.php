<?php

declare(strict_types=1);

namespace Plugs\Http\Middleware;

use Plugs\Debug\Profiler;
use Plugs\Debug\ProfilerBar;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;

/**
 * Profiler Middleware
 *
 * Profiles request performance and optionally injects a profiler bar into HTML responses.
 */
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

        // Track middleware segment
        $profiler->startSegment('middleware', 'Middleware');

        $response = $handler->handle($request);

        $profiler->stopSegment('middleware');

        // Collect profile data
        $profile = $profiler->stop([
            'method' => $request->getMethod(),
            'uri' => (string) $request->getUri(),
            'path' => $path,
            'params' => $request->getQueryParams(),
            'body' => $request->getParsedBody(),
            'ip' => $this->getClientIp($request),
            'status_code' => $response->getStatusCode(),
            'route' => $request->getAttribute('_route')?->getName() ?? null,
        ]);

        // Inject profiler bar into HTML responses if enabled
        if ($this->injectBar && $this->shouldInjectBar($response)) {
            $response = $this->injectProfilerBar($response, $profile);
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
    private function injectProfilerBar(ResponseInterface $response, array $profile): ResponseInterface
    {
        $body = (string) $response->getBody();

        // Only inject if there's a closing body tag
        if (stripos($body, '</body>') === false) {
            return $response;
        }

        $modifiedBody = ProfilerBar::injectIntoHtml($body, $profile);

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
