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
    private array $middlewareStack = [];
    private array $resolvedStack = [];
    private bool $resolved = false;
    private $fallbackHandler;

    public function __construct(array $middleware = [], ?callable $fallbackHandler = null)
    {
        $this->middlewareStack = $middleware;
        $this->fallbackHandler = $fallbackHandler;
    }

    public function add($middleware): void
    {
        $this->middlewareStack[] = $middleware;
        $this->resolved = false; // Invalidate cache
    }

    public function setFallbackHandler(callable $handler): void
    {
        $this->fallbackHandler = $handler;
    }

    public function handle(ServerRequestInterface $request): ResponseInterface
    {
        if (!$this->resolved) {
            $this->resolveStack();
        }

        return (new MiddlewareRunner($this->resolvedStack, $this->fallbackHandler))->handle($request);
    }

    private function resolveStack(): void
    {
        $this->resolvedStack = [];

        foreach ($this->middlewareStack as $i => $middleware) {
            // lazy resolution of string classes
            if (is_string($middleware)) {
                $middleware = app($middleware);
            }

            if (!$middleware instanceof MiddlewareInterface) {
                // If it's a closure or callable, wrap it? For now assume strict typing or fail.
                // But let's throw friendly error
                $type = is_object($middleware) ? get_class($middleware) : gettype($middleware);
                throw new \RuntimeException(sprintf('Middleware at index %d (%s) must implement %s', $i, $type, MiddlewareInterface::class));
            }

            // Pre-calculate display name for profiler
            $mwName = get_class($middleware);
            $displayName = basename(str_replace('\\', '/', $mwName));

            $this->resolvedStack[] = [
                'instance' => $middleware,
                'name' => 'mw_' . $mwName,
                'label' => 'MW: ' . $displayName
            ];
        }

        $this->resolved = true;
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
    private ?\Plugs\Debug\Profiler $profiler;

    public function __construct(array $stack, $fallback)
    {
        $this->stack = $stack;
        $this->fallback = $fallback;

        $this->profiler = \Plugs\Debug\Profiler::getInstance();
    }

    public function handle(ServerRequestInterface $request): ResponseInterface
    {
        if (!isset($this->stack[$this->index])) {
            if ($this->fallback === null) {
                throw new \RuntimeException('No middleware returned response and no fallback handler set');
            }

            return ($this->fallback)($request);
        }

        // Get pre-resolved middleware data
        $entry = $this->stack[$this->index];
        $middleware = $entry['instance'];
        $this->index++;

        // Fast path if profiler is disabled
        if (!$this->profiler->isEnabled()) {
            return $middleware->process($request, $this);
        }

        // Profiler path
        $this->profiler->startSegment($entry['name'], $entry['label']);

        try {
            return $middleware->process($request, $this);
        } finally {
            $this->profiler->stopSegment($entry['name']);
        }
    }
}
