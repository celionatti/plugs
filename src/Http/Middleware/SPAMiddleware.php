<?php

declare(strict_types=1);

namespace Plugs\Http\Middleware;

use Plugs\Container\Container;
use Plugs\View\ViewEngineInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;

class SPAMiddleware implements MiddlewareInterface
{
    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        if ($request->hasHeader('X-Plugs-SPA')) {
            $container = Container::getInstance();
            if ($container->bound(ViewEngineInterface::class)) {
                $viewEngine = $container->make(ViewEngineInterface::class);
                $viewEngine->suppressLayout(true);

                // Handle specific section requests
                if ($request->hasHeader('X-Plugs-Section')) {
                    $viewEngine->requestSection($request->getHeaderLine('X-Plugs-Section'));
                }
            }
        }

        return $handler->handle($request);
    }
}
