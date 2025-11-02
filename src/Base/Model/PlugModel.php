<?php

declare(strict_types=1);

namespace Plugs\Base\Model;

use PDO;
use PDOException;
use Plugs\Database\Collection;

abstract class PlugModel
{
    protected static $connection;
    protected $table;
    protected $primaryKey = 'id';
    protected $fillable = [];
    protected $guarded = ['*'];
    protected $hidden = [];
    protected $casts = [];
    protected $timestamps = true;
    protected $attributes = [];
    protected $original = [];
    protected $relations = [];
    protected $exists = false;
    protected $softDelete = false;
    protected $deletedAtColumn = 'deleted_at';

    // Query Builder Properties
    protected $query = [
        'select' => ['*'],
        'where' => [],
        'joins' => [],
        'orderBy' => [],
        'groupBy' => [],
        'having' => [],
        'limit' => null,
        'offset' => null,
        'withTrashed' => false
    ];

    // Model events
    protected static $booted = [];
    protected static $globalScopes = [];

    public function __construct(array $attributes = [])
    {
        $this->bootIfNotBooted();
        $this->fill($attributes);
        if (!$this->table) {
            $this->table = $this->getTableName();
        }
    }

    /**
     * Boot model (only once per class)
     */
    protected function bootIfNotBooted()
    {
        $class = static::class;
        if (!isset(static::$booted[$class])) {
            static::$booted[$class] = true;
            static::boot();
        }
    }

    /**
     * Bootstrap the model
     */
    protected static function boot()
    {
        // Override in child classes to register events, scopes, etc.
    }

    /**
     * Set database connection from array parameters
     * 
     * @param array $config Database configuration array
     *        Expected keys: driver, host, port, database, username, password, charset, options
     * @throws \Exception if connection fails
     */
    public static function setConnection(array $config)
    {
        $driver = $config['driver'] ?? 'mysql';
        $host = $config['host'] ?? 'localhost';
        $port = $config['port'] ?? 3306;
        $database = $config['database'] ?? '';
        $username = $config['username'] ?? 'root';
        $password = $config['password'] ?? '';
        $charset = $config['charset'] ?? 'utf8mb4';
        $options = $config['options'] ?? [];

        // Default PDO options
        $defaultOptions = [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES => false,
        ];

        // Merge with custom options
        $pdoOptions = array_merge($defaultOptions, $options);

        try {
            // Build DSN based on driver
            switch ($driver) {
                case 'mysql':
                    $dsn = "mysql:host={$host};port={$port};dbname={$database};charset={$charset}";
                    break;
                case 'pgsql':
                    $dsn = "pgsql:host={$host};port={$port};dbname={$database}";
                    break;
                case 'sqlite':
                    $dsn = "sqlite:{$database}";
                    break;
                case 'sqlsrv':
                    $dsn = "sqlsrv:Server={$host},{$port};Database={$database}";
                    break;
                default:
                    throw new \Exception("Unsupported database driver: {$driver}");
            }

            static::$connection = new PDO($dsn, $username, $password, $pdoOptions);
        } catch (PDOException $e) {
            throw new \Exception("Database connection failed: " . $e->getMessage());
        }
    }

    /**
     * Get database connection
     */
    protected static function getConnection()
    {
        if (!static::$connection) {
            throw new \Exception('Database connection not set. Use Model::setConnection($config)');
        }
        return static::$connection;
    }

    /**
     * Get table name from class name
     */
    protected function getTableName()
    {
        $className = (new \ReflectionClass($this))->getShortName();
        return strtolower($className) . 's';
    }

    /**
     * Set custom table name
     */
    public function setTable($table)
    {
        $this->table = $table;
        return $this;
    }

    /**
     * Fill model with attributes
     */
    public function fill(array $attributes)
    {
        foreach ($attributes as $key => $value) {
            if ($this->isFillable($key)) {
                $this->setAttribute($key, $value);
            }
        }
        return $this;
    }

