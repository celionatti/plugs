<?php

declare(strict_types=1);

namespace Plugs\Database\Contracts;

use Plugs\Database\QueryBuilder;

interface QueryMiddleware
{
    /**
     * Handle the query builder.
     *
     * @param  \Plugs\Database\QueryBuilder $builder
     * @param  \Closure $next
     * @return mixed
     */
    public function handle(QueryBuilder $builder, \Closure $next);
}
