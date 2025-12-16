<?php

declare(strict_types=1);

namespace Plugs\Http\Middleware;

use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;
use Plugs\Debug\Profiler;

class ProfilerMiddleware implements MiddlewareInterface
{
    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        // Don't profile the profiler dashboard itself to avoid infinite feedback loops or noise
        $path = $request->getUri()->getPath();
        if (str_starts_with($path, '/debug/performance')) {
            return $handler->handle($request);
        }

        $profiler = Profiler::getInstance();
        $profiler->start();

        $response = $handler->handle($request);

        $profiler->stop([
            'method' => $request->getMethod(),
            'uri' => (string) $request->getUri(),
            'path' => $request->getUri()->getPath(),
            'params' => $request->getQueryParams(),
            'ip' => $_SERVER['REMOTE_ADDR'] ?? 'unknown',
            'status_code' => $response->getStatusCode(),
        ]);

        return $response;
    }
}
