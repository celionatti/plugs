<?php

declare(strict_types=1);

namespace Plugs\Http\Middleware;

use Plugs\Facades\Auth;
use Plugs\Http\ResponseFactory;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;

class AuthenticateMiddleware implements MiddlewareInterface
{
    protected string $redirectTo = '/login';

    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        if (Auth::guest()) {
            if ($this->shouldReturnJson($request)) {
                return ResponseFactory::json(['message' => 'Unauthenticated.'], 401);
            }

            return ResponseFactory::redirect($this->redirectTo);
        }

        return $handler->handle($request);
    }

    protected function shouldReturnJson(ServerRequestInterface $request): bool
    {
        return $request->hasHeader('Accept') && strpos($request->getHeaderLine('Accept'), 'application/json') !== false;
    }
}