    /**
     * Check if attribute is fillable
     */
    protected function isFillable($key)
    {
        if (in_array('*', $this->guarded)) {
            return in_array($key, $this->fillable);
        }
        return !in_array($key, $this->guarded);
    }

    /**
     * Set attribute value
     */
    public function setAttribute($key, $value)
    {
        // Check for mutator
        $method = 'set' . str_replace('_', '', ucwords($key, '_')) . 'Attribute';
        if (method_exists($this, $method)) {
            $this->attributes[$key] = $this->$method($value);
        } else {
            $this->attributes[$key] = $value;
        }
    }

    /**
     * Get attribute value
     */
    public function getAttribute($key)
    {
        if (array_key_exists($key, $this->attributes)) {
            $value = $this->attributes[$key];
            
            // Check for accessor
            $method = 'get' . str_replace('_', '', ucwords($key, '_')) . 'Attribute';
            if (method_exists($this, $method)) {
                return $this->$method($value);
            }
            
            return $this->castAttribute($key, $value);
        }

        // Check for relationship
        if (method_exists($this, $key)) {
            return $this->getRelationValue($key);
        }

        return null;
    }

    /**
     * Cast attribute to specified type
     */
    protected function castAttribute($key, $value)
    {
        if (!isset($this->casts[$key]) || $value === null) {
            return $value;
        }

        switch ($this->casts[$key]) {
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
                return json_decode($value, true);
            case 'object':
                return json_decode($value);
            case 'datetime':
                return new \DateTime($value);
            case 'timestamp':
                return strtotime($value);
            default:
                return $value;
        }
    }

    /**
     * Get relation value
     */
    protected function getRelationValue($key)
    {
        if (array_key_exists($key, $this->relations)) {
            return $this->relations[$key];
        }

        $relation = $this->$key();
        $this->relations[$key] = $relation;
        return $relation;
    }

    /**
     * Magic getter
     */
    public function __get($key)
    {
        return $this->getAttribute($key);
    }

    /**
     * Magic setter
     */
    public function __set($key, $value)
    {
        $this->setAttribute($key, $value);
    }

    /**
     * Check if attribute exists
     */
    public function __isset($key)
    {
        return isset($this->attributes[$key]);
    }

    /**
     * Create new query instance (static)
     */
    public static function query()
    {
        return new static();
    }

    /**
     * Create a new instance (for chaining)
     */
    protected function newQuery()
    {
        $instance = new static();
        $instance->query = $this->query;
        return $instance;
    }

    /**
     * Find record by primary key
     */
    public static function find($id)
    {
        return static::query()->where((new static())->primaryKey, $id)->first();
    }

    /**
     * Find multiple records by primary keys
     */
    public static function findMany(array $ids): Collection
    {
        return static::query()->whereIn((new static())->primaryKey, $ids)->get();
    }

    /**
     * Find record by primary key or fail
     */
    public static function findOrFail($id)
    {
        $result = static::find($id);
        if (!$result) {
            throw new \Exception("Model not found with ID: {$id}");
        }
        return $result;
    }

    /**
     * Get all records
     */
    public static function all(): Collection
    {
        return static::query()->get();
    }

    /**
     * Add WHERE clause
     */
    public function where($column, $operator = null, $value = null)
    {
        if (func_num_args() === 2) {
            $value = $operator;
            $operator = '=';
        }

        $this->query['where'][] = [
            'column' => $column,
            'operator' => $operator,
            'value' => $value,
            'boolean' => 'AND',
            'type' => 'basic'
        ];

        return $this;
    }

    /**
     * Add OR WHERE clause
     */
    public function orWhere($column, $operator = null, $value = null)
    {
        if (func_num_args() === 2) {
            $value = $operator;
            $operator = '=';
        }

        $this->query['where'][] = [
            'column' => $column,
            'operator' => $operator,
            'value' => $value,
            'boolean' => 'OR',
            'type' => 'basic'
        ];

        return $this;
    }

    /**
     * Add WHERE IN clause
     */
    public function whereIn($column, array $values)
    {
        $this->query['where'][] = [
            'column' => $column,
            'values' => $values,
            'boolean' => 'AND',
            'type' => 'in'
        ];

        return $this;
    }

