<?php

declare(strict_types=1);

namespace Plugs\Database\Middleware;

use Plugs\Database\Contracts\QueryMiddleware;
use Plugs\Database\QueryBuilder;
use Closure;

class SoftDeleteMiddleware implements QueryMiddleware
{
    public function handle(QueryBuilder $builder, Closure $next)
    {
        // Only apply if the table has a deleted_at column
        // In a real scenario, we might check the model if it uses SoftDeletes trait
        $builder->whereNull('deleted_at');

        return $next($builder);
    }
}
