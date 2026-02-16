<?php

declare(strict_types=1);

namespace Plugs\Database\Traits;

use Plugs\Database\Collection;
use Plugs\Database\Connection;
use Plugs\Database\QueryBuilder;
use Plugs\Paginator\Pagination;

/**
 * @phpstan-consistent-constructor
 * @phpstan-ignore-next-line
 */
trait HasQueryBuilder
{
    /**
     * @phpstan-consistent-constructor
     */
    public static function query(): QueryBuilder
    {
        $connection = Connection::getInstance();

        // Search for a dedicated Query class in the same namespace or sub-namespace
        $modelClass = static::class;
        $queryClass = $modelClass . 'Query';

        if (class_exists($queryClass)) {
            $builder = new $queryClass($connection);
        } else {
            $builder = new QueryBuilder($connection);
        }

        /** @phpstan-ignore new.static */
        $instance = new static();
        $table = $instance->getTable();

        $builder = $builder->table($table)->setModel(static::class);

        // Apply global scopes
        if (method_exists($instance, 'applyGlobalScopes')) {
            /** @phpstan-ignore-next-line */
            $builder = $instance->applyGlobalScopes($builder);
        }

        return $builder;
    }

    public static function get(array $columns = ['*']): array|Collection
    {
        return static::query()->get($columns);
    }

    public static function all(array $columns = ['*']): Collection
    {
        $results = static::query()->all($columns);

        return $results instanceof Collection ? $results : new Collection($results);
    }

    /**
     * Get all results as a standardized API response.
     */
    public static function allResponse(array $columns = ['*'], int $status = 200, ?string $message = null): \Plugs\Http\StandardResponse
    {
        return static::query()->allResponse($columns, $status, $message);
    }

    /**
     * Get a standardized API response for a collection.
     */
    public static function getResponse(array $columns = ['*'], int $status = 200, ?string $message = null): \Plugs\Http\StandardResponse
    {
        return static::query()->allResponse($columns, $status, $message);
    }

    /**
     * Begin querying a model with eager loads.
     *
     * @param  array|string  $relations
     * @return QueryBuilder
     */
    public static function with($relations): QueryBuilder
    {
        return static::query()->with($relations);
    }

    public static function find($id, array $columns = ['*'])
    {
        return static::query()->find($id, $columns);
    }

    /**
     * Find a model and return as a standardized API response.
     */
    public static function findResponse($id, array $columns = ['*'], int $status = 200, ?string $message = null): \Plugs\Http\StandardResponse
    {
        return static::query()->findResponse($id, $columns, $status, $message);
    }

    public static function findOrFail($id, array $columns = ['*'])
    {
        return static::query()->findOrFail($id, $columns);
    }

    public static function findMany(array $ids, array $columns = ['*']): array|Collection
    {
        return static::query()->findMany($ids, $columns);
    }

    public static function first(array $columns = ['*'])
    {
        return static::query()->first($columns);
    }

    /**
     * Get the first result as a standardized API response.
     */
    public static function firstResponse(array $columns = ['*'], int $status = 200, ?string $message = null): \Plugs\Http\StandardResponse
    {
        return static::query()->firstResponse($columns, $status, $message);
    }

    public static function firstOrFail(array $columns = ['*'])
    {
        return static::query()->firstOrFail($columns);
    }

    public static function insert(array|object $data): Collection|self
    {
        /** @phpstan-ignore new.static */
        $instance = new static();
        $data = $instance->parseAttributes($data);

        // Check if data is multidimensional numeric array (bulk insert)
        $isBulk = isset($data[0]) && is_array($data[0]);

        if (!$isBulk) {
            return static::create($data);
        }

        // Bulk insert
        static::query()->insert($data);

        $models = array_map(function ($attributes) {
            /** @phpstan-ignore new.static */
            return new static($attributes);
        }, $data);

        return new Collection($models);
    }

    /**
     * Get the only record that matches the criteria.
     * Throws exception if no record found or more than one record found.
     */
    public static function sole(array $columns = ['*']): self
    {
        return static::query()->sole($columns);
    }

    /**
     * Chunk the results of the query.
     */
    public static function chunk(int $count, callable $callback): bool
    {
        return static::query()->chunk($count, $callback);
    }