    /**
     * Add WHERE NOT IN clause
     */
    public function whereNotIn($column, array $values)
    {
        $this->query['where'][] = [
            'column' => $column,
            'values' => $values,
            'boolean' => 'AND',
            'type' => 'notIn'
        ];

        return $this;
    }

    /**
     * Add WHERE NULL clause
     */
    public function whereNull($column)
    {
        $this->query['where'][] = [
            'column' => $column,
            'boolean' => 'AND',
            'type' => 'null'
        ];

        return $this;
    }

    /**
     * Add WHERE NOT NULL clause
     */
    public function whereNotNull($column)
    {
        $this->query['where'][] = [
            'column' => $column,
            'boolean' => 'AND',
            'type' => 'notNull'
        ];

        return $this;
    }

    /**
     * Add ORDER BY clause
     */
    public function orderBy($column, $direction = 'ASC')
    {
        $this->query['orderBy'][] = [
            'column' => $column,
            'direction' => strtoupper($direction)
        ];
        return $this;
    }

    /**
     * Order by descending
     */
    public function orderByDesc($column)
    {
        return $this->orderBy($column, 'DESC');
    }

    /**
     * Order by latest
     */
    public function latest($column = 'created_at')
    {
        return $this->orderBy($column, 'DESC');
    }

    /**
     * Order by oldest
     */
    public function oldest($column = 'created_at')
    {
        return $this->orderBy($column, 'ASC');
    }

    /**
     * Add LIMIT clause
     */
    public function limit($limit)
    {
        $this->query['limit'] = $limit;
        return $this;
    }

    /**
     * Alias for limit
     */
    public function take($limit)
    {
        return $this->limit($limit);
    }

    /**
     * Add OFFSET clause
     */
    public function offset($offset)
    {
        $this->query['offset'] = $offset;
        return $this;
    }

    /**
     * Alias for offset
     */
    public function skip($offset)
    {
        return $this->offset($offset);
    }

    /**
     * Get first record
     */
    public function first()
    {
        $this->limit(1);
        $results = $this->get();
        return $results->first();
    }

    /**
     * Get first record or fail
     */
    public function firstOrFail()
    {
        $result = $this->first();
        if (!$result) {
            throw new \Exception("No query results found");
        }
        return $result;
    }

    /**
     * Get count of records
     */
    public function count(): int
    {
        $originalSelect = $this->query['select'];
        $this->query['select'] = ['COUNT(*) as count'];
        
        $sql = $this->buildSelectQuery();
        $bindings = $this->getBindings();

        $stmt = static::getConnection()->prepare($sql);
        $stmt->execute($bindings);
        $result = $stmt->fetch(PDO::FETCH_ASSOC);

        $this->query['select'] = $originalSelect;
        
        return (int) $result['count'];
    }

    /**
     * Check if records exist
     */
    public function exists(): bool
    {
        return $this->count() > 0;
    }

    /**
     * Check if no records exist
     */
    public function doesntExist(): bool
    {
        return !$this->exists();
    }

    /**
     * Paginate results
     */
    public function paginate($perPage = 15, $page = 1): array
    {
        $total = $this->count();
        $totalPages = ceil($total / $perPage);
        
        $this->limit($perPage)->offset(($page - 1) * $perPage);
        $items = $this->get();

        return [
            'data' => $items,
            'current_page' => $page,
            'per_page' => $perPage,
            'total' => $total,
            'total_pages' => $totalPages,
            'from' => (($page - 1) * $perPage) + 1,
            'to' => min($page * $perPage, $total),
            'first_page_url' => "?page=1",
            'last_page_url' => "?page={$totalPages}",
            'next_page_url' => $page < $totalPages ? "?page=" . ($page + 1) : null,
            'prev_page_url' => $page > 1 ? "?page=" . ($page - 1) : null,
            'path' => '?page='
        ];
    }

