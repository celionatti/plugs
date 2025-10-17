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

use Plugs\Router\Router;
use Plugs\Container\Container;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;

class RoutingMiddleware implements MiddlewareInterface
{
    private $router;
    private $container;
    
    public function __construct(Router $router, Container $container)
    {
        $this->router = $router;
        $this->container = $container;
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