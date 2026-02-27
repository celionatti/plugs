<?php

declare(strict_types=1);

namespace Plugs\Http;

/*
|--------------------------------------------------------------------------
| MiddlewareDispatcher Class
|--------------------------------------------------------------------------
|
| This class manages and executes the middleware stack.
| It features a "Compiled Pipeline" for maximum speed and a 
| "Priority Registry" for guaranteed security middleware execution order.
*/

use Plugs\Http\Middleware\MiddlewareRegistry;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;

class MiddlewareDispatcher implements RequestHandlerInterface
{
    private array $middlewareStack = [];
    private ?MiddlewareRegistry $registry = null;
    private ?\Closure $compiledPipeline = null;
    private $fallbackHandler;
    private ?\Plugs\Debug\Profiler $profiler;

    public function __construct(array $middleware = [], ?callable $fallbackHandler = null, ?MiddlewareRegistry $registry = null)
    {
        $this->middlewareStack = $middleware;
        $this->fallbackHandler = $fallbackHandler;
        $this->profiler = \Plugs\Debug\Profiler::getInstance();
        $this->registry = $registry;

        if ($this->registry === null) {
            $this->resolveRegistry();
        }
    }


    private function resolveRegistry(): void
    {
        if (function_exists('app') && app()->has(MiddlewareRegistry::class)) {
            $this->registry = app(MiddlewareRegistry::class);
        } else {
            $this->registry = new MiddlewareRegistry();
        }
    }

    public function add($middleware): void
    {
        $this->middlewareStack[] = $middleware;
        $this->compiledPipeline = null; // Invalidate compiled cache
    }

    public function setFallbackHandler(callable $handler): void
    {
        $this->fallbackHandler = $handler;
        $this->compiledPipeline = null;
    }

    public function handle(ServerRequestInterface $request): ResponseInterface
    {
        if ($this->compiledPipeline === null) {
            $this->compile();
        }

        return ($this->compiledPipeline)($request);
    }

    /**
     * Compiles the middleware stack into a single nested closure chain.
     * This "Fast Path" avoids array iteration and index tracking on every request.
     */
    private function compile(): void
    {
        // 1. Resolve aliases and groups through registry
        $resolvedRows = [];
        foreach ($this->middlewareStack as $mw) {
            if (is_string($mw)) {
                foreach ($this->registry->resolve($mw) as $resolvedClass) {
                    $resolvedRows[] = $resolvedClass;
                }
            } else {
                $resolvedRows[] = $mw;
            }
        }

        // 2. Add Kernel (Global) middlewares if they aren't already present
        $kernel = $this->registry->getKernel();
        foreach ($kernel as $kmw) {
            if (!in_array($kmw, $resolvedRows, true)) {
                array_unshift($resolvedRows, $kmw);
            }
        }

        // 3. Sort by priority (Security-First)
        // 3. Orchestrate by Layer + Priority
        $orchestrated = $this->registry->orchestrate($resolvedRows);

        // Flatten back to a single list for the closure chain
        $sorted = [];
        foreach ($orchestrated as $layerMiddleware) {
            foreach ($layerMiddleware as $mw) {
                if (!in_array($mw, $sorted, true)) {
                    $sorted[] = $mw;
                }
            }
        }

        // 4. Build the closure chain from bottom (fallback) to top
        $next = function (ServerRequestInterface $req) {
            if ($this->fallbackHandler === null) {
                throw new \RuntimeException('No middleware returned response and no fallback handler set');
            }
            return ($this->fallbackHandler)($req);
        };

        // Reuse a single Handler wrapper for all PSR-15 middlewares
        $handlerFactory = function ($nextCallable) {
            return new class ($nextCallable) implements RequestHandlerInterface {
                private $cb;
                public function __construct($cb)
                {
                    $this->cb = $cb; }
                public function handle(ServerRequestInterface $request): ResponseInterface
                {
                    return ($this->cb)($request);
                }
            };
        };

        foreach (array_reverse($sorted) as $mw) {
            $next = $this->wrapMiddleware($mw, $next, $handlerFactory);
        }

        $this->compiledPipeline = $next;
    }

    /**
     * Wraps a middleware in a closure with optional profiling.
     */
    private function wrapMiddleware($mw, \Closure $next, \Closure $handlerFactory): \Closure
    {
        return function (ServerRequestInterface $request) use ($mw, $next, $handlerFactory) {
            $params = [];
            $middleware = $mw;

            // Parse parameters if it's a string like "Class:param1,param2"
            if (is_string($middleware) && strpos($middleware, ':') !== false) {
                [$middleware, $paramString] = explode(':', $middleware, 2);
                $params = explode(',', $paramString);
            }

            // Lazy load string-based middleware
            $instance = is_string($middleware) ? app($middleware) : $middleware;

            // Inject parameters if supported
            if (!empty($params) && method_exists($instance, 'setParameters')) {
                $instance->setParameters($params);
            }

            if ($this->profiler && $this->profiler->isEnabled()) {
                $name = is_string($middleware) ? $middleware : get_class($middleware);
                $label = 'MW: ' . basename(str_replace('\\', '/', $name));

                $this->profiler->startSegment('mw_' . $name, $label);
                try {
                    return $this->execute($instance, $request, $next, $handlerFactory);
                } finally {
                    $this->profiler->stopSegment('mw_' . $name);
                }
            }

            return $this->execute($instance, $request, $next, $handlerFactory);
        };
    }


    /**
     * Executes a single middleware instance.
     */
    private function execute($mw, ServerRequestInterface $request, \Closure $next, \Closure $handlerFactory): ResponseInterface
    {
        if ($mw instanceof MiddlewareInterface) {
            return $mw->process($request, $handlerFactory($next));
        }

        if (is_callable($mw)) {
            return $mw($request, $next);
        }

        if (is_object($mw) && method_exists($mw, 'handle')) {
            return $mw->handle($request, $next);
        }

        throw new \RuntimeException(sprintf('Middleware must implement %s or be callable', MiddlewareInterface::class));
    }
}