    /**
     * Execute query and get results as Collection
     */
    public function get(): Collection
    {
        $this->fireModelEvent('retrieving');
        
        $sql = $this->buildSelectQuery();
        $bindings = $this->getBindings();

        $stmt = static::getConnection()->prepare($sql);
        $stmt->execute($bindings);
        $results = $stmt->fetchAll(PDO::FETCH_ASSOC);

        $models = array_map(function($result) {
            return $this->newFromBuilder($result);
        }, $results);

        $this->fireModelEvent('retrieved');

        return new Collection($models);
    }

    /**
     * Build SELECT query
     */
    protected function buildSelectQuery()
    {
        $sql = "SELECT " . implode(', ', $this->query['select']) . " FROM {$this->table}";

        // WHERE clauses
        if (!empty($this->query['where'])) {
            $sql .= " WHERE " . $this->buildWhereClause();
        } else if ($this->softDelete && !$this->query['withTrashed']) {
            $sql .= " WHERE {$this->deletedAtColumn} IS NULL";
        }

        // Add soft delete filter if not already in WHERE
        if ($this->softDelete && !$this->query['withTrashed'] && !empty($this->query['where'])) {
            $sql .= " AND {$this->deletedAtColumn} IS NULL";
        }

        // ORDER BY
        if (!empty($this->query['orderBy'])) {
            $orderByClauses = array_map(function($order) {
                return "{$order['column']} {$order['direction']}";
            }, $this->query['orderBy']);
            $sql .= " ORDER BY " . implode(', ', $orderByClauses);
        }

        // LIMIT
        if ($this->query['limit']) {
            $sql .= " LIMIT {$this->query['limit']}";
        }

        // OFFSET
        if ($this->query['offset']) {
            $sql .= " OFFSET {$this->query['offset']}";
        }

        return $sql;
    }

    /**
     * Build WHERE clause
     */
    protected function buildWhereClause()
    {
        $clauses = [];
        foreach ($this->query['where'] as $index => $where) {
            $clause = '';
            
            switch ($where['type']) {
                case 'basic':
                    $clause = "{$where['column']} {$where['operator']} ?";
                    break;
                case 'in':
                    $placeholders = implode(', ', array_fill(0, count($where['values']), '?'));
                    $clause = "{$where['column']} IN ({$placeholders})";
                    break;
                case 'notIn':
                    $placeholders = implode(', ', array_fill(0, count($where['values']), '?'));
                    $clause = "{$where['column']} NOT IN ({$placeholders})";
                    break;
                case 'null':
                    $clause = "{$where['column']} IS NULL";
                    break;
                case 'notNull':
                    $clause = "{$where['column']} IS NOT NULL";
                    break;
            }
            
            if ($index > 0) {
                $clause = "{$where['boolean']} {$clause}";
            }
            $clauses[] = $clause;
        }
        return implode(' ', $clauses);
    }

    /**
     * Get query bindings
     */
    protected function getBindings()
    {
        $bindings = [];
        foreach ($this->query['where'] as $where) {
            if (isset($where['value'])) {
                $bindings[] = $where['value'];
            } elseif (isset($where['values'])) {
                $bindings = array_merge($bindings, $where['values']);
            }
        }
        return $bindings;
    }

    /**
     * Create new model instance from database result
     */
    protected function newFromBuilder(array $attributes)
    {
        $instance = new static();
        $instance->exists = true;
        $instance->attributes = $attributes;
        $instance->original = $attributes;
        return $instance;
    }

    /**
     * Save model to database
     */
    public function save(): bool
    {
        if ($this->fireModelEvent('saving') === false) {
            return false;
        }

        if ($this->exists) {
            $saved = $this->performUpdate();
        } else {
            $saved = $this->performInsert();
        }

        if ($saved) {
            $this->fireModelEvent('saved');
        }

        return $saved;
    }

