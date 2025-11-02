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
    protected $appends = []; // Attributes to append to array/JSON

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

    // Query logging
    protected static $queryLog = [];
    protected static $enableQueryLog = false;

    // Transaction depth tracking
    protected static $transactionDepth = 0;

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

        $defaultOptions = [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES => false,
        ];

        $pdoOptions = array_merge($defaultOptions, $options);

        try {
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

    // ==================== QUERY LOGGING ====================

    /**
     * Enable query logging
     */
    public static function enableQueryLog()
    {
        static::$enableQueryLog = true;
    }

    /**
     * Disable query logging
     */
    public static function disableQueryLog()
    {
        static::$enableQueryLog = false;
    }

    /**
     * Get query log
     */
    public static function getQueryLog(): array
    {
        return static::$queryLog;
    }

    /**
     * Clear query log
     */
    public static function flushQueryLog()
    {
        static::$queryLog = [];
    }

    /**
     * Log a query
     */
    protected static function logQuery(string $sql, array $bindings, float $time)
    {
        if (static::$enableQueryLog) {
            static::$queryLog[] = [
                'query' => $sql,
                'bindings' => $bindings,
                'time' => $time,
                'timestamp' => date('Y-m-d H:i:s')
            ];
        }
    }

    /**
     * Execute query with logging
     */
    protected function executeQuery(string $sql, array $bindings = [])
    {
        $startTime = microtime(true);

        try {
            $stmt = static::getConnection()->prepare($sql);
            $stmt->execute($bindings);

            $time = microtime(true) - $startTime;
            static::logQuery($sql, $bindings, $time);

            return $stmt;
        } catch (PDOException $e) {
            static::logQuery($sql, $bindings, microtime(true) - $startTime);
            throw $e;
        }
    }

    // ==================== TRANSACTIONS ====================

    /**
     * Begin database transaction
     */
    public static function beginTransaction(): bool
    {
        if (static::$transactionDepth === 0) {
            static::getConnection()->beginTransaction();
        } else {
            // Nested transaction - create savepoint
            static::getConnection()->exec("SAVEPOINT trans_" . static::$transactionDepth);
        }

        static::$transactionDepth++;
        return true;
    }

    /**
     * Commit database transaction
     */
    public static function commit(): bool
    {
        if (static::$transactionDepth === 0) {
            throw new \Exception('No active transaction to commit');
        }

        static::$transactionDepth--;

        if (static::$transactionDepth === 0) {
            return static::getConnection()->commit();
        }

        // Release savepoint for nested transaction
        static::getConnection()->exec("RELEASE SAVEPOINT trans_" . static::$transactionDepth);
        return true;
    }

    /**
     * Rollback database transaction
     */
    public static function rollBack(): bool
    {
        if (static::$transactionDepth === 0) {
            throw new \Exception('No active transaction to rollback');
        }

        static::$transactionDepth--;

        if (static::$transactionDepth === 0) {
            return static::getConnection()->rollBack();
        }

        // Rollback to savepoint for nested transaction
        static::getConnection()->exec("ROLLBACK TO SAVEPOINT trans_" . static::$transactionDepth);
        return true;
    }

    /**
     * Execute callback within transaction
     */
    public static function transaction(callable $callback)
    {
        static::beginTransaction();

        try {
            $result = $callback();
            static::commit();
            return $result;
        } catch (\Exception $e) {
            static::rollBack();
            throw $e;
        }
    }

    /**
     * Get transaction depth
     */
    public static function transactionLevel(): int
    {
        return static::$transactionDepth;
    }

    // ==================== RAW QUERIES ====================

    /**
     * Execute raw SELECT query
     */
    public static function raw(string $sql, array $bindings = []): Collection
    {
        $instance = new static();
        $stmt = $instance->executeQuery($sql, $bindings);
        $results = $stmt->fetchAll(PDO::FETCH_ASSOC);

        $models = array_map(function ($result) use ($instance) {
            return $instance->newFromBuilder($result);
        }, $results);

        return new Collection($models);
    }

    /**
     * Execute raw query (INSERT, UPDATE, DELETE)
     */
    public static function statement(string $sql, array $bindings = []): bool
    {
        $instance = new static();
        $stmt = $instance->executeQuery($sql, $bindings);
        return $stmt->rowCount() > 0;
    }

    /**
     * Get single value from raw query
     */
    public static function scalar(string $sql, array $bindings = [])
    {
        $instance = new static();
        $stmt = $instance->executeQuery($sql, $bindings);
        return $stmt->fetchColumn();
    }

    // ==================== BATCH OPERATIONS ====================

    /**
     * Insert multiple records
     */
    public static function insert(array $records): bool
    {
        if (empty($records)) {
            return false;
        }

        $instance = new static();
        $columns = array_keys($records[0]);
        $columnList = implode(', ', $columns);

        $placeholders = '(' . implode(', ', array_fill(0, count($columns), '?')) . ')';
        $values = implode(', ', array_fill(0, count($records), $placeholders));

        $sql = "INSERT INTO {$instance->table} ({$columnList}) VALUES {$values}";

        $bindings = [];
        foreach ($records as $record) {
            foreach ($columns as $column) {
                $bindings[] = $record[$column] ?? null;
            }
        }

        try {
            $instance->executeQuery($sql, $bindings);
            return true;
        } catch (PDOException $e) {
            throw new \Exception("Batch insert failed: " . $e->getMessage());
        }
    }

    /**
     * Update multiple records by ID
     */
    public static function updateMany(array $records): bool
    {
        if (empty($records)) {
            return false;
        }

        static::beginTransaction();

        try {
            foreach ($records as $record) {
                if (!isset($record['id'])) {
                    throw new \Exception('Each record must have an ID for batch update');
                }

                $id = $record['id'];
                unset($record['id']);

                static::query()->where('id', $id)->update($record);
            }

            static::commit();
            return true;
        } catch (\Exception $e) {
            static::rollBack();
            throw $e;
        }
    }

    /**
     * Upsert - Insert or update if exists
     */
    public static function upsert(array $records, array $uniqueKeys, array $updateColumns = []): bool
    {
        if (empty($records)) {
            return false;
        }

        $instance = new static();
        $columns = array_keys($records[0]);

        // Build ON DUPLICATE KEY UPDATE clause
        if (empty($updateColumns)) {
            $updateColumns = array_diff($columns, $uniqueKeys);
        }

        $updateClause = implode(', ', array_map(function ($col) {
            return "{$col} = VALUES({$col})";
        }, $updateColumns));

        $columnList = implode(', ', $columns);
        $placeholders = '(' . implode(', ', array_fill(0, count($columns), '?')) . ')';
        $values = implode(', ', array_fill(0, count($records), $placeholders));

        $sql = "INSERT INTO {$instance->table} ({$columnList}) VALUES {$values} 
                ON DUPLICATE KEY UPDATE {$updateClause}";

        $bindings = [];
        foreach ($records as $record) {
            foreach ($columns as $column) {
                $bindings[] = $record[$column] ?? null;
            }
        }

        try {
            $instance->executeQuery($sql, $bindings);
            return true;
        } catch (PDOException $e) {
            throw new \Exception("Upsert failed: " . $e->getMessage());
        }
    }

    /**
     * Chunk results for memory-efficient processing
     */
    public static function chunk(int $size, callable $callback): bool
    {
        $page = 1;

        do {
            $results = static::query()->skip(($page - 1) * $size)->take($size)->get();

            if ($results->isEmpty()) {
                break;
            }

            if ($callback($results, $page) === false) {
                return false;
            }

            $page++;
        } while ($results->count() === $size);

        return true;
    }

    /**
     * Process each record individually
     */
    public static function each(callable $callback, int $chunkSize = 1000): bool
    {
        return static::chunk($chunkSize, function ($records) use ($callback) {
            foreach ($records as $record) {
                if ($callback($record) === false) {
                    return false;
                }
            }
        });
    }

    // ==================== QUERY SCOPES & HELPERS ====================

    /**
     * Select specific columns
     */
    public function select(...$columns)
    {
        $clone = $this->cloneQuery();
        $clone->query['select'] = $columns;
        return $clone;
    }

    /**
     * Add select columns
     */
    public function addSelect(...$columns)
    {
        $clone = $this->cloneQuery();
        $clone->query['select'] = array_merge($clone->query['select'], $columns);
        return $clone;
    }

    /**
     * Group by columns
     */
    public function groupBy(...$columns)
    {
        $clone = $this->cloneQuery();
        $clone->query['groupBy'] = array_merge($clone->query['groupBy'], $columns);
        return $clone;
    }

    /**
     * Having clause
     */
    public function having($column, $operator, $value)
    {
        $clone = $this->cloneQuery();
        $clone->query['having'][] = [
            'column' => $column,
            'operator' => $operator,
            'value' => $value
        ];
        return $clone;
    }

    /**
     * When condition is true, apply callback
     */
    public function when($condition, callable $callback, callable $default = null)
    {
        if ($condition) {
            return $callback($this) ?? $this;
        } elseif ($default) {
            return $default($this) ?? $this;
        }

        return $this;
    }

    /**
     * Unless condition is false, apply callback
     */
    public function unless($condition, callable $callback, callable $default = null)
    {
        return $this->when(!$condition, $callback, $default);
    }

    /**
     * Tap into query chain
     */
    public function tap(callable $callback)
    {
        $callback($this);
        return $this;
    }

    // ==================== CORE METHODS (from original) ====================

    protected function getTableName()
    {
        $className = (new \ReflectionClass($this))->getShortName();
        return strtolower($className) . 's';
    }

    public function setTable($table)
    {
        $this->table = $table;
        return $this;
    }

    public function fill(array $attributes)
    {
        foreach ($attributes as $key => $value) {
            if ($this->isFillable($key)) {
                $this->setAttribute($key, $value);
            }
        }
        return $this;
    }

    protected function isFillable($key)
    {
        if (in_array('*', $this->guarded)) {
            return in_array($key, $this->fillable);
        }
        return !in_array($key, $this->guarded);
    }

    public function setAttribute($key, $value)
    {
        $method = 'set' . str_replace('_', '', ucwords($key, '_')) . 'Attribute';
        if (method_exists($this, $method)) {
            $this->attributes[$key] = $this->$method($value);
        } else {
            $this->attributes[$key] = $value;
        }
        return $this;
    }

    public function getAttribute($key)
    {
        if (array_key_exists($key, $this->attributes)) {
            $value = $this->attributes[$key];
            $method = 'get' . str_replace('_', '', ucwords($key, '_')) . 'Attribute';
            if (method_exists($this, $method)) {
                return $this->$method($value);
            }
            return $this->castAttribute($key, $value);
        }

        if (method_exists($this, $key)) {
            return $this->getRelationValue($key);
        }

        return null;
    }

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
                return is_string($value) ? json_decode($value, true) : $value;
            case 'object':
                return is_string($value) ? json_decode($value) : $value;
            case 'datetime':
                return $value instanceof \DateTime ? $value : new \DateTime($value);
            case 'timestamp':
                return is_numeric($value) ? $value : strtotime($value);
            default:
                return $value;
        }
    }

    protected function getRelationValue($key)
    {
        if (array_key_exists($key, $this->relations)) {
            return $this->relations[$key];
        }
        $relation = $this->$key();
        $this->relations[$key] = $relation;
        return $relation;
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

    public static function query()
    {
        return new static();
    }

    protected function newQuery()
    {
        $instance = new static();
        $instance->query = $this->query;
        return $instance;
    }

    protected function cloneQuery()
    {
        $clone = clone $this;
        $clone->query = $this->query;
        return $clone;
    }

    public static function find($id)
    {
        return static::query()->where((new static())->primaryKey, $id)->first();
    }

    public static function findMany(array $ids): Collection
    {
        return static::query()->whereIn((new static())->primaryKey, $ids)->get();
    }

    public static function findOrFail($id)
    {
        $result = static::find($id);
        if (!$result) {
            throw new \Exception("Model not found with ID: {$id}");
        }
        return $result;
    }

    public static function all(): Collection
    {
        return static::query()->get();
    }

    public function where($column, $operator = null, $value = null)
    {
        $clone = $this->cloneQuery();

        if (func_num_args() === 2) {
            $value = $operator;
            $operator = '=';
        }

        $clone->query['where'][] = [
            'column' => $column,
            'operator' => $operator,
            'value' => $value,
            'boolean' => 'AND',
            'type' => 'basic'
        ];

        return $clone;
    }

    public function orWhere($column, $operator = null, $value = null)
    {
        $clone = $this->cloneQuery();

        if (func_num_args() === 2) {
            $value = $operator;
            $operator = '=';
        }

        $clone->query['where'][] = [
            'column' => $column,
            'operator' => $operator,
            'value' => $value,
            'boolean' => 'OR',
            'type' => 'basic'
        ];

        return $clone;
    }

    public function whereIn($column, array $values)
    {
        $clone = $this->cloneQuery();
        $clone->query['where'][] = [
            'column' => $column,
            'values' => $values,
            'boolean' => 'AND',
            'type' => 'in'
        ];
        return $clone;
    }

    public function whereNotIn($column, array $values)
    {
        $clone = $this->cloneQuery();
        $clone->query['where'][] = [
            'column' => $column,
            'values' => $values,
            'boolean' => 'AND',
            'type' => 'notIn'
        ];
        return $clone;
    }

    public function whereNull($column)
    {
        $clone = $this->cloneQuery();
        $clone->query['where'][] = [
            'column' => $column,
            'boolean' => 'AND',
            'type' => 'null'
        ];
        return $clone;
    }

    public function whereNotNull($column)
    {
        $clone = $this->cloneQuery();
        $clone->query['where'][] = [
            'column' => $column,
            'boolean' => 'AND',
            'type' => 'notNull'
        ];
        return $clone;
    }

    public function orderBy($column, $direction = 'ASC')
    {
        $clone = $this->cloneQuery();
        $clone->query['orderBy'][] = [
            'column' => $column,
            'direction' => strtoupper($direction)
        ];
        return $clone;
    }

    public function orderByDesc($column)
    {
        return $this->orderBy($column, 'DESC');
    }

    public function latest($column = 'created_at')
    {
        return $this->orderBy($column, 'DESC');
    }

    public function oldest($column = 'created_at')
    {
        return $this->orderBy($column, 'ASC');
    }

    public function limit($limit)
    {
        $clone = $this->cloneQuery();
        $clone->query['limit'] = $limit;
        return $clone;
    }

    public function take($limit)
    {
        return $this->limit($limit);
    }

    public function offset($offset)
    {
        $clone = $this->cloneQuery();
        $clone->query['offset'] = $offset;
        return $clone;
    }

    public function skip($offset)
    {
        return $this->offset($offset);
    }

    public function first()
    {
        $clone = $this->cloneQuery();
        $clone->query['limit'] = 1;
        $results = $clone->get();
        return $results->first();
    }

    public function firstOrFail()
    {
        $result = $this->first();
        if (!$result) {
            throw new \Exception("No query results found");
        }
        return $result;
    }

    public function count(): int
    {
        $clone = $this->cloneQuery();
        $clone->query['select'] = ['COUNT(*) as count'];
        $sql = $clone->buildSelectQuery();
        $bindings = $clone->getBindings();
        $stmt = $clone->executeQuery($sql, $bindings);
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        return (int) ($result['count'] ?? 0);
    }

    public function exists(): bool
    {
        return $this->count() > 0;
    }

    public function doesntExist(): bool
    {
        return !$this->exists();
    }

    public function paginate($perPage = 15, $page = 1): array
    {
        $clone = $this->cloneQuery();
        $total = $clone->count();
        $totalPages = ceil($total / $perPage);
        $clone->query['limit'] = $perPage;
        $clone->query['offset'] = ($page - 1) * $perPage;
        $items = $clone->get();

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

    public function get(): Collection
    {
        $this->fireModelEvent('retrieving');
        $sql = $this->buildSelectQuery();
        $bindings = $this->getBindings();
        $stmt = $this->executeQuery($sql, $bindings);
        $results = $stmt->fetchAll(PDO::FETCH_ASSOC);

        $models = array_map(function ($result) {
            return $this->newFromBuilder($result);
        }, $results);

        $this->fireModelEvent('retrieved');
        return new Collection($models);
    }

    protected function buildSelectQuery()
    {
        $sql = "SELECT " . implode(', ', $this->query['select']) . " FROM {$this->table}";

        $whereClauses = [];

        if (!empty($this->query['where'])) {
            $whereClauses[] = $this->buildWhereClause();
        }

        if ($this->softDelete && !$this->query['withTrashed']) {
            $whereClauses[] = "{$this->deletedAtColumn} IS NULL";
        }

        if (!empty($whereClauses)) {
            $sql .= " WHERE " . implode(' AND ', array_map(function ($clause) {
                return "({$clause})";
            }, $whereClauses));
        }

        if (!empty($this->query['groupBy'])) {
            $sql .= " GROUP BY " . implode(', ', $this->query['groupBy']);
        }

        if (!empty($this->query['having'])) {
            $havingClauses = array_map(function ($having) {
                return "{$having['column']} {$having['operator']} ?";
            }, $this->query['having']);
            $sql .= " HAVING " . implode(' AND ', $havingClauses);
        }

        if (!empty($this->query['orderBy'])) {
            $orderByClauses = array_map(function ($order) {
                return "{$order['column']} {$order['direction']}";
            }, $this->query['orderBy']);
            $sql .= " ORDER BY " . implode(', ', $orderByClauses);
        }

        if ($this->query['limit']) {
            $sql .= " LIMIT {$this->query['limit']}";
        }

        if ($this->query['offset']) {
            $sql .= " OFFSET {$this->query['offset']}";
        }

        return $sql;
    }

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

        // Add HAVING bindings
        foreach ($this->query['having'] as $having) {
            $bindings[] = $having['value'];
        }

        return $bindings;
    }

    protected function newFromBuilder(array $attributes)
    {
        $instance = new static();
        $instance->exists = true;
        $instance->attributes = $attributes;
        $instance->original = $attributes;
        return $instance;
    }

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
            $stmt = $this->executeQuery($sql, array_values($attributes));
            $lastId = static::getConnection()->lastInsertId();
            if ($lastId) {
                $this->setAttribute($this->primaryKey, $lastId);
            }
            $this->exists = true;
            $this->original = $this->attributes;
            $this->fireModelEvent('created');
            return true;
        } catch (PDOException $e) {
            throw new \Exception("Insert failed: " . $e->getMessage());
        }
    }

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
            $this->executeQuery($sql, $bindings);
            $this->original = $this->attributes;
            $this->fireModelEvent('updated');
            return true;
        } catch (PDOException $e) {
            throw new \Exception("Update failed: " . $e->getMessage());
        }
    }

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

    public function isClean($attributes = null): bool
    {
        return !$this->isDirty($attributes);
    }

    public function getChanges(): array
    {
        return $this->getDirty();
    }

    public static function create(array $attributes)
    {
        $instance = new static($attributes);
        $instance->save();
        return $instance;
    }

    public function update(array $attributes): bool
    {
        $this->fill($attributes);
        return $this->save();
    }

    public static function updateOrCreate(array $attributes, array $values = [])
    {
        $instance = static::query();

        foreach ($attributes as $key => $value) {
            $instance = $instance->where($key, $value);
        }

        $model = $instance->first();

        if ($model) {
            $model->update($values);
            return $model;
        }

        return static::create(array_merge($attributes, $values));
    }

    public static function firstOrCreate(array $attributes, array $values = [])
    {
        $instance = static::query();

        foreach ($attributes as $key => $value) {
            $instance = $instance->where($key, $value);
        }

        $model = $instance->first();

        if ($model) {
            return $model;
        }

        return static::create(array_merge($attributes, $values));
    }

    public static function firstOrNew(array $attributes, array $values = [])
    {
        $instance = static::query();

        foreach ($attributes as $key => $value) {
            $instance = $instance->where($key, $value);
        }

        $model = $instance->first();

        if ($model) {
            return $model;
        }

        return new static(array_merge($attributes, $values));
    }

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
            $this->executeQuery($sql, [$this->getAttribute($this->primaryKey)]);
            $this->exists = false;
            $this->fireModelEvent('deleted');
            return true;
        } catch (PDOException $e) {
            throw new \Exception("Delete failed: " . $e->getMessage());
        }
    }

    protected function performSoftDelete(): bool
    {
        $this->setAttribute($this->deletedAtColumn, date('Y-m-d H:i:s'));
        return $this->save();
    }

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

    public function forceDelete(): bool
    {
        $wasSoftDelete = $this->softDelete;
        $this->softDelete = false;
        $result = $this->delete();
        $this->softDelete = $wasSoftDelete;
        return $result;
    }

    public function withTrashed()
    {
        $clone = $this->cloneQuery();
        $clone->query['withTrashed'] = true;
        return $clone;
    }

    public function onlyTrashed()
    {
        return $this->whereNotNull($this->deletedAtColumn);
    }

    public function trashed(): bool
    {
        return $this->softDelete && !is_null($this->getAttribute($this->deletedAtColumn));
    }

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

    public function toArray(): array
    {
        $attributes = $this->attributes;

        foreach ($this->hidden as $hidden) {
            unset($attributes[$hidden]);
        }

        foreach ($this->relations as $key => $value) {
            if ($value instanceof Collection) {
                $attributes[$key] = $value->toArray();
            } elseif (is_array($value)) {
                $attributes[$key] = array_map(function ($item) {
                    return $item instanceof PlugModel ? $item->toArray() : $item;
                }, $value);
            } elseif ($value instanceof PlugModel) {
                $attributes[$key] = $value->toArray();
            } else {
                $attributes[$key] = $value;
            }
        }

        // Add appended attributes
        foreach ($this->appends as $append) {
            $method = 'get' . str_replace('_', '', ucwords($append, '_')) . 'Attribute';
            if (method_exists($this, $method)) {
                $attributes[$append] = $this->$method();
            }
        }

        return $attributes;
    }

    public function toJson($options = 0): string
    {
        return json_encode($this->toArray(), $options);
    }

    public function only(array $keys): array
    {
        return array_intersect_key($this->toArray(), array_flip($keys));
    }

    public function except(array $keys): array
    {
        return array_diff_key($this->toArray(), array_flip($keys));
    }

    public function makeVisible($attributes)
    {
        $attributes = is_array($attributes) ? $attributes : func_get_args();
        $this->hidden = array_diff($this->hidden, $attributes);
        return $this;
    }

    public function makeHidden($attributes)
    {
        $attributes = is_array($attributes) ? $attributes : func_get_args();
        $this->hidden = array_unique(array_merge($this->hidden, $attributes));
        return $this;
    }

    public function append($attributes)
    {
        $attributes = is_array($attributes) ? $attributes : func_get_args();
        $this->appends = array_unique(array_merge($this->appends, $attributes));
        return $this;
    }

    // ==================== AGGREGATE METHODS ====================

    public function max(string $column)
    {
        $clone = $this->cloneQuery();
        $clone->query['select'] = ["MAX({$column}) as max"];
        $sql = $clone->buildSelectQuery();
        $bindings = $clone->getBindings();
        $stmt = $clone->executeQuery($sql, $bindings);
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        return $result['max'] ?? null;
    }

    public function min(string $column)
    {
        $clone = $this->cloneQuery();
        $clone->query['select'] = ["MIN({$column}) as min"];
        $sql = $clone->buildSelectQuery();
        $bindings = $clone->getBindings();
        $stmt = $clone->executeQuery($sql, $bindings);
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        return $result['min'] ?? null;
    }

    public function sum(string $column)
    {
        $clone = $this->cloneQuery();
        $clone->query['select'] = ["SUM({$column}) as sum"];
        $sql = $clone->buildSelectQuery();
        $bindings = $clone->getBindings();
        $stmt = $clone->executeQuery($sql, $bindings);
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        return $result['sum'] ?? 0;
    }

    public function avg(string $column)
    {
        $clone = $this->cloneQuery();
        $clone->query['select'] = ["AVG({$column}) as avg"];
        $sql = $clone->buildSelectQuery();
        $bindings = $clone->getBindings();
        $stmt = $clone->executeQuery($sql, $bindings);
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        return $result['avg'] ?? 0;
    }

    // ==================== SCOPES ====================

    public function __call($method, $parameters)
    {
        $scopeMethod = 'scope' . ucfirst($method);
        if (method_exists($this, $scopeMethod)) {
            array_unshift($parameters, $this);
            return call_user_func_array([$this, $scopeMethod], $parameters);
        }

        throw new \BadMethodCallException("Method {$method} does not exist.");
    }

    public static function __callStatic($method, $parameters)
    {
        $instance = new static();

        $staticMethods = [
            'where',
            'orWhere',
            'whereIn',
            'whereNotIn',
            'whereNull',
            'whereNotNull',
            'orderBy',
            'orderByDesc',
            'latest',
            'oldest',
            'limit',
            'take',
            'offset',
            'skip',
            'first',
            'firstOrFail',
            'get',
            'count',
            'exists',
            'doesntExist',
            'paginate',
            'max',
            'min',
            'sum',
            'avg',
            'withTrashed',
            'onlyTrashed',
            'with',
            'select',
            'addSelect',
            'groupBy',
            'having',
            'when',
            'unless',
            'tap'
        ];

        if (in_array($method, $staticMethods)) {
            return call_user_func_array([$instance, $method], $parameters);
        }

        $scopeMethod = 'scope' . ucfirst($method);
        if (method_exists($instance, $scopeMethod)) {
            array_unshift($parameters, $instance);
            return call_user_func_array([$instance, $scopeMethod], $parameters);
        }

        throw new \BadMethodCallException("Static method {$method} does not exist.");
    }

    // ==================== RELATIONSHIPS ====================

    protected function hasOne($related, $foreignKey = null, $localKey = null)
    {
        $foreignKey = $foreignKey ?? strtolower(class_basename(static::class)) . '_id';
        $localKey = $localKey ?? $this->primaryKey;
        $instance = new $related();
        return $instance->where($foreignKey, $this->getAttribute($localKey))->first();
    }

    protected function hasMany($related, $foreignKey = null, $localKey = null): Collection
    {
        $foreignKey = $foreignKey ?? strtolower(class_basename(static::class)) . '_id';
        $localKey = $localKey ?? $this->primaryKey;
        $instance = new $related();
        return $instance->where($foreignKey, $this->getAttribute($localKey))->get();
    }

    protected function belongsTo($related, $foreignKey = null, $ownerKey = null)
    {
        $foreignKey = $foreignKey ?? strtolower(class_basename($related)) . '_id';
        $ownerKey = $ownerKey ?? 'id';
        $instance = new $related();
        return $instance->where($ownerKey, $this->getAttribute($foreignKey))->first();
    }

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

        $relatedInstance = new $related();
        $relatedTable = $relatedInstance->table;

        $sql = "SELECT r.* FROM {$pivotTable} p
                JOIN {$relatedTable} r ON r.{$relatedKey} = p.{$relatedPivotKey}
                WHERE p.{$foreignPivotKey} = ?";

        $stmt = $this->executeQuery($sql, [$this->getAttribute($parentKey)]);
        $results = $stmt->fetchAll(PDO::FETCH_ASSOC);

        $models = array_map(function ($result) use ($relatedInstance) {
            return $relatedInstance->newFromBuilder($result);
        }, $results);

        return new Collection($models);
    }

    public function with($relations)
    {
        $clone = $this->cloneQuery();

        if (is_string($relations)) {
            $relations = func_get_args();
        }

        $results = $clone->get();

        foreach ($relations as $relation) {
            foreach ($results as $model) {
                $model->load($relation);
            }
        }

        return $results;
    }

    public function load($relation)
    {
        if (!isset($this->relations[$relation])) {
            $this->relations[$relation] = $this->$relation();
        }
        return $this;
    }

    // ==================== MODEL EVENTS ====================

    protected function fireModelEvent($event)
    {
        $method = $event;
        if (method_exists($this, $method)) {
            return $this->$method();
        }
        return true;
    }

    protected function retrieving() {}
    protected function retrieved() {}
    protected function creating() {}
    protected function created() {}
    protected function updating() {}
    protected function updated() {}
    protected function saving() {}
    protected function saved() {}
    protected function deleting() {}
    protected function deleted() {}
    protected function restoring() {}
    protected function restored() {}
}

if (!function_exists('class_basename')) {
    function class_basename($class)
    {
        $class = is_object($class) ? get_class($class) : $class;
        return basename(str_replace('\\', '/', $class));
    }
}
