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

use Plugs\Database\Collection;
use Plugs\Database\Connection;
use Plugs\Database\QueryBuilder;
use Plugs\Database\Traits\HasQueryBuilder;
use Plugs\Paginator\Pagination;

/**
 * @phpstan-consistent-constructor
 */
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

    public static function all(array $columns = ['*']): array|Collection
    {
        return static::get($columns);
    }

    public static function find($id, array $columns = ['*'])
    {
        return static::query()->find($id, $columns);
    }

    public static function findOrFail($id, array $columns = ['*']): self
    {
        $result = static::find($id, $columns);
        if (!$result) {
            throw new \Exception("Model not found with id: {$id}");
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

    public static function firstOrFail(array $columns = ['*']): self
    {
        $result = static::first($columns);
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
     * Get searchable columns - override this in your model
     */
    protected function getSearchableColumns(): array
    {
        return $this->searchableColumns;
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
        /** @phpstan-ignore property.staticAccess */
        return static::$table ?? strtolower(
            preg_replace('/([a-z])([A-Z])/', '$1_$2', class_basename(static::class))
        ) . 's';
    }

    /**
     * Parse attributes from array or object.
     */
    protected function parseAttributes(array|object $attributes): array
    {
        if (is_object($attributes)) {
            if (method_exists($attributes, 'toArray')) {
                return $attributes->toArray();
            }

            return (array) $attributes;
        }

        return $attributes;
    }
}