    /**
     * Perform INSERT query
     */
    protected function performInsert(): bool
    {
        if ($this->fireModelEvent('creating') === false) {
            return false;
        }

        if ($this->timestamps) {
            $this->setAttribute('created_at', date('Y-m-d H:i:s'));
            $this->setAttribute('updated_at', date('Y-m-d H:i:s'));
        }

        $attributes = $this->attributes;
        $columns = implode(', ', array_keys($attributes));
        $placeholders = implode(', ', array_fill(0, count($attributes), '?'));

        $sql = "INSERT INTO {$this->table} ({$columns}) VALUES ({$placeholders})";
        
        try {
            $stmt = static::getConnection()->prepare($sql);
            $stmt->execute(array_values($attributes));
            
            $lastId = static::getConnection()->lastInsertId();
            $this->setAttribute($this->primaryKey, $lastId);
            $this->exists = true;
            $this->original = $this->attributes;
            
            $this->fireModelEvent('created');
            
            return true;
        } catch (PDOException $e) {
            throw new \Exception("Insert failed: " . $e->getMessage());
        }
    }

    /**
     * Perform UPDATE query
     */
    protected function performUpdate(): bool
    {
        if ($this->fireModelEvent('updating') === false) {
            return false;
        }

        if ($this->timestamps) {
            $this->setAttribute('updated_at', date('Y-m-d H:i:s'));
        }

        $dirty = $this->getDirty();
        if (empty($dirty)) {
            return true;
        }

        $setClauses = [];
        $bindings = [];
        foreach ($dirty as $key => $value) {
            $setClauses[] = "{$key} = ?";
            $bindings[] = $value;
        }
        $bindings[] = $this->getAttribute($this->primaryKey);

        $sql = "UPDATE {$this->table} SET " . implode(', ', $setClauses) . 
               " WHERE {$this->primaryKey} = ?";

        try {
            $stmt = static::getConnection()->prepare($sql);
            $stmt->execute($bindings);
            $this->original = $this->attributes;
            
            $this->fireModelEvent('updated');
            
            return true;
        } catch (PDOException $e) {
            throw new \Exception("Update failed: " . $e->getMessage());
        }
    }

    /**
     * Get dirty attributes
     */
    protected function getDirty()
    {
        $dirty = [];
        foreach ($this->attributes as $key => $value) {
            if (!array_key_exists($key, $this->original) || $this->original[$key] !== $value) {
                $dirty[$key] = $value;
            }
        }
        return $dirty;
    }

    /**
     * Check if model is dirty
     */
    public function isDirty($attributes = null): bool
    {
        $dirty = $this->getDirty();
        
        if (is_null($attributes)) {
            return count($dirty) > 0;
        }
        
        if (!is_array($attributes)) {
            $attributes = func_get_args();
        }
        
        foreach ($attributes as $attribute) {
            if (array_key_exists($attribute, $dirty)) {
                return true;
            }
        }
        
        return false;
    }

    /**
     * Check if model is clean
     */
    public function isClean($attributes = null): bool
    {
        return !$this->isDirty($attributes);
    }

    /**
     * Get changed attributes
     */
    public function getChanges(): array
    {
        return $this->getDirty();
    }

    /**
     * Create new record
     */
    public static function create(array $attributes)
    {
        $instance = new static($attributes);
        $instance->save();
        return $instance;
    }

    /**
     * Update record
     */
    public function update(array $attributes): bool
    {
        $this->fill($attributes);
        return $this->save();
    }

    /**
     * Update or create record
     */
    public static function updateOrCreate(array $attributes, array $values = [])
    {
        $instance = static::query();
        
        foreach ($attributes as $key => $value) {
            $instance->where($key, $value);
        }
        
        $model = $instance->first();
        
        if ($model) {
            $model->update($values);
            return $model;
        }
        
        return static::create(array_merge($attributes, $values));
    }

    /**
     * Find or create record
     */
    public static function firstOrCreate(array $attributes, array $values = [])
    {
        $instance = static::query();
        
        foreach ($attributes as $key => $value) {
            $instance->where($key, $value);
        }
        
        $model = $instance->first();
        
        if ($model) {
            return $model;
        }
        
        return static::create(array_merge($attributes, $values));
    }

