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
        return (new MiddlewareRunner($this->middlewareStack, $this->fallbackHandler))->handle($request);
    }
}

/**
 * Lightweight PSR-15 runner for the middleware stack.
 * 
 * @internal
 */
class MiddlewareRunner implements RequestHandlerInterface
{
    private array $stack;
    private int $index = 0;
    private $fallback;

    public function __construct(array $stack, $fallback)
    {
        $this->stack = $stack;
        $this->fallback = $fallback;
    }

    public function handle(ServerRequestInterface $request): ResponseInterface
    {
        if (!isset($this->stack[$this->index])) {
            if ($this->fallback === null) {
                throw new \RuntimeException('No middleware returned response and no fallback handler set');
            }

            return ($this->fallback)($request);
        }

        $middleware = $this->stack[$this->index];
        $this->index++;

        // Lazy resolution
        if (is_string($middleware)) {
            $middleware = app($middleware);
        }

        if (!$middleware instanceof MiddlewareInterface) {
            throw new \RuntimeException(sprintf('Middleware must implement %s', MiddlewareInterface::class));
        }

        $profiler = \Plugs\Debug\Profiler::getInstance();
        $mwName = get_class($middleware);
        $profiler->startSegment('mw_' . $mwName, 'MW: ' . basename(str_replace('\\', '/', $mwName)));

        try {
            return $middleware->process($request, $this);
        } finally {
            $profiler->stopSegment('mw_' . $mwName);
        }
    }
}
