<?php

declare(strict_types=1);

namespace Plugs\Http;

/*
|--------------------------------------------------------------------------
| MiddlewareDispatcher Class
|--------------------------------------------------------------------------
|
| This class is responsible for dispatching middleware in the HTTP request
| handling process. It manages the execution of middleware components and
| ensures that each middleware is called in the correct order.
*/

use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;

class MiddlewareDispatcher implements RequestHandlerInterface
{
    private $middlewareStack = [];
    private $fallbackHandler;

    public function __construct(array $middleware = [], ?callable $fallbackHandler = null)
    {
        $this->middlewareStack = $middleware;
        $this->fallbackHandler = $fallbackHandler;
    }

    public function add(MiddlewareInterface $middleware): void
    {
        $this->middlewareStack[] = $middleware;
    }

    public function setFallbackHandler(callable $handler): void
    {
        $this->fallbackHandler = $handler;
    }

    public function handle(ServerRequestInterface $request): ResponseInterface
    {
        $stack = $this->middlewareStack;
        $fallback = $this->fallbackHandler;

        // Create a handler that will process the remaining middleware
        $runner = new class($stack, $fallback) implements RequestHandlerInterface {
            private $stack;
            private $index = 0;
            private $fallbackHandler;

            public function __construct(array $stack, ?callable $fallbackHandler)
            {
                $this->stack = $stack;
                $this->fallbackHandler = $fallbackHandler;
            }

            public function handle(ServerRequestInterface $request): ResponseInterface
            {
                // If no more middleware, use fallback handler
                if (!isset($this->stack[$this->index])) {
                    if ($this->fallbackHandler === null) {
                        throw new \RuntimeException('No middleware returned response and no fallback handler set');
                    }
                    return ($this->fallbackHandler)($request);
                }

                $middleware = $this->stack[$this->index];
                $this->index++;

                return $middleware->process($request, $this);
            }
        };

        return $runner->handle($request);
    }
}