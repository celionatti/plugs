<?php

declare(strict_types=1);

namespace Plugs\Base\Model;

use BadMethodCallException;
use Exception;
use PDO;
use PDOException;
use Plugs\Database\Collection;
use Plugs\Database\Connection;
use Plugs\Database\Traits\HasAttributes;
use Plugs\Database\Traits\HasConnection;
use Plugs\Database\Traits\HasEvents;
use Plugs\Database\Traits\HasQueryBuilder;
use Plugs\Database\Traits\HasRelationships;
use Plugs\Database\Traits\HasTimestamps;
use Plugs\Database\Traits\HasValidation;
use Plugs\Database\Traits\Searchable;
use Plugs\Database\Traits\SoftDeletes;

abstract class PlugModel
{
    use Debuggable;
    use Searchable;
    use HasConnection;
    use HasQueryBuilder;
    use HasAttributes;
    use HasRelationships;
    use HasEvents;
    use HasValidation;
    use HasTimestamps;
    use SoftDeletes;

    protected $table;
    protected $primaryKey = 'id';

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
        'withTrashed' => false,
        'distinct' => false,
    ];

    // Separate bindings for better memory management
    protected $bindings = [];
    protected static $queryLog = [];
    protected static $enableQueryLog = false;
    protected static $queryCache = [];
    protected static $cacheEnabled = false;
    protected static $cacheTTL = 3600;
    protected static $maxCacheSize = 1000;
    protected static $preparedStatements = [];

    // Transaction tracking
    protected static $transactionDepth = 0;
    protected static $transactionConnection = null;

    protected $allowedRawFunctions = ['RAND', 'COUNT', 'SUM', 'AVG', 'MAX', 'MIN'];
    protected $allowRawQueries = false; // Default to secure
    protected $exists = false;

    public function __construct(array|object $attributes = [])
    {
        $this->bootIfNotBooted();

        // Validate configuration
        $this->validateModelConfiguration();

        $this->fill($attributes);
        if (!$this->table) {
            $this->table = $this->getTableName();
        }
    }

    protected function validateModelConfiguration(): void
    {
        // Validation removed to allow fillable taking precedence over default guarded=['*']
    }

    public function enableRawQueries(bool $enable = true)
    {
        $this->allowRawQueries = $enable;

        return $this;
    }

    /**
     * Get key value
     */
    public function getKey()
    {
        return $this->getAttribute($this->primaryKey);
    }

    /**
     * Get key name
     */
    public function getKeyName(): string
    {
        return $this->primaryKey;
    }

    /**
     * Get table name
     */
    public function getTable(): string
    {
        return $this->table ?? strtolower(
            preg_replace('/([a-z])([A-Z])/', '$1_$2', class_basename(static::class))
        ) . 's';
    }

    protected static function getTableName(): string
    {
        return (new static())->getTable();
    }

    protected function sanitizeColumnName(string $column): string
    {
        // Prevent SQL injection in column names
        if (!preg_match('/^[a-zA-Z0-9_\.]+$/', $column)) {
            throw new Exception("Invalid column name: {$column}");
        }

        return $column;
    }

    public function exists(): bool
    {
        return isset($this->attributes[$this->primaryKey]);
    }

    public static function create(array|object $attributes): self
    {
        $model = new static($attributes);
        $model->save();

        return $model;
    }

    public static function updateOrCreate(array|object $attributes, array|object $values = []): self
    {
        $attributes = (new static())->parseAttributes($attributes);
        $values = (new static())->parseAttributes($values);

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

    public static function firstOrCreate(array|object $attributes): self
    {
        $attributes = (new static())->parseAttributes($attributes);

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

    // ==================== QUERY BUILDER ENHANCEMENTS (That use instance state) ====================

    protected function cloneQuery()
    {
        $clone = clone $this;
        $clone->query = $this->query;
        $clone->bindings = $this->bindings;

        return $clone;
    }

    public function instanceWhere(string $column, $operator, $value = null)
    {
        if ($value === null) {
            $value = $operator;
            $operator = '=';
        }

        $clone = $this->cloneQuery();
        $clone->query['where'][] = [
            'type' => 'basic',
            'column' => $column,
            'operator' => $operator,
            'value' => $value,
            'boolean' => 'AND',
        ];

        // Add binding
        $clone->bindings[] = $value;

        return $clone;
    }

    public function instanceOrWhere(string $column, $operator, $value = null)
    {
        if ($value === null) {
            $value = $operator;
            $operator = '=';
        }

        $clone = $this->cloneQuery();
        $clone->query['where'][] = [
            'type' => 'basic',
            'column' => $column,
            'operator' => $operator,
            'value' => $value,
            'boolean' => 'OR',
        ];

        // Add binding
        $clone->bindings[] = $value;

        return $clone;
    }

    public function instanceWhereIn(string $column, array $values)
    {
        $clone = $this->cloneQuery();

        $clone->query['where'][] = [
            'type' => 'in',
            'column' => $column,
            'values' => $values,
            'boolean' => 'AND',
        ];

        foreach ($values as $value) {
            $clone->bindings[] = $value;
        }

        return $clone;
    }

    protected function sanitizeRawSql(string $sql): void
    {
        if (
            strpos(strtoupper($sql), 'DROP ') !== false ||
            strpos(strtoupper($sql), 'DELETE ') !== false ||
            strpos(strtoupper($sql), 'TRUNCATE ') !== false ||
            strpos(strtoupper($sql), 'UPDATE ') !== false ||
            strpos(strtoupper($sql), 'ALTER ') !== false
        ) {

            if (!$this->allowRawQueries) {
                throw new Exception("Potentially unsafe raw SQL detected");
            }
        }
    }

    public function distinct()
    {
        $clone = $this->cloneQuery();
        $clone->query['distinct'] = true;

        return $clone;
    }

    public function whereBetween(string $column, array $values)
    {
        if (count($values) !== 2) {
            throw new Exception('whereBetween requires exactly 2 values');
        }

        return $this->instanceWhere($column, '>=', $values[0])
            ->instanceWhere($column, '<=', $values[1]);
    }

    public function whereNotBetween(string $column, array $values)
    {
        if (count($values) !== 2) {
            throw new Exception('whereNotBetween requires exactly 2 values');
        }

        return $this->instanceWhere($column, '<', $values[0])
            ->instanceOrWhere($column, '>', $values[1]);
    }

    public function whereDate(string $column, string $date)
    {
        return $this->instanceWhere($column, '=', $date);
    }

    public function whereYear(string $column, int $year)
    {
        return $this->instanceWhere("YEAR({$column})", $year);
    }

    public function whereMonth(string $column, int $month)
    {
        return $this->instanceWhere("MONTH({$column})", $month);
    }

    public function whereDay(string $column, int $day)
    {
        return $this->instanceWhere("DAY({$column})", $day);
    }

    public function whereLike(string $column, string $value)
    {
        return $this->instanceWhere($column, 'LIKE', $value);
    }

    public function orWhereLike(string $column, string $value)
    {
        return $this->instanceOrWhere($column, 'LIKE', $value);
    }

    public function inRandomOrder()
    {
        return $this->orderByRaw('RAND()');
    }

    public function random(int $count = 1)
    {
        return $this->inRandomOrder()->limit($count)->get();
    }

    public function orderByRaw(string $expression, array $bindings = [])
    {
        // More strict validation
        $this->sanitizeRawSql($expression);

        // Only allow specific function patterns
        if (preg_match('/^([A-Z_]+)\s*\(/', $expression, $matches)) {
            if (!in_array($matches[1], $this->allowedRawFunctions)) {
                throw new \InvalidArgumentException("Function '{$matches[1]}' not allowed in ORDER BY");
            }
        }

        // Validate entire expression more strictly
        if (!preg_match('/^[a-zA-Z0-9_\(\)\s,\.]+$/', $expression)) {
            throw new \InvalidArgumentException("Invalid ORDER BY expression");
        }

        $clone = $this->cloneQuery();
        $clone->query['orderBy'][] = [
            'type' => 'raw',
            'expression' => $expression,
        ];
        $clone->bindings = array_merge($clone->bindings, $bindings);

        return $clone;
    }

    // ==================== CACHE & LOGGING ====================

    public static function enableCache(int $ttl = 3600): void
    {
        static::$cacheEnabled = true;
        static::$cacheTTL = $ttl;
    }

    public static function disableCache(): void
    {
        static::$cacheEnabled = false;
    }

    public static function flushCache(): void
    {
        static::$queryCache = [];
    }

    protected function getCacheKey(string $sql, array $bindings): string
    {
        return md5($sql . serialize($bindings));
    }

    protected function getFromCache(string $key)
    {
        if (!static::$cacheEnabled || !isset(static::$queryCache[$key])) {
            return null;
        }

        $cached = static::$queryCache[$key];
        if (time() > $cached['expires']) {
            unset(static::$queryCache[$key]);

            return null;
        }

        return $cached['data'];
    }

    protected function putInCache(string $key, $data): void
    {
        if (static::$cacheEnabled) {
            if (count(static::$queryCache) >= static::$maxCacheSize) {
                array_shift(static::$queryCache); // Remove oldest
            }

            static::$queryCache[$key] = [
                'data' => $data,
                'expires' => time() + static::$cacheTTL,
            ];
        }
    }

    public static function enableQueryLog(): void
    {
        static::$enableQueryLog = true;
    }

    public static function disableQueryLog(): void
    {
        static::$enableQueryLog = false;
    }

    public static function getQueryLog(): array
    {
        return static::$queryLog;
    }

    public static function flushQueryLog(): void
    {
        static::$queryLog = [];
    }

    protected static function logQuery(string $sql, array $bindings, float $time): void
    {
        if (static::$enableQueryLog) {
            static::$queryLog[] = [
                'query' => $sql,
                'bindings' => $bindings,
                'time' => $time,
                'timestamp' => date('Y-m-d H:i:s'),
            ];
        }
    }

    // ==================== EXECUTION ====================

    protected function executeQuery(string $sql, array $bindings = [])
    {
        $startTime = microtime(true);

        try {
            $connection = static::getConnection();

            if ($connection instanceof PDO) {
                $stmt = $connection->prepare($sql);
                $stmt->execute($bindings);
            } else {
                $stmt = $connection->query($sql, $bindings);
            }

            $time = microtime(true) - $startTime;
            static::logQuery($sql, $bindings, $time);

            return $stmt;
        } catch (PDOException $e) {
            static::logQuery($sql, $bindings, microtime(true) - $startTime);

            throw $e;
        }
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

        if ($this->timestamps) {
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

        if ($this->timestamps) {
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
            throw new Exception("Cannot refresh a model that doesn't exist");
        }

        $fresh = static::find($this->attributes[$this->primaryKey]);

        if (!$fresh) {
            throw new Exception("Model no longer exists in database");
        }

        $this->attributes = $fresh->attributes;
        $this->original = $fresh->original;

        return $this;
    }

    // ==================== TRANSACTION HELPERS ====================

    public static function beginTransaction(): bool
    {
        if (static::$transactionDepth === 0) {
            static::$transactionConnection = static::getConnection();
            static::$transactionConnection->beginTransaction();
        } else {
            static::getConnection()->exec("SAVEPOINT trans_" . static::$transactionDepth);
        }
        static::$transactionDepth++;

        return true;
    }

    public static function commit(): bool
    {
        if (static::$transactionDepth === 0) {
            throw new Exception('No active transaction to commit');
        }
        static::$transactionDepth--;
        if (static::$transactionDepth === 0) {
            return static::getConnection()->commit();
        }
        static::getConnection()->exec("RELEASE SAVEPOINT trans_" . static::$transactionDepth);

        return true;
    }

    public static function rollBack(): bool
    {
        if (static::$transactionDepth === 0) {
            throw new Exception('No active transaction to rollback');
        }
        static::$transactionDepth--;
        if (static::$transactionDepth === 0) {
            return static::getConnection()->rollBack();
        }
        static::getConnection()->exec("ROLLBACK TO SAVEPOINT trans_" . static::$transactionDepth);

        return true;
    }

    // ==================== RAW & BATCH OPERATIONS ====================

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

    public static function statement(string $sql, array $bindings = []): bool
    {
        $instance = new static();
        $stmt = $instance->executeQuery($sql, $bindings);

        return $stmt->rowCount() > 0;
    }


    // ... (Keep other batch operations or move to trait if desired, but for now keep here as they heavily use static/instance context mix)

    // ==================== MAGIC METHODS ====================

    /**
     * Override __call to return relationship proxy or forward calls
     */
    public function __call($method, $parameters)
    {
        $class = static::class;

        if (!isset(self::$relationTypes[$class][$method])) {
            if (method_exists($this, $method)) {
                $reflection = new \ReflectionMethod($this, $method);
                $code = $this->getMethodCode($reflection);

                self::$relationTypes[$class][$method] = strpos($code, 'belongsToMany') !== false
                    ? 'belongsToMany'
                    : 'other';
            } else {
                self::$relationTypes[$class][$method] = null;
            }
        }

        if (self::$relationTypes[$class][$method] === 'belongsToMany') {
            return $this->getBelongsToManyRelation($method);
        }

        // Check if it's a belongsToMany relationship (fallback)
        if (method_exists($this, $method)) {
            $reflection = new \ReflectionMethod($this, $method);
            $code = $this->getMethodCode($reflection);

            if (strpos($code, 'belongsToMany') !== false) {
                return $this->getBelongsToManyRelation($method);
            }
        }

        // Handle instance method calls that should use instance variants
        $instanceMethod = 'instance' . ucfirst($method);
        if (method_exists($this, $instanceMethod)) {
            return call_user_func_array([$this, $instanceMethod], $parameters);
        }

        $scopeMethod = 'scope' . ucfirst($method);
        if (method_exists($this, $scopeMethod)) {
            array_unshift($parameters, $this);

            return call_user_func_array([$this, $scopeMethod], $parameters);
        }

        throw new BadMethodCallException("Method {$method} does not exist.");
    }

    public static function __callStatic($method, $parameters)
    {
        $instance = new static();

        if (method_exists(static::class, $method)) {
            return forward_static_call_array([static::class, $method], $parameters);
        }

        if (method_exists($instance, $method)) {
            return call_user_func_array([$instance, $method], $parameters);
        }

        $scopeMethod = 'scope' . ucfirst($method);
        if (method_exists($instance, $scopeMethod)) {
            array_unshift($parameters, $instance);

            return call_user_func_array([$instance, $scopeMethod], $parameters);
        }

        throw new BadMethodCallException("Static method {$method} does not exist on " . get_called_class());
    }

    public function __toString(): string
    {
        return $this->toJson();
    }

    public function __debugInfo(): array
    {
        return [
            'attributes' => $this->attributes,
            'original' => $this->original,
            'relations' => $this->relations,
            'exists' => $this->exists,
            'table' => $this->table,
        ];
    }
}

if (!function_exists('class_basename')) {
    function class_basename($class): string
    {
        $class = is_object($class) ? get_class($class) : $class;

        return basename(str_replace('\\', '/', $class));
    }
}
