<?php

declare(strict_types=1);

namespace Plugs\Database\Traits;

use Plugs\Database\Collection;
use Plugs\Database\Connection;
use Plugs\Database\QueryBuilder;
use Plugs\Paginator\Pagination;

trait HasQueryBuilder
{
    public static function query(): QueryBuilder
    {
        $connection = Connection::getInstance();
        $builder = new QueryBuilder($connection);

        $instance = new static();
        $table = $instance->getTable();

        return $builder->table($table)->setModel(static::class);
    }

    public static function get(array $columns = ['*']): array|Collection
    {
        $results = static::query()->get($columns);

        return is_array($results) ? new Collection($results) : $results;
    }

    public static function all(array $columns = ['*']): Collection
    {
        return static::get($columns);
    }

    /**
     * Get all results as a standardized API response.
     */
    public static function allResponse(array $columns = ['*'], int $status = 200, ?string $message = null): \Plugs\Http\StandardResponse
    {
        return static::all($columns)->toResponse($status, $message);
    }

    /**
     * Get a standardized API response for a collection.
     */
    public static function getResponse(array $columns = ['*'], int $status = 200, ?string $message = null): \Plugs\Http\StandardResponse
    {
        return static::get($columns)->toResponse($status, $message);
    }

    /**
     * Begin querying a model with eager loads.
     *
     * @param  array|string  $relations
     * @return QueryBuilder
     */
    public static function with($relations): QueryBuilder
    {
        $relations = is_string($relations) ? func_get_args() : $relations;

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
        $result = static::find($id, $columns);

        if (!$result) {
            return \Plugs\Http\StandardResponse::error("Record not found", 404);
        }

        return $result->toResponse($status, $message);
    }

    public static function findOrFail($id, array $columns = ['*'])
    {
        $result = static::find($id, $columns);
        if (!$result) {
            throw (new \Plugs\Database\Exception\ModelNotFoundException())->setModel(static::class, $id);
        }

        return $result;
    }

    public static function findMany(array $ids, array $columns = ['*']): array|Collection
    {
        return static::query()->whereIn('id', $ids)->get($columns);
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
        $result = static::first($columns);

        if (!$result) {
            return \Plugs\Http\StandardResponse::error("No records found", 404);
        }

        return $result->toResponse($status, $message);
    }

    public static function firstOrFail(array $columns = ['*'])
    {
        $result = static::first($columns);
        if (!$result) {
            throw new \Exception("No records found");
        }

        return $result;
    }

    public static function insert(array|object $data): Collection|self
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
     * Paginate results (Production Ready)
     * 
     * @return Pagination
     */
    public static function paginate(int $perPage = 15, ?int $page = null, array $columns = ['*']): Pagination
    {
        $maxPerPage = defined('static::MAX_PER_PAGE') ? static::MAX_PER_PAGE : 100;
        $perPage = max(1, min($perPage, $maxPerPage));

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
        $paginator = static::paginate($perPage, $page, $columns);
        $paginated = $paginator->toArray();

        // Extract meta and links manually since toArray is flat
        $meta = [
            'total' => $paginated['total'],
            'per_page' => $paginated['per_page'],
            'current_page' => $paginated['current_page'],
            'last_page' => $paginated['last_page'],
            'from' => $paginated['from'],
            'to' => $paginated['to'],
            'path' => $paginated['path'],
        ];

        $links = [
            'first' => $paginated['first_page_url'],
            'last' => $paginated['last_page_url'],
            'prev' => $paginated['prev_page_url'],
            'next' => $paginated['next_page_url'],
        ];

        return \Plugs\Http\StandardResponse::success($paginated['data'])
            ->withMeta($meta)
            ->withLinks($links);
    }

