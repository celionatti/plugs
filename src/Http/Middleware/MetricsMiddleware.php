<?php

declare(strict_types=1);

namespace Plugs\Http\Middleware;

use Plugs\Metrics\MetricsCollector;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;

/**
 * Middleware to track request metrics.
 */
class MetricsMiddleware implements MiddlewareInterface
{
    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        // Start tracking
        MetricsCollector::startRequest();

        // Process request
        $response = $handler->handle($request);

        // End tracking
        $route = $request->getAttribute('_route', $request->getUri()->getPath());
        $method = $request->getMethod();
        $statusCode = $response->getStatusCode();

        MetricsCollector::endRequest($route, $method, $statusCode);

        return $response;
    }
}
