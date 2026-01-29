<?php

declare(strict_types=1);

namespace Plugs\Database\Filters;

use Plugs\Database\QueryBuilder;
use Psr\Http\Message\ServerRequestInterface;

abstract class QueryFilter
{
    /**
     * @var ServerRequestInterface
     */
    protected $request;

    /**
     * @var QueryBuilder
     */
    protected $builder;

    /**
     * @var array
     */
    protected $filters = [];

    /**
     * QueryFilter constructor.
     *
     * @param ServerRequestInterface $request
     */
    public function __construct(ServerRequestInterface $request)
    {
        $this->request = $request;
    }

    /**
     * Apply the filters to the builder.
     *
     * @param QueryBuilder $builder
     * @return QueryBuilder
     */
    public function apply(QueryBuilder $builder): QueryBuilder
    {
        $this->builder = $builder;

        foreach ($this->getFilters() as $filter => $value) {
            if (method_exists($this, $filter)) {
                $this->$filter($value);
            }
        }

        return $this->builder;
    }

    /**
     * Get all applicable filters from the request.
     *
     * @return array
     */
    protected function getFilters(): array
    {
        return array_filter($this->request->getQueryParams(), function ($value) {
            return $value !== null && $value !== '';
        });
    }

    /**
     * Sort the query.
     *
     * @param string $column
     * @return void
     */
    public function sort(string $column): void
    {
        $direction = $this->request->getQueryParams()['direction'] ?? 'ASC';
        $this->builder->orderBy($column, strtoupper($direction));
    }
}