    /**
     * First or new (doesn't save)
     */
    public static function firstOrNew(array $attributes, array $values = [])
    {
        $instance = static::query();
        
        foreach ($attributes as $key => $value) {
            $instance->where($key, $value);
        }
        
        $model = $instance->first();
        
        if ($model) {
            return $model;
        }
        
        return new static(array_merge($attributes, $values));
    }

    /**
     * Delete record
     */
    public function delete(): bool
    {
        if (!$this->exists) {
            return false;
        }

        if ($this->fireModelEvent('deleting') === false) {
            return false;
        }

        if ($this->softDelete) {
            return $this->performSoftDelete();
        }

        $sql = "DELETE FROM {$this->table} WHERE {$this->primaryKey} = ?";
        
        try {
            $stmt = static::getConnection()->prepare($sql);
            $stmt->execute([$this->getAttribute($this->primaryKey)]);
            $this->exists = false;
            
            $this->fireModelEvent('deleted');
            
            return true;
        } catch (PDOException $e) {
            throw new \Exception("Delete failed: " . $e->getMessage());
        }
    }

    /**
     * Perform soft delete
     */
    protected function performSoftDelete(): bool
    {
        $this->setAttribute($this->deletedAtColumn, date('Y-m-d H:i:s'));
        return $this->save();
    }

    /**
     * Restore soft deleted record
     */
    public function restore(): bool
    {
        if (!$this->softDelete) {
            return false;
        }

        if ($this->fireModelEvent('restoring') === false) {
            return false;
        }

        $this->setAttribute($this->deletedAtColumn, null);
        $result = $this->save();

        if ($result) {
            $this->fireModelEvent('restored');
        }

        return $result;
    }

    /**
     * Force delete (permanent delete even with soft delete)
     */
    public function forceDelete(): bool
    {
        $wasSoftDelete = $this->softDelete;
        $this->softDelete = false;
        
        $result = $this->delete();
        
        $this->softDelete = $wasSoftDelete;
        
        return $result;
    }

    /**
     * Include soft deleted records
     */
    public function withTrashed()
    {
        $this->query['withTrashed'] = true;
        return $this;
    }

    /**
     * Get only soft deleted records
     */
    public function onlyTrashed()
    {
        return $this->whereNotNull($this->deletedAtColumn);
    }

    /**
     * Check if model is soft deleted
     */
    public function trashed(): bool
    {
        return $this->softDelete && !is_null($this->getAttribute($this->deletedAtColumn));
    }

    /**
     * Refresh model from database
     */
    public function refresh()
    {
        if (!$this->exists) {
            return $this;
        }

        $fresh = static::find($this->getAttribute($this->primaryKey));
        
        if ($fresh) {
            $this->attributes = $fresh->attributes;
            $this->original = $fresh->original;
            $this->relations = [];
        }

        return $this;
    }

    /**
     * Convert model to array
     */
    public function toArray(): array
    {
        $attributes = $this->attributes;
        
        // Remove hidden attributes
        foreach ($this->hidden as $hidden) {
            unset($attributes[$hidden]);
        }

        // Include relations
        foreach ($this->relations as $key => $value) {
            if ($value instanceof Collection) {
                $attributes[$key] = $value->toArray();
            } elseif (is_array($value)) {
                $attributes[$key] = array_map(function($item) {
                    return $item instanceof PlugModel ? $item->toArray() : $item;
                }, $value);
            } elseif ($value instanceof PlugModel) {
                $attributes[$key] = $value->toArray();
            } else {
                $attributes[$key] = $value;
            }
        }

        return $attributes;
    }

    /**
     * Convert model to JSON
     */
    public function toJson($options = 0): string
    {
        return json_encode($this->toArray(), $options);
    }

    /**
     * Get only specified attributes
     */
    public function only(array $keys): array
    {
        return array_intersect_key($this->toArray(), array_flip($keys));
    }

    /**
     * Get all except specified attributes
     */
    public function except(array $keys): array
    {
        return array_diff_key($this->toArray(), array_flip($keys));
    }