    /**
     * Chunk the results of the query by ID.
     * More efficient than chunk() for large datasets.
     *
     * @param int $count Number of records per chunk
     * @param callable $callback Function to process each chunk
     * @param string|null $column The column to use for chunking
     * @param string|null $alias The alias for the column
     * @return bool
     */
    public static function chunkById(int $count, callable $callback, ?string $column = 'id', ?string $alias = null): bool
    {
        return static::query()->chunkById($count, $callback, $column, $alias);
    }

    public static function where(string $column, $operator = null, $value = null): QueryBuilder
    {
        return static::query()->where($column, $operator, $value);
    }

    public static function whereIn(string $column, array $values): QueryBuilder
    {
        return static::query()->whereIn($column, $values);
    }

    public static function nestedWhere(\Closure $callback, string $boolean = 'AND'): QueryBuilder
    {
        return static::query()->nestedWhere($callback, $boolean);
    }

    public static function whereHas(string $relation, ?\Closure $callback = null): QueryBuilder
    {
        return static::query()->whereHas($relation, $callback);
    }

    public static function whereDoesntHave(string $relation, ?\Closure $callback = null): QueryBuilder
    {
        return static::query()->whereDoesntHave($relation, $callback);
    }

    public static function when($value, callable $callback, callable $default = null): QueryBuilder
    {
        return static::query()->when($value, $callback, $default);
    }

    public static function unless($value, callable $callback, callable $default = null): QueryBuilder
    {
        return static::query()->unless($value, $callback, $default);
    }

    public static function orderBy(string $column, string $direction = 'asc'): QueryBuilder
    {
        return static::query()->orderBy($column, $direction);
    }

    public static function latest(string $column = 'created_at'): QueryBuilder
    {
        return static::query()->latest($column);
    }

    public static function oldest(string $column = 'created_at'): QueryBuilder
    {
        return static::query()->oldest($column);
    }

    public static function limit(int $limit): QueryBuilder
    {
        return static::query()->limit($limit);
    }

    public static function offset(int $offset): QueryBuilder
    {
        return static::query()->offset($offset);
    }

    public static function count(string $column = '*'): int
    {
        return static::query()->count($column);
    }

    public static function recordExists(): bool
    {
        return static::count() > 0;
    }

    /**
     * Paginate results (Production Ready)
     *
     * @return Pagination
     */
    public static function paginate(int $perPage = 15, ?int $page = null, array $columns = ['*']): Pagination
    {
        return static::query()->paginate($perPage, $page, $columns);
    }

    /**
     * Paginate results and return a Pagination object
     *
     * @alias paginate
     */
    public static function paginateLinks(int $perPage = 15, ?int $page = null, array $columns = ['*']): Pagination
    {
        return static::paginate($perPage, $page, $columns);
    }

    /**
     * Get paginated results as a standardized API response.
     */
    public static function paginateResponse(int $perPage = 15, ?int $page = null, array $columns = ['*']): \Plugs\Http\StandardResponse
    {
        return static::query()->paginateResponse($perPage, $page, $columns);
    }

    /**
     * Simple paginate (lighter, no total count)
     */
    public static function simplePaginate(int $perPage = 15, ?int $page = null, array $columns = ['*']): array
    {
        return static::query()->simplePaginate($perPage, $page, $columns);
    }

    /**
     * Get current page from request
     */
    protected static function getCurrentPage(): int
    {
        return (int) ($_GET['page'] ?? $_REQUEST['page'] ?? 1);
    }

    /**
     * Apply filters from request parameters or a QueryFilter instance
     */
    public static function filter(array|\Plugs\Database\Filters\QueryFilter $params): QueryBuilder
    {
        return static::query()->filter($params);
    }

    /**
     * Search and paginate with request parameters
     *
     * @return Pagination
     */
    public static function search(?array $params = null): Pagination
    {
        return static::query()->search($params);
    }

    /**
     * Search and paginate results as a standardized API response.
     */
    public static function searchResponse(?array $params = null): \Plugs\Http\StandardResponse
    {
        return static::query()->searchResponse($params);
    }
}
