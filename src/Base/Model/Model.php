<?php

declare(strict_types=1);

namespace Plugs;

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

abstract class Model
{
    protected static $table;
    protected static $primaryKey = 'id';
    protected $attributes = [];
    protected $original = [];
    
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
    
    public static function where(string $column, string $operator, $value): QueryBuilder
    {
        return static::query()->where($column, $operator, $value);
    }
    
    public static function create(array $attributes): self
    {
        $model = new static($attributes);
        $model->save();
        return $model;
    }
    
    public function save(): bool
    {
        if ($this->exists()) {
            return $this->update();
        }
        
        return $this->insert();
    }
    
    private function insert(): bool
    {
        $data = $this->attributes;
        
        if (property_exists($this, 'timestamps') && $this->timestamps) {
            $data['created_at'] = date('Y-m-d H:i:s');
            $data['updated_at'] = date('Y-m-d H:i:s');
        }
        
        $result = static::query()->insert($data);
        
        if ($result) {
            $connection = Connection::getInstance();
            $this->attributes[static::$primaryKey] = $connection->lastInsertId();
            $this->original = $this->attributes;
        }
        
        return $result;
    }
    
    private function update(): bool
    {
        $dirty = $this->getDirty();
        
        if (empty($dirty)) {
            return true;
        }
        
        if (property_exists($this, 'timestamps') && $this->timestamps) {
            $dirty['updated_at'] = date('Y-m-d H:i:s');
        }
        
        $result = static::query()
            ->where(static::$primaryKey, '=', $this->attributes[static::$primaryKey])
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
            ->where(static::$primaryKey, '=', $this->attributes[static::$primaryKey])
            ->delete();
    }
    
    public function fill(array $attributes): self
    {
        foreach ($attributes as $key => $value) {
            $this->attributes[$key] = $value;
        }
        
        return $this;
    }
    
    public function getAttribute(string $key)
    {
        return $this->attributes[$key] ?? null;
    }
    
    public function setAttribute(string $key, $value): void
    {
        $this->attributes[$key] = $value;
    }
    
    public function exists(): bool
    {
        return isset($this->attributes[static::$primaryKey]);
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
    
    public function toArray(): array
    {
        return $this->attributes;
    }
    
    public function __get($key)
    {
        return $this->getAttribute($key);
    }
    
    public function __set($key, $value)
    {
        $this->setAttribute($key, $value);
    }
    
    protected static function getTable(): string
    {
        return static::$table ?? strtolower(
            preg_replace('/([a-z])([A-Z])/', '$1_$2', class_basename(static::class))
        ) . 's';
    }
}