<?php

declare(strict_types=1);

namespace Plugs\Database\Traits;

use Plugs\Database\Filters\QueryFilter;
use Plugs\Database\QueryBuilder;

trait Filterable
{
    /**
     * Scope a query to use query filters.
     *
     * @param QueryBuilder $query
     * @param QueryFilter $filters
     * @return QueryBuilder
     */
    public function scopeFilter(QueryBuilder $query, QueryFilter $filters): QueryBuilder
    {
        return $filters->apply($query);
    }
}
