<?php

declare(strict_types=1);

namespace Plugs\Database;

use BadMethodCallException;
use Plugs\Database\QueryBuilder;

/**
 * A proxy class for query scopes to improve discoverability.
 */
class ScopeProxy
{
    /**
     * Create a new scope proxy instance.
     *
     * @param QueryBuilder $builder
     */
    public function __construct(protected QueryBuilder $builder)
    {
    }

    /**
     * Get the underlying query builder.
     *
     * @return QueryBuilder
     */
    public function getBuilder(): QueryBuilder
    {
        return $this->builder;
    }

    /**
     * Dynamically call a scope.
     *
     * @param string $method
     * @param array $parameters
     * @return QueryBuilder
     */
    public function __call(string $method, array $parameters): QueryBuilder
    {
        return $this->builder->$method(...$parameters);
    }
}
