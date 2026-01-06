<?php

declare(strict_types=1);

namespace Plugs\Database\Traits;

use Plugs\Database\Connection;
use Plugs\Database\QueryBuilder;
use Plugs\Database\Collection;

trait HasQueryBuilder
{
    public static function query(): QueryBuilder
    {
        $connection = Connection::getInstance();
        $builder = new QueryBuilder($connection);

        $table = method_exists(static::class, 'getTableName')
            ? static::getTableName()
            : static::getTable();

        return $builder->table($table);
    }

    public static function all(): array
    {
        $results = static::query()->get();
        return array_map(fn($item) => new static($item), $results);
    }

    public static function find($id)
    {
        $result = static::query()->find($id);
        return $result ? new static($result) : null;
    }

    public static function findOrFail($id)
    {
        $result = static::find($id);
        if (!$result) {
            throw new \Exception("Model not found with id: {$id}");
        }
        return $result;
    }

    public static function findMany(array $ids): array
    {
        $results = static::query()->whereIn('id', $ids)->get();
        return array_map(fn($item) => new static($item), $results);
    }

    public static function first()
    {
        $result = static::query()->first();
        return $result ? new static($result) : null;
    }

    public static function firstOrFail()
    {
        $result = static::first();
        if (!$result) {
            throw new \Exception("No records found");
        }
        return $result;
    }

    public static function insert(array|object $data)
    {
        $data = (new static())->parseAttributes($data);

        // Check if data is multidimensional numeric array (bulk insert)
        $isBulk = isset($data[0]) && is_array($data[0]);

        if (!$isBulk) {
            return static::create($data);
        }

        // Bulk insert
        static::query()->insert($data);

        $models = array_map(function ($attributes) {
            return new static($attributes);
        }, $data);

        return new Collection($models);
    }

    public static function where(string $column, string $operator, $value = null): QueryBuilder
    {
        if ($value === null) {
            $value = $operator;
            $operator = '=';
        }
        return static::query()->where($column, $operator, $value);
    }

    public static function whereIn(string $column, array $values): QueryBuilder
    {
        return static::query()->whereIn($column, $values);
    }

    public static function orderBy(string $column, string $direction = 'asc'): QueryBuilder
    {
        return static::query()->orderBy($column, strtoupper($direction));
    }

    public static function latest(string $column = 'created_at'): QueryBuilder
    {
        return static::query()->orderBy($column, 'DESC');
    }

    public static function oldest(string $column = 'created_at'): QueryBuilder
    {
        return static::query()->orderBy($column, 'ASC');
    }

    public static function limit(int $limit): QueryBuilder
    {
        return static::query()->limit($limit);
    }

    public static function offset(int $offset): QueryBuilder
    {
        return static::query()->offset($offset);
    }

    public static function count(): int
    {
        return static::query()->count();
    }

    public static function recordExists(): bool
    {
        return static::count() > 0;
    }

    /**
     * Paginate results
     */
    public static function paginate(int $perPage = 15, ?int $page = null, array $columns = ['*']): array
    {
        $page = $page ?? static::getCurrentPage();
        $page = max(1, $page);

        $total = static::query()->count();
        $offset = ($page - 1) * $perPage;

        $items = static::query()
            ->select($columns)
            ->limit($perPage)
            ->offset($offset)
            ->get();

        $data = array_map(fn($item) => new static($item), $items);

        return [
            'data' => $data,
            'total' => $total,
            'per_page' => $perPage,
            'current_page' => $page,
            'last_page' => (int) ceil($total / $perPage),
            'from' => $offset + 1,
            'to' => min($offset + $perPage, $total),
        ];
    }

    /**
     * Simple paginate (lighter, no total count)
     */
    public static function simplePaginate(int $perPage = 15, ?int $page = null): array
    {
        $page = $page ?? static::getCurrentPage();
        $page = max(1, $page);

        $offset = ($page - 1) * $perPage;

        // Fetch one extra to determine if there's a next page
        $items = static::query()
            ->limit($perPage + 1)
            ->offset($offset)
            ->get();

        $hasMore = count($items) > $perPage;
        if ($hasMore) {
            array_pop($items);
        }

        $data = array_map(fn($item) => new static($item), $items);

        return [
            'data' => $data,
            'per_page' => $perPage,
            'current_page' => $page,
            'has_more' => $hasMore,
            'from' => $offset + 1,
            'to' => $offset + count($data),
        ];
    }

    /**
     * Get current page from request
     */
    protected static function getCurrentPage(): int
    {
        return (int) ($_GET['page'] ?? $_REQUEST['page'] ?? 1);
    }

    /**
     * Apply filters from request parameters
     */
    public static function filter(array $params): QueryBuilder
    {
        $query = static::query();
        $instance = new static();

        foreach ($params as $key => $value) {
            // Skip empty values and pagination params
            if ($value === null || $value === '' || $key === 'page' || $key === 'per_page' || $key === 'direction') {
                continue;
            }

            // Handle search parameters
            if ($key === 'search') {
                if (method_exists($instance, 'getSearchableColumns')) {
                    $searchColumns = $instance->getSearchableColumns();
                    if (!empty($searchColumns)) {
                        foreach ($searchColumns as $column) {
                            $query->orWhere($column, 'LIKE', "%{$value}%");
                        }
                    }
                }
                continue;
            }

            // Handle sort parameters
            if ($key === 'sort') {
                $direction = strtoupper($params['direction'] ?? 'ASC');
                $query->orderBy($value, $direction);
                continue;
            }

            // Regular where clause
            if (is_array($value)) {
                $query->whereIn($key, $value);
            } else {
                $query->where($key, '=', $value);
            }
        }

        return $query;
    }

    /**
     * Search and paginate with request parameters
     */
    public static function search(?array $params = null): array
    {
        $params = $params ?? $_GET ?? $_REQUEST ?? [];

        $perPage = (int) ($params['per_page'] ?? 15);
        $perPage = max(1, min($perPage, 100)); // Limit between 1 and 100
        $page = (int) ($params['page'] ?? 1);
        $page = max(1, $page);

        $query = static::filter($params);

        $total = $query->count();
        $offset = ($page - 1) * $perPage;

        $items = $query
            ->limit($perPage)
            ->offset($offset)
            ->get();

        $data = array_map(fn($item) => new static($item), $items);

        return [
            'data' => $data,
            'total' => $total,
            'per_page' => $perPage,
            'current_page' => $page,
            'last_page' => (int) ceil($total / $perPage),
            'filters' => array_filter($params, fn($k) => !in_array($k, ['page', 'per_page']), ARRAY_FILTER_USE_KEY),
        ];
    }
}
