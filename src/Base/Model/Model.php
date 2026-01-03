<?php

declare(strict_types=1);

namespace Plugs\Base\Model;

/*
|--------------------------------------------------------------------------
| Model Class
|--------------------------------------------------------------------------
|
| This is a base Model class providing Active Record style ORM functionality.
| It allows for easy interaction with the database, including querying,
| inserting, updating, and deleting records.
*/

use Plugs\Database\Connection;
use Plugs\Database\QueryBuilder;
use Plugs\Database\Traits\HasQueryBuilder;

abstract class Model
{
    use HasQueryBuilder;

    protected $table;
    protected $primaryKey = 'id';
    protected $attributes = [];
    protected $original = [];
    protected $fillable = [];
    protected $guarded = ['*'];
    protected $hidden = [];
    protected $casts = [];
    protected $searchableColumns = [];

    public function __construct(array $attributes = [])
    {
        $this->fill($attributes);
        $this->original = $this->attributes;
    }

    public static function query(): QueryBuilder
    {
        $connection = Connection::getInstance();
        $builder = new QueryBuilder($connection);
        return $builder->table(static::getTable());
    }

    public static function all(): array
    {
        $results = static::query()->get();
        return array_map(fn($item) => new static($item), $results);
    }

    public static function find($id): ?self
    {
        $result = static::query()->find($id);
        return $result ? new static($result) : null;
    }

    public static function findOrFail($id): self
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

    public static function first(): ?self
    {
        $result = static::query()->first();
        return $result ? new static($result) : null;
    }

