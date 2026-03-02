<?php

declare(strict_types=1);

namespace Plugs\Database;

use Closure;
use Plugs\Database\Contracts\QueryMiddleware;

class QueryPipeline
{
    /**
     * Send a query builder through a stack of middleware.
     *
     * @param  \Plugs\Database\QueryBuilder $builder
     * @param  array $middleware
     * @param  \Closure $destination
     * @return mixed
     */
    public static function send(QueryBuilder $builder, array $middleware, Closure $destination)
    {
        if (empty($middleware)) {
            return $destination($builder);
        }

        $pipeline = array_reduce(
            array_reverse($middleware),
            function ($next, $middlewareInstance) {
                return function ($builder) use ($next, $middlewareInstance) {
                    return static::execute($middlewareInstance, $builder, $next);
                };
            },
            $destination
        );

        return $pipeline($builder);
    }

    /**
     * Execute a single middleware.
     *
     * @param  mixed $middleware
     * @param  \Plugs\Database\QueryBuilder $builder
     * @param  \Closure $next
     * @return mixed
     */
    protected static function execute($middleware, QueryBuilder $builder, Closure $next)
    {
        if ($middleware instanceof Closure) {
            return $middleware($builder, $next);
        }

        if (is_string($middleware)) {
            // Resolve from container if available, else instantiate
            $instance = function_exists('app') ? app($middleware) : new $middleware();
        } else {
            $instance = $middleware;
        }

        if ($instance instanceof QueryMiddleware) {
            return $instance->handle($builder, $next);
        }

        if (is_callable($instance)) {
            return $instance($builder, $next);
        }

        // If it's an object with handle method but not implementing interface
        if (is_object($instance) && method_exists($instance, 'handle')) {
            return $instance->handle($builder, $next);
        }

        throw new \InvalidArgumentException(sprintf(
            'Middleware must be a Closure, implement %s, or be a class name with a handle method.',
            QueryMiddleware::class
        ));
    }
}
