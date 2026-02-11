<?php

declare(strict_types=1);

namespace Plugs\Database\Contracts;

use Plugs\Database\QueryBuilder;

interface GlobalScope
{
    /**
     * Apply the scope to a given query builder.
     *
     * @param  \Plugs\Database\QueryBuilder  $builder
     * @param  \Plugs\Base\Model\PlugModel  $model
     * @return void
     */
    public function apply(QueryBuilder $builder, $model): void;
}