    /**
     * Make attributes visible
     */
    public function makeVisible($attributes)
    {
        $attributes = is_array($attributes) ? $attributes : func_get_args();
        $this->hidden = array_diff($this->hidden, $attributes);
        return $this;
    }

    /**
     * Make attributes hidden
     */
    public function makeHidden($attributes)
    {
        $attributes = is_array($attributes) ? $attributes : func_get_args();
        $this->hidden = array_unique(array_merge($this->hidden, $attributes));
        return $this;
    }

    // ==================== AGGREGATE METHODS ====================

    /**
     * Get the maximum value
     */
    public function max(string $column)
    {
        $originalSelect = $this->query['select'];
        $this->query['select'] = ["MAX({$column}) as max"];
        
        $sql = $this->buildSelectQuery();
        $bindings = $this->getBindings();

        $stmt = static::getConnection()->prepare($sql);
        $stmt->execute($bindings);
        $result = $stmt->fetch(PDO::FETCH_ASSOC);

        $this->query['select'] = $originalSelect;
        
        return $result['max'];
    }

    /**
     * Get the minimum value
     */
    public function min(string $column)
    {
        $originalSelect = $this->query['select'];
        $this->query['select'] = ["MIN({$column}) as min"];
        
        $sql = $this->buildSelectQuery();
        $bindings = $this->getBindings();

        $stmt = static::getConnection()->prepare($sql);
        $stmt->execute($bindings);
        $result = $stmt->fetch(PDO::FETCH_ASSOC);

        $this->query['select'] = $originalSelect;
        
        return $result['min'];
    }

    /**
     * Get the sum of values
     */
    public function sum(string $column)
    {
        $originalSelect = $this->query['select'];
        $this->query['select'] = ["SUM({$column}) as sum"];
        
        $sql = $this->buildSelectQuery();
        $bindings = $this->getBindings();

        $stmt = static::getConnection()->prepare($sql);
        $stmt->execute($bindings);
        $result = $stmt->fetch(PDO::FETCH_ASSOC);

        $this->query['select'] = $originalSelect;
        
        return $result['sum'] ?? 0;
    }

    /**
     * Get the average value
     */
    public function avg(string $column)
    {
        $originalSelect = $this->query['select'];
        $this->query['select'] = ["AVG({$column}) as avg"];
        
        $sql = $this->buildSelectQuery();
        $bindings = $this->getBindings();

        $stmt = static::getConnection()->prepare($sql);
        $stmt->execute($bindings);
        $result = $stmt->fetch(PDO::FETCH_ASSOC);

        $this->query['select'] = $originalSelect;
        
        return $result['avg'] ?? 0;
    }

    // ==================== SCOPES ====================

    /**
     * Apply local scope
     */
    public function __call($method, $parameters)
    {
        // Check for scope method
        $scopeMethod = 'scope' . ucfirst($method);
        if (method_exists($this, $scopeMethod)) {
            array_unshift($parameters, $this);
            return call_user_func_array([$this, $scopeMethod], $parameters);
        }

        throw new \BadMethodCallException("Method {$method} does not exist.");
    }

    /**
     * Apply local scope (static)
     */
    public static function __callStatic($method, $parameters)
    {
        return (new static())->$method(...$parameters);
    }

    // ==================== RELATIONSHIPS ====================

    /**
     * Define a one-to-one relationship
     */
    protected function hasOne($related, $foreignKey = null, $localKey = null)
    {
        $foreignKey = $foreignKey ?? strtolower(class_basename(static::class)) . '_id';
        $localKey = $localKey ?? $this->primaryKey;

        $instance = new $related();
        return $instance->where($foreignKey, $this->getAttribute($localKey))->first();
    }

    /**
     * Define a one-to-many relationship
     */
    protected function hasMany($related, $foreignKey = null, $localKey = null): Collection
    {
        $foreignKey = $foreignKey ?? strtolower(class_basename(static::class)) . '_id';
        $localKey = $localKey ?? $this->primaryKey;

        $instance = new $related();
        return $instance->where($foreignKey, $this->getAttribute($localKey))->get();
    }

