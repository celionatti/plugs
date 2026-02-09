<?php

declare(strict_types=1);

namespace Plugs\Database\Retention;

use Plugs\Database\QueryBuilder;

interface RetentionRuleInterface
{
    /**
     * Apply the retention rule to the query builder.
     *
     * @param  QueryBuilder  $query
     * @return QueryBuilder
     */
    public function apply(QueryBuilder $query): QueryBuilder;
}