    /**
     * Simple paginate (lighter, no total count)
     */
    public static function simplePaginate(int $perPage = 15, ?int $page = null): array
    {
        // Enforce a sensible maximum
        $maxPerPage = defined('static::MAX_PER_PAGE') ? static::MAX_PER_PAGE : 100;
        $perPage = max(1, min($perPage, $maxPerPage));

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
            if ($items instanceof Collection) {
                $items = new Collection(array_slice($items->all(), 0, $perPage, true));
            } else {
                array_pop($items);
            }
        }

        $data = $items instanceof Collection ? $items->all() : $items;

        // Generate absolute URLs for links
        $baseUrl = function_exists('currentUrl') ? currentUrl(includeQuery: false) : '';
        $queryParams = $_GET;
        unset($queryParams['page']);

        $buildUrl = function ($p) use ($baseUrl, $queryParams) {
            if (!$p)
                return null;
            $params = array_merge($queryParams, ['page' => $p]);
            return $baseUrl . '?' . http_build_query($params);
        };

        return [
            'data' => $data,
            'meta' => [
                'per_page' => $perPage,
                'current_page' => $page,
                'from' => count($data) > 0 ? $offset + 1 : 0,
                'to' => $offset + count($data),
                'has_more' => $hasMore,
                'path' => $baseUrl,
            ],
            'links' => [
                'next' => $hasMore ? $buildUrl($page + 1) : null,
                'prev' => $page > 1 ? $buildUrl($page - 1) : null,
            ]
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
     * Apply filters from request parameters or a QueryFilter instance
     */
    public static function filter(array|\Plugs\Database\Filters\QueryFilter $params): QueryBuilder
    {
        $query = static::query();

        // Handle QueryFilter instance
        if ($params instanceof \Plugs\Database\Filters\QueryFilter) {
            return $params->apply($query);
        }

        // Legacy array-based filtering
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
     *
     * @return Pagination
     */
    public static function search(?array $params = null): Pagination
    {
        $params = $params ?? $_GET ?? $_REQUEST ?? [];

        // Enforce a sensible maximum
        $maxPerPage = defined('static::MAX_PER_PAGE') ? static::MAX_PER_PAGE : 100;
        $perPage = (int) ($params['per_page'] ?? 15);
        $perPage = max(1, min($perPage, $maxPerPage));

        $page = (int) ($params['page'] ?? 1);

        $query = static::filter($params);

        $paginator = $query->paginate($perPage, $page);
        $paginator->appends(array_filter($params, fn($k) => !in_array($k, ['page', 'per_page']), ARRAY_FILTER_USE_KEY));

        return $paginator;
    }


    /**
     * Search and paginate results as a standardized API response.
     */
    public static function searchResponse(?array $params = null): \Plugs\Http\StandardResponse
    {
        $paginator = static::search($params);
        $paginated = $paginator->toArray();

        // Extract meta and links manually since toArray is flat
        $meta = [
            'total' => $paginated['total'],
            'per_page' => $paginated['per_page'],
            'current_page' => $paginated['current_page'],
            'last_page' => $paginated['last_page'],
            'from' => $paginated['from'],
            'to' => $paginated['to'],
            'path' => $paginated['path'],
            // specific to search, filters might be needed, but Pagination object doesn't carry them explicitly in toArray usually unless added
            // Pagination has 'filters' only if we added it? 
            // In Model::search and HasQueryBuilder::search, I added $paginator->appends(...)
            // Pagination appends to query string, but does it put it in toArray?
            // Pagination::toArray() does NOT include 'filters' key.
            // If API consumers need 'filters', I might need to check if Pagination allows retrieving them.
            // Pagination::getCurrentQuery() returns them.
            // But for now, let's stick to standard meta.
        ];

        $links = [
            'first' => $paginated['first_page_url'],
            'last' => $paginated['last_page_url'],
            'prev' => $paginated['prev_page_url'],
            'next' => $paginated['next_page_url'],
        ];

        return \Plugs\Http\StandardResponse::success($paginated['data'])
            ->withMeta($meta)
            ->withLinks($links);
    }
}