    /**
     * Define an inverse one-to-one or many relationship
     */
    protected function belongsTo($related, $foreignKey = null, $ownerKey = null)
    {
        $foreignKey = $foreignKey ?? strtolower(class_basename($related)) . '_id';
        $ownerKey = $ownerKey ?? 'id';

        $instance = new $related();
        return $instance->where($ownerKey, $this->getAttribute($foreignKey))->first();
    }

    /**
     * Define a many-to-many relationship
     */
    protected function belongsToMany($related, $pivotTable = null, $foreignPivotKey = null, $relatedPivotKey = null, $parentKey = null, $relatedKey = null): Collection
    {
        $foreignPivotKey = $foreignPivotKey ?? strtolower(class_basename(static::class)) . '_id';
        $relatedPivotKey = $relatedPivotKey ?? strtolower(class_basename($related)) . '_id';
        $parentKey = $parentKey ?? $this->primaryKey;
        $relatedKey = $relatedKey ?? 'id';
        
        if (!$pivotTable) {
            $tables = [
                strtolower(class_basename(static::class)),
                strtolower(class_basename($related))
            ];
            sort($tables);
            $pivotTable = implode('_', $tables);
        }

        $sql = "SELECT r.* FROM {$pivotTable} p
                JOIN " . (new $related())->table . " r ON r.{$relatedKey} = p.{$relatedPivotKey}
                WHERE p.{$foreignPivotKey} = ?";

        $stmt = static::getConnection()->prepare($sql);
        $stmt->execute([$this->getAttribute($parentKey)]);
        $results = $stmt->fetchAll(PDO::FETCH_ASSOC);

        $instance = new $related();
        $models = array_map(function($result) use ($instance) {
            return $instance->newFromBuilder($result);
        }, $results);

        return new Collection($models);
    }

    /**
     * Eager load relationships
     */
    public function with($relations): Collection
    {
        if (is_string($relations)) {
            $relations = func_get_args();
        }

        $results = $this->get();

        foreach ($relations as $relation) {
            foreach ($results as $model) {
                $model->load($relation);
            }
        }

        return $results;
    }

    /**
     * Load a relationship
     */
    public function load($relation)
    {
        if (!isset($this->relations[$relation])) {
            $this->relations[$relation] = $this->$relation();
        }
        return $this;
    }

    // ==================== MODEL EVENTS ====================

    /**
     * Fire model event
     */
    protected function fireModelEvent($event)
    {
        $method = $event;
        
        if (method_exists($this, $method)) {
            return $this->$method();
        }

        return true;
    }

    /**
     * Register a retrieving model event
     */
    protected function retrieving()
    {
        // Override in child classes
    }

    /**
     * Register a retrieved model event
     */
    protected function retrieved()
    {
        // Override in child classes
    }

    /**
     * Register a creating model event
     */
    protected function creating()
    {
        // Override in child classes
    }

    /**
     * Register a created model event
     */
    protected function created()
    {
        // Override in child classes
    }

    /**
     * Register a updating model event
     */
    protected function updating()
    {
        // Override in child classes
    }

    /**
     * Register a updated model event
     */
    protected function updated()
    {
        // Override in child classes
    }

    /**
     * Register a saving model event
     */
    protected function saving()
    {
        // Override in child classes
    }

    /**
     * Register a saved model event
     */
    protected function saved()
    {
        // Override in child classes
    }

    /**
     * Register a deleting model event
     */
    protected function deleting()
    {
        // Override in child classes
    }

    /**
     * Register a deleted model event
     */
    protected function deleted()
    {
        // Override in child classes
    }

    /**
     * Register a restoring model event
     */
    protected function restoring()
    {
        // Override in child classes
    }

    /**
     * Register a restored model event
     */
    protected function restored()
    {
        // Override in child classes
    }
}

/**
 * Helper function to get class basename
 */
if (!function_exists('class_basename')) {
    function class_basename($class) {
        $class = is_object($class) ? get_class($class) : $class;
        return basename(str_replace('\\', '/', $class));
    }
}