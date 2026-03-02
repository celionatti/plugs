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
        $cacheKey = $this->registry->getCacheKey($this->middlewareStack, $this->getCurrentContext());
        $sorted = \Plugs\Facades\Cache::get($cacheKey);

        if ($sorted === null) {
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
            $kernel = $this->registry->getKernel($this->getCurrentContext());
            foreach ($kernel as $kmw) {
                if (!in_array($kmw, $resolvedRows, true)) {
                    array_unshift($resolvedRows, $kmw);
                }
            }

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

            // Cache for 24 hours (or until manual clear) - skip if contains non-string items
            // Closures and objects with state are not safely serializable in all drivers.
            $isCacheable = true;
            foreach ($sorted as $mw) {
                if (!is_string($mw)) {
                    $isCacheable = false;
                    break;
                }
            }

            if ($isCacheable) {
                \Plugs\Facades\Cache::set($cacheKey, $sorted, 86400);
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
                public function __construct(private $cb)
                {}
                public function handle(ServerRequestInterface $request): ResponseInterface
                {
                    return ($this->cb)($request);
                }
            };
        };

        $isProfilerEnabled = $this->profiler && $this->profiler->isEnabled();

        foreach (array_reverse($sorted) as $mw) {
            // Pre-parse parameters if available
            $params = [];
            if (is_string($mw) && strpos($mw, ':') !== false) {
                [$mw, $paramString] = explode(':', $mw, 2);
                $params = explode(',', $paramString);
            }

            $next = $this->wrapMiddleware($mw, $params, $next, $handlerFactory, $isProfilerEnabled);
        }

        $this->compiledPipeline = $next;
    }

    private function getCurrentContext(): ?\Plugs\Bootstrap\ContextType
    {
        return function_exists('app_context') ? app_context() : \Plugs\Bootstrap\ContextType::Web;
    }

    /**
     * Wraps a middleware in a closure with optional profiling.
     */
    private function wrapMiddleware($mw, array $params, \Closure $next, \Closure $handlerFactory, bool $isProfilerEnabled): \Closure
    {
        return function (ServerRequestInterface $request) use ($mw, $params, $next, $handlerFactory, $isProfilerEnabled) {
            // Lazy load string-based middleware
            $instance = is_string($mw) ? app($mw) : $mw;

            // Inject parameters if supported
            if (!empty($params) && method_exists($instance, 'setParameters')) {
                $instance->setParameters($params);
            }

            if ($isProfilerEnabled) {
                $name = is_string($mw) ? $mw : get_class($mw);
                $label = 'MW: ' . basename(str_replace('\\', '/', $name)) . ' (Inclusive)';

                // Start inclusive segment
                $this->profiler->startSegment('mw_' . $name, $label);

                try {
                    // Record start of execution for potential exclusive timing calculation
                    $response = $this->execute($instance, $request, $next, $handlerFactory);
                    return $response;
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