    public static function firstOrFail(): self
    {
        $result = static::first();
        if (!$result) {
            throw new \Exception("No records found");
        }
        return $result;
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
     * 
     * @param int $perPage Items per page
     * @param int|null $page Current page number
     * @param array $columns Columns to select
     * @return array Pagination data
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
                $searchColumns = $instance->getSearchableColumns();
                if (!empty($searchColumns)) {
                    foreach ($searchColumns as $column) {
                        $query->orWhere($column, 'LIKE', "%{$value}%");
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
     * Get searchable columns - override this in your model
     */
    protected function getSearchableColumns(): array
    {
        return $this->searchableColumns;
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

    public static function create(array $attributes): self
    {
        $model = new static($attributes);
        $model->save();
        return $model;
    }

    public static function updateOrCreate(array $attributes, array $values = []): self
    {
        $query = static::query();

        foreach ($attributes as $key => $value) {
            $query->where($key, '=', $value);
        }

        $result = $query->first();

        if ($result) {
            $model = new static($result);
            $model->fill($values);
            $model->save();
            return $model;
        }

        return static::create(array_merge($attributes, $values));
    }

    public static function firstOrCreate(array $attributes): self
    {
        $query = static::query();

        foreach ($attributes as $key => $value) {
            $query->where($key, '=', $value);
        }

        $result = $query->first();

        if ($result) {
            return new static($result);
        }

        return static::create($attributes);
    }

    public function save(): bool
    {
        if ($this->exists()) {
            return $this->performUpdate();
        }

        return $this->performInsert();
    }

    private function performInsert(): bool
    {
        $data = $this->attributes;

        if (property_exists($this, 'timestamps') && $this->timestamps) {
            $data['created_at'] = date('Y-m-d H:i:s');
            $data['updated_at'] = date('Y-m-d H:i:s');
        }

        $result = static::query()->insert($data);

        if ($result) {
            $connection = Connection::getInstance();
            $this->attributes[$this->primaryKey] = $connection->lastInsertId();
            $this->original = $this->attributes;
        }

        return $result;
    }

    private function performUpdate(): bool
    {
        $dirty = $this->getDirty();

        if (empty($dirty)) {
            return true;
        }

        if (property_exists($this, 'timestamps') && $this->timestamps) {
            $dirty['updated_at'] = date('Y-m-d H:i:s');
        }

        $result = static::query()
            ->where($this->primaryKey, '=', $this->attributes[$this->primaryKey])
            ->update($dirty);

        if ($result) {
            $this->original = $this->attributes;
        }

        return $result;
    }

    public function delete(): bool
    {
        if (!$this->exists()) {
            return false;
        }

        return static::query()
            ->where($this->primaryKey, '=', $this->attributes[$this->primaryKey])
            ->delete();
    }

    public static function destroy($ids): int
    {
        $ids = is_array($ids) ? $ids : func_get_args();
        $count = 0;

        foreach ($ids as $id) {
            $model = static::find($id);
            if ($model && $model->delete()) {
                $count++;
            }
        }

        return $count;
    }

    public function refresh(): self
    {
        if (!$this->exists()) {
            throw new \Exception("Cannot refresh a model that doesn't exist");
        }

        $fresh = static::find($this->attributes[$this->primaryKey]);

        if (!$fresh) {
            throw new \Exception("Model no longer exists in database");
        }

        $this->attributes = $fresh->attributes;
        $this->original = $fresh->original;

        return $this;
    }

    public function fill(array $attributes): self
    {
        foreach ($attributes as $key => $value) {
            if ($this->isFillable($key)) {
                $this->attributes[$key] = $value;
            }
        }

        return $this;
    }

    protected function isFillable(string $key): bool
    {
        if (!empty($this->fillable)) {
            return in_array($key, $this->fillable);
        }

        if (in_array('*', $this->guarded)) {
            return false;
        }

        return !in_array($key, $this->guarded);
    }

    public function getAttribute(string $key)
    {
        $value = $this->attributes[$key] ?? null;

        // Apply casts
        if (isset($this->casts[$key]) && $value !== null) {
            return $this->castAttribute($key, $value);
        }

        return $value;
    }

    protected function castAttribute(string $key, $value)
    {
        $castType = $this->casts[$key];

        switch ($castType) {
            case 'int':
            case 'integer':
                return (int) $value;
            case 'float':
            case 'double':
                return (float) $value;
            case 'string':
                return (string) $value;
            case 'bool':
            case 'boolean':
                return (bool) $value;
            case 'array':
            case 'json':
                return is_string($value) ? json_decode($value, true) : $value;
            case 'date':
            case 'datetime':
                return new \DateTime($value);
            default:
                return $value;
        }
    }

    public function setAttribute(string $key, $value): void
    {
        $this->attributes[$key] = $value;
    }

    public function exists(): bool
    {
        return isset($this->attributes[$this->primaryKey]);
    }

    private function getDirty(): array
    {
        $dirty = [];

        foreach ($this->attributes as $key => $value) {
            if (!array_key_exists($key, $this->original) || $this->original[$key] !== $value) {
                $dirty[$key] = $value;
            }
        }

        return $dirty;
    }

    public function isDirty(?string $key = null): bool
    {
        $dirty = $this->getDirty();

        if ($key === null) {
            return !empty($dirty);
        }

        return array_key_exists($key, $dirty);
    }

    public function getOriginal(?string $key = null)
    {
        if ($key === null) {
            return $this->original;
        }

        return $this->original[$key] ?? null;
    }

    public function toArray(): array
    {
        $array = $this->attributes;

        // Remove hidden attributes
        foreach ($this->hidden as $key) {
            unset($array[$key]);
        }

        return $array;
    }

    public function toJson(): string
    {
        return json_encode($this->toArray());
    }

    public function __get($key)
    {
        return $this->getAttribute($key);
    }

    public function __set($key, $value)
    {
        $this->setAttribute($key, $value);
    }

    public function __isset($key)
    {
        return isset($this->attributes[$key]);
    }

    public function __unset($key)
    {
        unset($this->attributes[$key]);
    }

    protected static function getTable(): string
    {
        return static::$table ?? strtolower(
            preg_replace('/([a-z])([A-Z])/', '$1_$2', class_basename(static::class))
        ) . 's';
    }
}
