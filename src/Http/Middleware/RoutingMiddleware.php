<?php

declare(strict_types=1);

namespace Plugs\Http\Middleware;

/*
|--------------------------------------------------------------------------
| RoutingMiddleware Class
|--------------------------------------------------------------------------
|
| This middleware handles routing for incoming requests.
*/

use Plugs\Container\Container;
use Plugs\Router\Router;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;

class RoutingMiddleware implements MiddlewareInterface
{
    private $router;

    public function __construct(Router $router)
    {
        $this->router = $router;
    }

    public function process(
        ServerRequestInterface $request,
        RequestHandlerInterface $handler
    ): ResponseInterface {
        $response = $this->router->dispatch($request);

        if ($response !== null) {
            return $response;
        }

        return $handler->handle($request);
    }
}
