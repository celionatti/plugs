<?php

declare(strict_types=1);

namespace Plugs\Http\Middleware;

use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;

class ForceJsonMiddleware implements MiddlewareInterface
{
    /**
     * Process an incoming server request.
     */
    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        // Force the Accept header to application/json so the framework
        // treats it as an API request (e.g. for error handling)
        $request = $request->withHeader('Accept', 'application/json');

        $response = $handler->handle($request);

        // Ensure the Content-Type header is set to application/json
        if (!$response->hasHeader('Content-Type')) {
            return $response->withHeader('Content-Type', 'application/json');
        }

        return $response;
    }
}
