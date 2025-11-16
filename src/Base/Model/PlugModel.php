<?php

declare(strict_types=1);

namespace Plugs\Base\Model;

use PDO;
use DateTime;
use Exception;
use PDOException;
use BadMethodCallException;
use Plugs\Database\Collection;
use Plugs\Database\Connection;
use Plugs\Base\Model\Debuggable;

abstract class PlugModel
{
    use Debuggable;
    protected static $connection;
    protected static $connectionName = 'default';
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
    protected $appends = [];
    protected $with = [];

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
        // 'bindings' => []
    ];

    // Separate bindings for better memory management
    protected $bindings = [];

    protected $castNulls = true;

    // Model events & scopes
    protected static $booted = [];
    protected static $globalScopes = [];
    protected static $observers = [];

    // Query logging & caching
    protected static $queryLog = [];
    protected static $queryCount = 0;
    protected static $queryLimit = 10000;
    protected static $enableQueryLog = false;
    protected static $queryCache = [];
    protected static $cacheEnabled = false;
    protected static $cacheTTL = 3600;

    // Transaction tracking
    protected static $transactionDepth = 0;
    protected static $transactionConnection = null;

    // Model registry for polymorphic relations
    protected static $morphMap = [];

    // Prepared statement cache
    protected static $preparedStatements = [];

    protected static $maxCacheSize = 1000;
    protected static $maxLogSize = 500;

    protected $eagerLoad = [];
    protected static $relationLoaders = [
        'hasOne' => 'eagerLoadHasOne',
        'hasMany' => 'eagerLoadHasMany',
        'belongsTo' => 'eagerLoadBelongsTo',
        'belongsToMany' => 'eagerLoadBelongsToMany',
        'morphTo' => 'eagerLoadMorphTo',
        'morphMany' => 'eagerLoadMorphMany',
    ];

    public function __construct(array $attributes = [])
    {
        $this->bootIfNotBooted();
        $this->fill($attributes);
        if (!$this->table) {
            $this->table = $this->getTableName();
        }
    }

    protected function bootIfNotBooted(): void
    {
        $class = static::class;
        if (!isset(static::$booted[$class])) {
            static::$booted[$class] = true;
            static::boot();
        }
    }

    protected static function boot(): void
    {
        // Override in child classes
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
        return $this->table;
    }

    // ==================== CONNECTION MANAGEMENT ====================

    /**
     * Set connection using configuration array (original method)
     */

    public static function setConnection(array $config): void
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
                    throw new Exception("Unsupported database driver: {$driver}");
            }

            static::$connection = new PDO($dsn, $username, $password, $pdoOptions);
        } catch (PDOException $e) {
            throw new Exception("Database connection failed: " . $e->getMessage());
        }
    }

    /**
     * Get connection name
     */
    public function getConnectionName(): string
    {
        return static::$connectionName;
    }

    /**
     * Set the connection name for Connection class usage (new method)
     */
    public static function connection(string $name): void
    {
        static::$connectionName = $name;
    }

    protected static function getConnection(): Connection|PDO
    {
        if (static::$connection instanceof PDO) {
            return static::$connection;
        }

        // Otherwise use Connection class
        // return Connection::getInstance(null, static::$connectionName);
        return Connection::getInstance();
    }

    /**
     * Get the PDO instance (enhanced)
     */
    protected static function getPdo(): PDO
    {
        $connection = static::getConnection();

        if ($connection instanceof PDO) {
            return $connection;
        }

        // If using Connection class
        return $connection->getPdo();
    }

    // ==================== GLOBAL SCOPES ====================

    /**
     * Add global scope
     */
    public static function addGlobalScope(string $name, callable $callback)
    {
        static::$globalScopes[static::class][$name] = $callback;
    }

    /**
     * Remove global scope
     */
    public static function removeGlobalScope(string $name)
    {
        unset(static::$globalScopes[static::class][$name]);
    }

    /**
     * Apply global scopes to query
     */
    protected function applyGlobalScopes()
    {
        $class = static::class;
        if (!isset(static::$globalScopes[$class])) {
            return $this;
        }

        $clone = $this;
        foreach (static::$globalScopes[$class] as $scope) {
            $clone = $scope($clone);
        }

        return $clone;
    }

    // ==================== QUERY CACHING ====================

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

    /**
     * Find with caching
     */
    public static function findCached($id, int $ttl = 3600)
    {
        $instance = new static();
        $cacheKey = md5(static::class . "find_{$id}");

        $cached = $instance->getFromCache($cacheKey);
        if ($cached !== null) {
            return $cached;
        }

        $model = static::find($id);

        if ($model) {
            static::$queryCache[$cacheKey] = [
                'data' => $model,
                'expires' => time() + $ttl,
            ];
        }

        return $model;
    }

    public static function clearPreparedStatements(): void
    {
        static::$preparedStatements = [];
    }

    public function useIndex(string $index)
    {
        $this->query['index_hint'] = $index;
        return $this;
    }

    public function remember(?int $ttl = null)
    {
        $clone = $this->cloneQuery();
        $ttl = $ttl ?? static::$cacheTTL;

        $sql = $clone->buildSelectQuery();
        $bindings = $clone->getBindings();
        $cacheKey = $clone->getCacheKey($sql, $bindings);

        $cached = $clone->getFromCache($cacheKey);
        if ($cached !== null) {
            return $cached;
        }

        $result = $clone->get();

        static::$queryCache[$cacheKey] = [
            'data' => $result,
            'expires' => time() + $ttl,
        ];

        return $result;
    }

    /**
     * Preload models efficiently
     */
    public static function preload(array $ids): Collection
    {
        if (empty($ids)) {
            return new Collection([]);
        }

        $ids = array_unique($ids);

        return static::whereIn((new static())->primaryKey, $ids)->get();
    }

    /**
     * Get memory statistics
     */
    public static function getMemoryStats(): array
    {
        return [
            'query_cache_size' => count(static::$queryCache),
            'query_log_size' => count(static::$queryLog),
            'observers_count' => count(static::$observers),
            'global_scopes_count' => isset(static::$globalScopes[static::class]) ?
                count(static::$globalScopes[static::class]) : 0,
        ];
    }

    /**
     * Clear all caches and logs
     */
    public static function clearAll(): void
    {
        static::flushCache();
        static::flushQueryLog();
        static::$preparedStatements = [];
    }

    // ==================== QUERY LOGGING ====================

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

    protected function executeQuery(string $sql, array $bindings = [])
    {
        $startTime = microtime(true);

        try {
            $connection = static::getConnection();

            if ($connection instanceof PDO) {
                // Use original PDO execution
                $stmt = $connection->prepare($sql);
                $stmt->execute($bindings);
            } else {
                // Use Connection class
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

    // ==================== TRANSACTIONS ====================

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

    public static function transaction(callable $callback)
    {
        static::beginTransaction();
        try {
            $result = $callback();
            static::commit();
            return $result;
        } catch (Exception $e) {
            static::rollBack();
            throw $e;
        }
    }

    public static function transactionLevel(): int
    {
        return static::$transactionDepth;
    }

    // ==================== MODEL OBSERVERS ====================

    public static function observe($observer): void
    {
        static::$observers[static::class][] = $observer;
    }

    protected function fireObserverEvent(string $event, ...$args): void
    {
        $class = static::class;
        if (!isset(static::$observers[$class])) {
            return;
        }

        foreach (static::$observers[$class] as $observer) {
            if (method_exists($observer, $event)) {
                $observer->$event($this, ...$args);
            }
        }
    }

    // ==================== VALIDATION ====================

    protected $rules = [];
    protected $messages = [];
    protected $errors = [];

    public function validate(?array $rules = null, ?array $messages = null): bool
    {
        $rules = $rules ?? $this->rules;
        $messages = $messages ?? $this->messages;

        if (empty($rules)) {
            return true;
        }

        $this->errors = [];

        foreach ($rules as $field => $fieldRules) {
            $value = $this->getAttribute($field);
            $ruleList = is_string($fieldRules) ? explode('|', $fieldRules) : $fieldRules;

            foreach ($ruleList as $rule) {
                if (!$this->validateRule($field, $value, $rule, $messages)) {
                    break;
                }
            }
        }

        return empty($this->errors);
    }

    protected function validateRule(string $field, $value, string $rule, array $messages): bool
    {
        $parts = explode(':', $rule, 2);
        $ruleName = $parts[0];
        $params = isset($parts[1]) ? explode(',', $parts[1]) : [];

        $valid = true;
        $message = $messages[$field . '.' . $ruleName] ?? $messages[$ruleName] ?? null;

        switch ($ruleName) {
            case 'required':
                $valid = !empty($value);
                $message = $message ?? "The {$field} field is required.";
                break;
            case 'email':
                $valid = filter_var($value, FILTER_VALIDATE_EMAIL) !== false;
                $message = $message ?? "The {$field} must be a valid email.";
                break;
            case 'min':
                $min = (int) $params[0];
                $valid = is_string($value) ? strlen($value) >= $min : $value >= $min;
                $message = $message ?? "The {$field} must be at least {$min}.";
                break;
            case 'max':
                $max = (int) $params[0];
                $valid = is_string($value) ? strlen($value) <= $max : $value <= $max;
                $message = $message ?? "The {$field} must not exceed {$max}.";
                break;
            case 'unique':
                $table = $params[0] ?? $this->table;
                $column = $params[1] ?? $field;
                $query = static::where($column, $value);
                if ($this->exists) {
                    $query = $query->instanceWhere($this->primaryKey, '!=', $this->getAttribute($this->primaryKey));
                }
                $valid = !$query->exists();
                $message = $message ?? "The {$field} has already been taken.";
                break;
            case 'in':
                $valid = in_array($value, $params);
                $message = $message ?? "The {$field} is invalid.";
                break;
            case 'numeric':
                $valid = is_numeric($value);
                $message = $message ?? "The {$field} must be numeric.";
                break;
            case 'integer':
                $valid = filter_var($value, FILTER_VALIDATE_INT) !== false;
                $message = $message ?? "The {$field} must be an integer.";
                break;
            case 'date':
                $valid = strtotime($value) !== false;
                $message = $message ?? "The {$field} must be a valid date.";
                break;
        }

        if (!$valid) {
            $this->errors[$field][] = $message;
        }

        return $valid;
    }

    public function getErrors(): array
    {
        return $this->errors;
    }

    public function hasErrors(): bool
    {
        return !empty($this->errors);
    }

    // ==================== JSON OPERATIONS ====================

    public function fromJson(string $json)
    {
        $data = json_decode($json, true);
        if (json_last_error() !== JSON_ERROR_NONE) {
            throw new Exception('Invalid JSON: ' . json_last_error_msg());
        }
        return $this->fill($data);
    }

    public static function createFromJson(string $json)
    {
        $instance = new static();
        return $instance->fromJson($json);
    }

    // ==================== QUERY BUILDER ENHANCEMENTS ====================

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

    public function search(string $column, string $term, bool $exact = false)
    {
        $pattern = $exact ? $term : "%{$term}%";
        return $this->whereLike($column, $pattern);
    }

    public function searchMultiple(array $columns, string $term)
    {
        $clone = $this->cloneQuery();
        $pattern = "%{$term}%";

        $clone->query['where'][] = [
            'type' => 'nested',
            'boolean' => 'AND',
            'query' => function ($query) use ($columns, $pattern) {
                foreach ($columns as $index => $column) {
                    if ($index === 0) {
                        $query->instanceWhere($column, 'LIKE', $pattern);
                    } else {
                        $query->instanceOrWhere($column, 'LIKE', $pattern);
                    }
                }
            },
        ];

        return $clone;
    }

    public function inRandomOrder()
    {
        return $this->orderByRaw('RAND()');
    }

    public function random(int $count = 1)
    {
        return $this->inRandomOrder()->limit($count)->get();
    }

    public function orderByRaw(string $expression)
    {
        $clone = $this->cloneQuery();
        $clone->query['orderBy'][] = [
            'type' => 'raw',
            'expression' => $expression,
        ];
        return $clone;
    }

    // ==================== SOFT DELETE ENHANCEMENTS ====================

    public static function withoutTrashed()
    {
        return static::query();
    }

    public static function restoreAll(): bool
    {
        $instance = new static();
        if (!$instance->softDelete) {
            return false;
        }

        $sql = "UPDATE {$instance->table} SET {$instance->deletedAtColumn} = NULL
                WHERE {$instance->deletedAtColumn} IS NOT NULL";

        $instance->executeQuery($sql);
        return true;
    }

    public static function forceDeleteAll(): bool
    {
        $instance = new static();
        if (!$instance->softDelete) {
            return false;
        }

        $sql = "DELETE FROM {$instance->table} WHERE {$instance->deletedAtColumn} IS NOT NULL";
        $instance->executeQuery($sql);
        return true;
    }

    // ==================== POLYMORPHIC RELATIONSHIPS ====================

    public static function morphMap(array $map): void
    {
        static::$morphMap = array_merge(static::$morphMap, $map);
    }

    public static function getMorphedModel(string $alias): string
    {
        return static::$morphMap[$alias] ?? $alias;
    }

    protected function morphTo($name = null, $type = null, $id = null)
    {
        $name = $name ?? debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS, 2)[1]['function'];
        $type = $type ?? $name . '_type';
        $id = $id ?? $name . '_id';

        $class = $this->getAttribute($type);
        $class = static::getMorphedModel($class);

        if (!$class) {
            return null;
        }

        return (new $class())->find($this->getAttribute($id));
    }

    protected function morphMany($related, $name, $type = null, $id = null, $localKey = null)
    {
        $type = $type ?? $name . '_type';
        $id = $id ?? $name . '_id';
        $localKey = $localKey ?? $this->primaryKey;

        $instance = new $related();
        return $instance->instanceWhere($type, static::class)
            ->instanceWhere($id, $this->getAttribute($localKey))
            ->get();
    }

    protected function morphOne($related, $name, $type = null, $id = null, $localKey = null)
    {
        $collection = $this->morphMany($related, $name, $type, $id, $localKey);
        return $collection->first();
    }

    // ==================== ATTRIBUTE CASTING ENHANCEMENTS ====================

    protected function castAttribute($key, $value)
    {
        if (!isset($this->casts[$key]) || $value === null) {
            return $value;
        }

        $castType = $this->casts[$key];

        if ($castType === 'encrypted') {
            return $this->decrypt($value);
        }

        if ($castType === 'collection') {
            $data = is_string($value) ? json_decode($value, true) : $value;
            return new Collection(is_array($data) ? $data : []);
        }

        switch ($castType) {
            case 'int':
            case 'integer':
                return (int) $value;
            case 'float':
            case 'double':
            case 'real':
                return (float) $value;
            case 'decimal':
                return number_format((float) $value, 2, '.', '');
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
            case 'date':
                return $value instanceof DateTime ? $value : new DateTime($value);
            case 'timestamp':
                return is_numeric($value) ? $value : strtotime($value);
            default:
                return $value;
        }
    }

    // protected function encrypt(string $value): string
    // {
    //     return base64_encode($value);
    // }

    // protected function decrypt(string $value): string
    // {
    //     return base64_decode($value);
    // }

    protected function encrypt(string $value): string
    {
        // Requires encryption key in config
        $key = getenv('APP_KEY') ?: throw new Exception('APP_KEY not set');
        $iv = random_bytes(16);
        $encrypted = openssl_encrypt($value, 'AES-256-CBC', $key, 0, $iv);
        return base64_encode($iv . $encrypted);
    }

    protected function decrypt(string $value): string
    {
        $key = getenv('APP_KEY') ?: throw new Exception('APP_KEY not set');
        $data = base64_decode($value);
        $iv = substr($data, 0, 16);
        $encrypted = substr($data, 16);
        return openssl_decrypt($encrypted, 'AES-256-CBC', $key, 0, $iv);
    }

    // ==================== MASS ASSIGNMENT PROTECTION ====================

    public function forceFill(array $attributes)
    {
        foreach ($attributes as $key => $value) {
            $this->setAttribute($key, $value);
        }
        return $this;
    }

    public static function unguarded(callable $callback)
    {
        $instance = new static();
        $originalGuarded = $instance->guarded;
        $instance->guarded = [];

        try {
            return $callback();
        } finally {
            $instance->guarded = $originalGuarded;
        }
    }

    // ==================== INCREMENT & DECREMENT ====================

    public function increment(string $column, int $amount = 1, array $extra = []): bool
    {
        return $this->incrementOrDecrement($column, $amount, $extra, 'increment');
    }

    public function decrement(string $column, int $amount = 1, array $extra = []): bool
    {
        return $this->incrementOrDecrement($column, $amount, $extra, 'decrement');
    }

    protected function incrementOrDecrement(string $column, int $amount, array $extra, string $type): bool
    {
        $operator = $type === 'increment' ? '+' : '-';

        $sets = ["{$column} = {$column} {$operator} ?"];
        $bindings = [$amount];

        foreach ($extra as $key => $value) {
            $sets[] = "{$key} = ?";
            $bindings[] = $value;
        }

        if ($this->timestamps) {
            $sets[] = "updated_at = ?";
            $bindings[] = date('Y-m-d H:i:s');
        }

        $sql = "UPDATE {$this->table} SET " . implode(', ', $sets) .
            " WHERE {$this->primaryKey} = ?";
        $bindings[] = $this->getAttribute($this->primaryKey);

        $this->executeQuery($sql, $bindings);

        // Update local attributes
        $current = $this->getAttribute($column) ?? 0;
        $new = $type === 'increment' ? $current + $amount : $current - $amount;
        $this->setAttribute($column, $new);
        $this->original[$column] = $new;

        foreach ($extra as $key => $value) {
            $this->setAttribute($key, $value);
            $this->original[$key] = $value;
        }

        return true;
    }

    // ==================== TOUCHING ====================

    public function touch(): bool
    {
        if (!$this->timestamps) {
            return false;
        }

        $this->setAttribute('updated_at', date('Y-m-d H:i:s'));
        return $this->save();
    }

    public static function touchAll(array $ids): bool
    {
        $instance = new static();

        if (!$instance->timestamps) {
            return false;
        }

        $placeholders = implode(',', array_fill(0, count($ids), '?'));
        $sql = "UPDATE {$instance->table} SET updated_at = ?
                WHERE {$instance->primaryKey} IN ({$placeholders})";

        $bindings = array_merge([date('Y-m-d H:i:s')], $ids);
        $instance->executeQuery($sql, $bindings);

        return true;
    }

    // ==================== RAW QUERIES ====================

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

    public static function scalar(string $sql, array $bindings = [])
    {
        $instance = new static();
        $stmt = $instance->executeQuery($sql, $bindings);
        return $stmt->fetchColumn();
    }

    // ==================== BATCH OPERATIONS ====================

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
            throw new Exception("Batch insert failed: " . $e->getMessage());
        }
    }

    /**
     * Insert and get ID
     */
    public static function insertGetId(array $values): string
    {
        $instance = new static();
        $columns = implode(', ', array_keys($values));
        $placeholders = implode(', ', array_fill(0, count($values), '?'));

        $sql = "INSERT INTO {$instance->table} ({$columns}) VALUES ({$placeholders})";

        $instance->executeQuery($sql, array_values($values));
        return static::getPdo()->lastInsertId();
    }

    public static function updateMany(array $records): bool
    {
        if (empty($records)) {
            return false;
        }

        static::beginTransaction();

        try {
            $instance = new static();
            foreach ($records as $record) {
                if (!isset($record[$instance->primaryKey])) {
                    throw new Exception('Each record must have a primary key for batch update');
                }

                $id = $record[$instance->primaryKey];
                unset($record[$instance->primaryKey]);

                $setClauses = [];
                $bindings = [];
                foreach ($record as $key => $value) {
                    $setClauses[] = "{$key} = ?";
                    $bindings[] = $value;
                }
                $bindings[] = $id;

                $sql = "UPDATE {$instance->table} SET " . implode(', ', $setClauses) .
                    " WHERE {$instance->primaryKey} = ?";

                $instance->executeQuery($sql, $bindings);
            }

            static::commit();
            return true;
        } catch (Exception $e) {
            static::rollBack();
            throw $e;
        }
    }

    public static function upsert(array $records, array $uniqueKeys, array $updateColumns = []): bool
    {
        $driver = static::getPdo()->getAttribute(PDO::ATTR_DRIVER_NAME);

        if ($driver === 'pgsql') {
            // Use ON CONFLICT
        } elseif ($driver === 'sqlite') {
            // Use INSERT OR REPLACE
        }

        if (empty($records)) {
            return false;
        }

        $instance = new static();
        $columns = array_keys($records[0]);

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
            throw new Exception("Upsert failed: " . $e->getMessage());
        }
    }

    /**
     * Raw update
     */
    public static function updateRaw(array $values, array $where = []): bool
    {
        $instance = new static();

        $setClauses = [];
        $bindings = [];
        foreach ($values as $key => $value) {
            $setClauses[] = "{$key} = ?";
            $bindings[] = $value;
        }

        $sql = "UPDATE {$instance->table} SET " . implode(', ', $setClauses);

        if (!empty($where)) {
            $whereClauses = [];
            foreach ($where as $key => $value) {
                $whereClauses[] = "{$key} = ?";
                $bindings[] = $value;
            }
            $sql .= " WHERE " . implode(' AND ', $whereClauses);
        }

        $instance->executeQuery($sql, $bindings);

        if (static::$cacheEnabled) {
            static::flushCache();
        }

        return true;
    }

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

    public static function each(callable $callback, int $chunkSize = 1000): bool
    {
        return static::chunk($chunkSize, function ($records) use ($callback) {
            foreach ($records as $record) {
                if ($callback($record) === false) {
                    return false;
                }
            }
            return true;
        });
    }

    // ==================== CORE METHODS ====================

    protected function getTableName(): string
    {
        $className = (new \ReflectionClass($this))->getShortName();
        return strtolower($className) . 's';
    }

    public function setTable(string $table)
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

    // protected function isFillable(string $key): bool
    // {
    //     if (in_array('*', $this->guarded)) {
    //         return in_array($key, $this->fillable);
    //     }
    //     return !in_array($key, $this->guarded);
    // }

    protected function isFillable(string $key): bool
    {
        // If both are empty, allow everything
        if (empty($this->fillable) && empty($this->guarded)) {
            return true;
        }

        // If fillable is empty but guarded is not, check guarded
        if (empty($this->fillable)) {
            // If guarded contains '*', nothing is fillable unless explicitly in fillable
            if (in_array('*', $this->guarded)) {
                return false;
            }
            // Otherwise, allow everything not in guarded
            return !in_array($key, $this->guarded);
        }

        // If fillable is set, only allow what's in fillable (unless it's also guarded)
        if (in_array($key, $this->fillable)) {
            return !in_array($key, $this->guarded);
        }

        return false;
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

    protected function validateAttributeType($key, $value)
    {
        if (isset($this->casts[$key])) {
            $cast = $this->casts[$key];
            if ($cast === 'int' && !is_numeric($value)) {
                throw new \InvalidArgumentException("$key must be numeric");
            }
            // Add more validation
        }
    }

    protected function getRelationValue(string $key)
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
        $clone->query = [
            'select' => $this->query['select'],
            'where' => [],
            'joins' => [],
            'orderBy' => [],
            'groupBy' => [],
            'having' => [],
            'limit' => $this->query['limit'],
            'offset' => $this->query['offset'],
            'withTrashed' => $this->query['withTrashed'],
            'distinct' => $this->query['distinct'],
        ];

        // Deep copy arrays
        // $clone->bindings = $this->bindings;

        $clone->bindings = array_map(function ($binding) {
            return is_object($binding) ? clone $binding : $binding;
        }, $this->bindings);

        foreach ($this->query['where'] as $where) {
            $clone->query['where'][] = $where;
        }

        foreach ($this->query['orderBy'] as $order) {
            $clone->query['orderBy'][] = $order;
        }

        foreach ($this->query['groupBy'] as $group) {
            $clone->query['groupBy'][] = $group;
        }

        foreach ($this->query['having'] as $having) {
            $clone->query['having'][] = $having;
        }

        return $clone;
    }

    public static function find($id)
    {
        return static::query()->instanceWhere((new static())->primaryKey, $id)->first();
    }

    public static function findMany(array $ids): Collection
    {
        return static::query()->instanceWhereIn((new static())->primaryKey, $ids)->get();
    }

    public static function findOrFail($id)
    {
        $result = static::find($id);
        if (!$result) {
            throw new Exception("Model not found with ID: {$id}");
        }
        return $result;
    }

    public static function all(): Collection
    {
        return static::query()->get();
    }

    public function select(...$columns)
    {
        $clone = $this->cloneQuery();
        $clone->query['select'] = $columns;
        return $clone;
    }

    public function addSelect(...$columns)
    {
        $clone = $this->cloneQuery();
        $clone->query['select'] = array_merge($clone->query['select'], $columns);
        return $clone;
    }

    // ==================== QUERY BUILDER METHODS WITH STATIC SUPPORT ====================
    public static function where($column, $operator = null, $value = null)
    {
        $instance = static::query();

        // Get the actual number of arguments passed to this static method
        $args = func_get_args();

        // Forward all arguments to instanceWhere
        return call_user_func_array([$instance, 'instanceWhere'], $args);
    }

    public function instanceWhere($column, $operator = null, $value = null)
    {
        $clone = $this->cloneQuery();
        $args = func_num_args();

        if ($args === 1) {
            // Handle callable for nested where
            if (is_callable($column)) {
                return $this->whereNested($column);
            }

            // Handle array syntax
            if (is_array($column)) {
                foreach ($column as $key => $val) {
                    $clone = $clone->instanceWhere($key, '=', $val);
                }
                return $clone;
            }
            throw new Exception("Invalid arguments for where clause");
        } elseif ($args === 2) {
            $value = $operator;
            $operator = '=';
        }

        $clone->query['where'][] = [
            'column' => $column,
            'operator' => $operator,
            'value' => $value,
            'boolean' => 'AND',
            'type' => 'basic',
        ];

        $clone->bindings[] = $value;

        return $clone;
    }

    /**
     * Support for nested where clauses
     */
    protected function whereNested(callable $callback)
    {
        $clone = $this->cloneQuery();
        $query = new static();

        $callback($query);

        $clone->query['where'][] = [
            'type' => 'nested',
            'query' => $query,
            'boolean' => 'AND',
        ];

        $clone->bindings = array_merge($clone->bindings, $query->bindings);

        return $clone;
    }

    public static function orWhere($column, $operator = null, $value = null)
    {
        $instance = static::query();

        // Get the actual number of arguments passed to this static method
        $args = func_get_args();

        // Forward all arguments to instanceOrWhere
        return call_user_func_array([$instance, 'instanceOrWhere'], $args);
    }

    public function instanceOrWhere($column, $operator = null, $value = null)
    {
        $clone = $this->cloneQuery();

        $args = func_num_args();

        if ($args === 1 && is_array($column)) {
            foreach ($column as $key => $val) {
                $clone = $clone->instanceOrWhere($key, '=', $val);
            }
            return $clone;
        } elseif ($args === 2) {
            $value = $operator;
            $operator = '=';
        }

        $clone->query['where'][] = [
            'column' => $column,
            'operator' => $operator,
            'value' => $value,
            'boolean' => 'OR',
            'type' => 'basic',
        ];

        $clone->query['bindings'][] = $value;

        return $clone;
    }

    public static function whereIn(string $column, array $values)
    {
        return static::query()->instanceWhereIn($column, $values);
    }

    public function instanceWhereIn(string $column, array $values)
    {
        $clone = $this->cloneQuery();
        $clone->query['where'][] = [
            'column' => $column,
            'values' => $values,
            'boolean' => 'AND',
            'type' => 'in',
        ];

        // $clone->query['bindings'] = array_merge($clone->query['bindings'], $values);
        $clone->bindings = array_merge($clone->bindings, $values);

        return $clone;
    }

    public static function whereNotIn(string $column, array $values)
    {
        return static::query()->instanceWhereNotIn($column, $values);
    }

    public function instanceWhereNotIn(string $column, array $values)
    {
        $clone = $this->cloneQuery();
        $clone->query['where'][] = [
            'column' => $column,
            'values' => $values,
            'boolean' => 'AND',
            'type' => 'notIn',
        ];

        $clone->query['bindings'] = array_merge($clone->query['bindings'], $values);

        return $clone;
    }

    public static function whereNull(string $column)
    {
        return static::query()->instanceWhereNull($column);
    }

    public function instanceWhereNull(string $column)
    {
        $clone = $this->cloneQuery();
        $clone->query['where'][] = [
            'column' => $column,
            'boolean' => 'AND',
            'type' => 'null',
        ];
        return $clone;
    }

    public static function whereNotNull(string $column)
    {
        return static::query()->instanceWhereNotNull($column);
    }

    public function instanceWhereNotNull(string $column)
    {
        $clone = $this->cloneQuery();
        $clone->query['where'][] = [
            'column' => $column,
            'boolean' => 'AND',
            'type' => 'notNull',
        ];
        return $clone;
    }

    /**
     * Raw where clause
     */
    public function whereRaw(string $sql, array $bindings = [])
    {
        $clone = $this->cloneQuery();
        $clone->query['where'][] = [
            'type' => 'raw',
            'sql' => $sql,
            'boolean' => 'AND',
        ];
        $clone->bindings = array_merge($clone->bindings, $bindings);
        return $clone;
    }

    /**
     * Raw or where clause
     */
    public function orWhereRaw(string $sql, array $bindings = [])
    {
        $clone = $this->cloneQuery();
        $clone->query['where'][] = [
            'type' => 'raw',
            'sql' => $sql,
            'boolean' => 'OR',
        ];
        $clone->bindings = array_merge($clone->bindings, $bindings);
        return $clone;
    }

    public function groupBy(...$columns)
    {
        $clone = $this->cloneQuery();
        $clone->query['groupBy'] = array_merge($clone->query['groupBy'], $columns);
        return $clone;
    }

    public function having(string $column, string $operator, $value)
    {
        $clone = $this->cloneQuery();
        $clone->query['having'][] = [
            'column' => $column,
            'operator' => $operator,
            'value' => $value,
        ];

        $clone->query['bindings'][] = $value;

        return $clone;
    }

    public static function orderBy(string $column, string $direction = 'ASC')
    {
        return static::query()->instanceOrderBy($column, $direction);
    }

    public function instanceOrderBy(string $column, string $direction = 'ASC')
    {
        $clone = $this->cloneQuery();
        $clone->query['orderBy'][] = [
            'column' => $column,
            'direction' => strtoupper($direction),
        ];
        return $clone;
    }

    public static function orderByDesc(string $column)
    {
        return static::orderBy($column, 'DESC');
    }

    public function orderByDescInstance(string $column)
    {
        return $this->instanceOrderBy($column, 'DESC');
    }

    public static function latest(string $column = 'created_at')
    {
        return static::orderBy($column, 'DESC');
    }

    public function latestInstance(string $column = 'created_at')
    {
        return $this->instanceOrderBy($column, 'DESC');
    }

    public static function oldest(string $column = 'created_at')
    {
        return static::orderBy($column, 'ASC');
    }

    public function oldestInstance(string $column = 'created_at')
    {
        return $this->instanceOrderBy($column, 'ASC');
    }

    public function limit(int $limit)
    {
        $clone = $this->cloneQuery();
        $clone->query['limit'] = $limit;
        return $clone;
    }

    // ==================== NEW UTILITY METHODS ====================

    /**
     * Get single record or throw if multiple/no records
     */
    public function sole()
    {
        $results = $this->take(2)->get();

        if ($results->count() === 0) {
            throw new Exception("No records found");
        }

        if ($results->count() > 1) {
            throw new Exception("Multiple records found");
        }

        return $results->first();
    }

    /**
     * Get single column value from first result
     */
    public function value(string $column)
    {
        $result = $this->first();
        return $result ? $result->getAttribute($column) : null;
    }

    /**
     * Pluck column values
     */
    public function pluck(string $column, ?string $key = null)
    {
        $results = $this->get();

        if ($key) {
            return $results->pluck($column, $key);
        }

        return $results->pluck($column);
    }

    public function take(int $limit)
    {
        return $this->limit($limit);
    }

    public function offset(int $offset)
    {
        $clone = $this->cloneQuery();
        $clone->query['offset'] = $offset;
        return $clone;
    }

    public function skip(int $offset)
    {
        return $this->offset($offset);
    }

    public function when($condition, callable $callback, ?callable $default = null)
    {
        if ($condition) {
            return $callback($this) ?? $this;
        } elseif ($default) {
            return $default($this) ?? $this;
        }
        return $this;
    }

    public function unless($condition, callable $callback, ?callable $default = null)
    {
        return $this->when(!$condition, $callback, $default);
    }

    public function tap(callable $callback)
    {
        $callback($this);
        return $this;
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
            throw new Exception("No query results found");
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

    /**
     * Simple pagination without total count
     */
    public function simplePaginate(int $perPage = 15, int $page = 1): array
    {
        $clone = $this->cloneQuery();
        $clone->query['limit'] = $perPage + 1;
        $clone->query['offset'] = ($page - 1) * $perPage;
        $items = $clone->get();

        $hasMore = $items->count() > $perPage;
        if ($hasMore) {
            $items = $items->take($perPage);
        }

        return [
            'data' => $items,
            'current_page' => $page,
            'per_page' => $perPage,
            'from' => (($page - 1) * $perPage) + 1,
            'to' => (($page - 1) * $perPage) + $items->count(),
            'has_more' => $hasMore,
            'next_page_url' => $hasMore ? "?page=" . ($page + 1) : null,
            'prev_page_url' => $page > 1 ? "?page=" . ($page - 1) : null,
            'path' => '?page=',
        ];
    }

    public function paginate(int $perPage = 15, int $page = 1): array
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
            'path' => '?page=',
        ];
    }

    /**
     * Chunk by ID for better performance with large datasets
     */
    public static function chunkById(int $size, callable $callback, ?string $column = null): bool
    {
        $instance = new static();
        $column = $column ?? $instance->primaryKey;
        $lastId = 0;

        do {
            $results = static::query()
                ->where($column, '>', $lastId)
                ->orderBy($column)
                ->limit($size)
                ->get();

            if ($results->isEmpty()) {
                break;
            }

            if ($callback($results) === false) {
                return false;
            }

            $lastId = $results->last()->getAttribute($column);
            unset($results);
        } while (true);

        return true;
    }

    /**
     * Cursor for memory-efficient iteration
     */
    public static function cursor()
    {
        $instance = new static();
        $sql = $instance->buildSelectQuery();
        $bindings = $instance->bindings;

        $stmt = $instance->executeQuery($sql, $bindings);

        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            yield $instance->newFromBuilder($row);
        }
    }

    // public function get(): Collection
    // {
    //     $this->fireModelEvent('retrieving');
    //     $sql      = $this->buildSelectQuery();
    //     $bindings = $this->getBindings();
    //     $stmt     = $this->executeQuery($sql, $bindings);
    //     $results  = $stmt->fetchAll(PDO::FETCH_ASSOC);

    //     $models = array_map(function ($result) {
    //         return $this->newFromBuilder($result);
    //     }, $results);

    //     $this->fireModelEvent('retrieved');
    //     return new Collection($models);
    // }

    /**
     * Execute query and load all eager relations
     */
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

        $collection = new Collection($models);

        // Eager load all queued relations
        if (!empty($this->eagerLoad) && !$collection->isEmpty()) {
            $collection = $this->eagerLoadRelations($collection);
        }

        $this->fireModelEvent('retrieved');
        return $collection;
    }

    protected function buildSelectQuery(): string
    {
        $distinct = $this->query['distinct'] ? 'DISTINCT ' : '';
        $sql = "SELECT {$distinct}" . implode(', ', $this->query['select']) . " FROM {$this->table}";

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
                if (isset($order['type']) && $order['type'] === 'raw') {
                    return $order['expression'];
                }
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

    protected function buildWhereClause(): string
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
                case 'nested':
                    $clause = $where['query']($this)->buildWhereClause();
                    break;
            }

            if ($index > 0 && $where['type'] !== 'nested') {
                $clause = "{$where['boolean']} {$clause}";
            }
            $clauses[] = $clause;
        }
        return implode(' ', $clauses);
    }

    protected function getBindings(): array
    {
        // return $this->query['bindings'] ?? [];
        return $this->bindings;
    }

    /**
     * Get the SQL query that would be executed (for debugging)
     */
    public function toSql(): string
    {
        return $this->buildSelectQuery();
    }

    /**
     * Get the bindings for debugging
     */
    public function getBindingsArray(): array
    {
        return $this->getBindings();
    }

    /**
     * Debug the query - shows SQL and bindings
     */
    public function dd()
    {
        echo "SQL: " . $this->toSql() . "\n";
        echo "Bindings: " . json_encode($this->getBindingsArray()) . "\n";
        die();
    }

    public function dump()
    {
        echo "<pre>";
        echo "SQL: " . $this->toSql() . "\n\n";
        echo "Bindings: " . print_r($this->getBindingsArray(), true) . "\n\n";
        echo "Query State: " . print_r($this->query, true);
        echo "</pre>";
        // return $this;
        die();
    }

    /**
     * Create a new model instance from raw attributes
     * This is a public alternative to newFromBuilder, useful for creating
     * model instances from database results outside the model itself.
     * 
     * @param array $attributes Raw database attributes
     * @param bool $exists Whether the model exists in database (default: true)
     * @return static
     */
    public static function hydrate(array $attributes, bool $exists = true): self
    {
        $instance = new static();
        $instance->exists = $exists;
        $instance->attributes = $attributes;
        $instance->original = $attributes;

        return $instance;
    }

    /**
     * Create a collection of models from an array of raw attributes
     * 
     * @param array $items Array of attribute arrays from database
     * @return Collection
     */
    public static function hydrateRaw(array $items): Collection
    {
        if (empty($items)) {
            return new Collection([]);
        }

        $models = array_map(function ($item) {
            return static::hydrate($item);
        }, $items);

        return new Collection($models);
    }

    /**
     * Create a model instance and fill it with attributes (convenience method)
     * Similar to hydrate but specifically for new (non-existing) records
     * 
     * @param array $attributes
     * @return static
     */
    public static function make(array $attributes): self
    {
        return static::hydrate($attributes, false);
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

        $this->fireObserverEvent('saving');

        if ($this->exists) {
            $saved = $this->performUpdate();
        } else {
            $saved = $this->performInsert();
        }

        if ($saved) {
            $this->fireModelEvent('saved');
            $this->fireObserverEvent('saved');
        }

        return $saved;
    }

    protected function performInsert(): bool
    {
        if ($this->fireModelEvent('creating') === false) {
            return false;
        }

        $this->fireObserverEvent('creating');

        if ($this->timestamps) {
            $now = date('Y-m-d H:i:s');

            // Only set if not already set
            if (!isset($this->attributes['created_at'])) {
                $this->setAttribute('created_at', $now);
            }
            if (!isset($this->attributes['updated_at'])) {
                $this->setAttribute('updated_at', $now);
            }
        }

        $attributes = $this->attributes;

        if (empty($attributes)) {
            return false;
        }

        $columns = implode(', ', array_keys($attributes));
        $placeholders = implode(', ', array_fill(0, count($attributes), '?'));
        $values = array_values($attributes);

        $sql = "INSERT INTO {$this->table} ({$columns}) VALUES ({$placeholders})";

        try {
            // $stmt = $this->executeQuery($sql, array_values($attributes));
            $stmt = $this->executeQuery($sql, $values);
            // $lastId = static::getConnection()->lastInsertId();
            // Get last insert ID
            $connection = static::getConnection();
            if ($connection instanceof PDO) {
                $lastId = $connection->lastInsertId();
            } else {
                $lastId = $connection->getPdo()->lastInsertId();
            }

            if ($lastId) {
                $this->setAttribute($this->primaryKey, $lastId);
            }
            $this->exists = true;
            $this->original = $this->attributes;
            $this->fireModelEvent('created');
            $this->fireObserverEvent('created');
            return true;
        } catch (PDOException $e) {
            throw new Exception("Insert failed: " . $e->getMessage());
        }
    }

    protected function performUpdate(): bool
    {
        if ($this->fireModelEvent('updating') === false) {
            return false;
        }

        $this->fireObserverEvent('updating');

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
            $this->fireObserverEvent('updated');
            return true;
        } catch (PDOException $e) {
            throw new Exception("Update failed: " . $e->getMessage());
        }
    }

    protected function getDirty(): array
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

    // public static function create(array $attributes)
    // {
    //     $instance = new static($attributes);
    //     $instance->save();
    //     return $instance;
    // }

    public static function create(array $attributes)
    {
        try {
            $instance = new static($attributes);

            $saved = $instance->save();

            if (!$saved) {
                return null;
            }

            return $instance;
        } catch (Exception $e) {
            throw $e;
        }
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
            $instance = $instance->instanceWhere($key, $value);
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
            $instance = $instance->instanceWhere($key, $value);
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
            $instance = $instance->instanceWhere($key, $value);
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

        $this->fireObserverEvent('deleting');

        if ($this->softDelete) {
            return $this->performSoftDelete();
        }

        $sql = "DELETE FROM {$this->table} WHERE {$this->primaryKey} = ?";

        try {
            $this->executeQuery($sql, [$this->getAttribute($this->primaryKey)]);
            $this->exists = false;
            $this->fireModelEvent('deleted');
            $this->fireObserverEvent('deleted');
            return true;
        } catch (PDOException $e) {
            throw new Exception("Delete failed: " . $e->getMessage());
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
        if (!$this->softDelete) {
            return $this;
        }

        $clone = $this->cloneQuery();
        $clone->query['withTrashed'] = true;
        return $clone->instanceWhereNotNull($this->deletedAtColumn);
    }

    public function trashed(): bool
    {
        return $this->softDelete && !is_null($this->getAttribute($this->deletedAtColumn));
    }

    /**
     * Destroy multiple records efficiently
     */
    public static function destroyMany(array $ids): int
    {
        if (empty($ids)) {
            return 0;
        }

        $instance = new static();

        if ($instance->softDelete) {
            $placeholders = implode(',', array_fill(0, count($ids), '?'));
            $sql = "UPDATE {$instance->table} SET {$instance->deletedAtColumn} = ?
                    WHERE {$instance->primaryKey} IN ({$placeholders})";
            $bindings = array_merge([date('Y-m-d H:i:s')], $ids);
        } else {
            $placeholders = implode(',', array_fill(0, count($ids), '?'));
            $sql = "DELETE FROM {$instance->table} WHERE {$instance->primaryKey} IN ({$placeholders})";
            $bindings = $ids;
        }

        $stmt = $instance->executeQuery($sql, $bindings);

        if (static::$cacheEnabled) {
            static::flushCache();
        }

        return $stmt->rowCount();
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
        // $attributes = $this->attributes;

        $attributes = [];

        // Apply casting to all attributes
        foreach ($this->attributes as $key => $value) {
            $attributes[$key] = $this->castAttribute($key, $value);
        }

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

    /**
     * Get fresh instance with relations
     */
    public function fresh(array $with = [])
    {
        if (!$this->exists) {
            return null;
        }

        $key = $this->getAttribute($this->primaryKey);

        if (empty($with)) {
            return static::find($key);
        }

        return static::where($this->primaryKey, $key)->with($with)->first();
    }

    /**
     * Check if attributes were changed after save
     */
    public function wasChanged($attributes = null): bool
    {
        return $this->isDirty($attributes);
    }

    /**
     * Replicate model
     */
    public function replicate(array $except = []): self
    {
        $defaults = [
            $this->primaryKey,
            'created_at',
            'updated_at',
        ];

        if ($this->softDelete) {
            $defaults[] = $this->deletedAtColumn;
        }

        $except = array_merge($defaults, $except);

        $attributes = array_diff_key($this->attributes, array_flip($except));

        $instance = new static();
        $instance->attributes = $attributes;

        return $instance;
    }

    /**
     * Compare models
     */
    public function is(?self $model): bool
    {
        return $model instanceof static &&
            $this->getAttribute($this->primaryKey) === $model->getAttribute($model->primaryKey) &&
            $this->table === $model->table;
    }

    public function isNot(?self $model): bool
    {
        return !$this->is($model);
    }

    // ==================== RELATIONSHIPS ====================

    protected function hasOne($related, $foreignKey = null, $localKey = null)
    {
        $foreignKey = $foreignKey ?? strtolower(class_basename(static::class)) . '_id';
        $localKey = $localKey ?? $this->primaryKey;
        $instance = new $related();
        return $instance->instanceWhere($foreignKey, $this->getAttribute($localKey))->first();
    }

    protected function hasMany($related, $foreignKey = null, $localKey = null): Collection
    {
        $foreignKey = $foreignKey ?? strtolower(class_basename(static::class)) . '_id';
        $localKey = $localKey ?? $this->primaryKey;
        $instance = new $related();
        return $instance->instanceWhere($foreignKey, $this->getAttribute($localKey))->get();
    }

    protected function belongsTo($related, $foreignKey = null, $ownerKey = null)
    {
        $foreignKey = $foreignKey ?? strtolower(class_basename($related)) . '_id';
        $ownerKey = $ownerKey ?? 'id';
        $instance = new $related();
        return $instance->instanceWhere($ownerKey, $this->getAttribute($foreignKey))->first();
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
                strtolower(class_basename($related)),
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

    // public function with($relations)
    // {
    //     if (is_string($relations)) {
    //         $relations = func_get_args();
    //     }

    //     $results = $this->get();

    //     if ($results->isEmpty()) {
    //         return $results;
    //     }

    //     foreach ($relations as $relation) {
    //         $this->eagerLoadRelation($results, $relation);
    //     }

    //     return $results;
    // }

    /**
     * Enhanced with() method that properly queues relations for eager loading
     */
    public function with($relations)
    {
        if (is_string($relations)) {
            $relations = func_get_args();
        }

        $clone = $this->cloneQuery();

        foreach ($relations as $key => $value) {
            // Support for nested relations: 'posts.comments'
            // Support for constraints: ['posts' => function($query) { $query->where(...) }]
            if (is_numeric($key)) {
                $clone->eagerLoad[$value] = null;
            } else {
                $clone->eagerLoad[$key] = $value;
            }
        }

        return $clone;
    }

    /**
     * Main eager loading orchestrator
     * Handles nested relations like 'posts.comments.author'
     */
    protected function eagerLoadRelations(Collection $models): Collection
    {
        foreach ($this->eagerLoad as $relation => $constraints) {
            // Handle nested relations
            if (strpos($relation, '.') !== false) {
                $models = $this->eagerLoadNestedRelations($models, $relation, $constraints);
            } else {
                $models = $this->eagerLoadRelation($models, $relation, $constraints);
            }
        }

        return $models;
    }

    /**
     * Eager load a single relation on a collection of models
     * This is the FIXED version that eliminates N+1 queries
     */
    protected function eagerLoadRelation(Collection $models, string $relation, $constraints = null): Collection
    {
        $firstModel = $models->first();

        // Verify relation method exists
        if (!method_exists($firstModel, $relation)) {
            throw new BadMethodCallException("Relation method '{$relation}' does not exist on " . get_class($firstModel));
        }

        // Detect relation type by calling the method and inspecting what it returns
        $relationType = $this->detectRelationType($firstModel, $relation);

        // Get the appropriate eager loader method
        $loaderMethod = static::$relationLoaders[$relationType] ?? null;

        if (!$loaderMethod) {
            throw new Exception("Unsupported relation type: {$relationType}");
        }

        // Execute the eager loading strategy
        return $this->$loaderMethod($models, $relation, $constraints);
    }

    // protected function eagerLoadRelation(Collection $models, string $relation)
    // {
    //     $firstModel = $models->first();

    //     if (! method_exists($firstModel, $relation)) {
    //         return;
    //     }

    //     $ids = $models->pluck($this->primaryKey)->toArray();
    //     // Load all relations in ONE query
    //     $relatedModels = $this->$relation()->whereIn('foreign_key', $ids)->get();

    //     // Load relation for each model
    //     foreach ($models as $model) {
    //         $model->load($relation);
    //     }
    // }

    /**
     * Detect what type of relation a method returns
     */
    protected function detectRelationType($model, string $relation): string
    {
        // Create a reflection of the relation method
        $reflection = new \ReflectionMethod($model, $relation);
        $code = $this->getMethodCode($reflection);

        // Detect relation type by method calls in the code
        if (preg_match('/->hasOne\s*\(/', $code)) {
            return 'hasOne';
        } elseif (preg_match('/->hasMany\s*\(/', $code)) {
            return 'hasMany';
        } elseif (preg_match('/->belongsTo\s*\(/', $code)) {
            return 'belongsTo';
        } elseif (preg_match('/->belongsToMany\s*\(/', $code)) {
            return 'belongsToMany';
        } elseif (preg_match('/->morphTo\s*\(/', $code)) {
            return 'morphTo';
        } elseif (preg_match('/->morphMany\s*\(/', $code)) {
            return 'morphMany';
        }

        // Fallback: try to execute and check return type
        $result = $model->$relation();

        if ($result instanceof Collection) {
            return 'hasMany';
        } elseif ($result instanceof PlugModel || $result === null) {
            return 'belongsTo'; // or hasOne
        }

        throw new Exception("Could not detect relation type for: {$relation}");
    }

    /**
     * Get method source code for analysis
     */
    protected function getMethodCode(\ReflectionMethod $method): string
    {
        $filename = $method->getFileName();
        $startLine = $method->getStartLine();
        $endLine = $method->getEndLine();

        if (!$filename) {
            return '';
        }

        $lines = file($filename);
        $methodLines = array_slice($lines, $startLine - 1, $endLine - $startLine + 1);

        return implode('', $methodLines);
    }

    /**
     * Eager load hasOne relation
     * One parent -> One child
     */
    protected function eagerLoadHasOne(Collection $models, string $relation, $constraints = null): Collection
    {
        // Get relation configuration from first model
        $sampleModel = $models->first();
        $relationConfig = $this->getRelationConfig($sampleModel, $relation, 'hasOne');

        $relatedClass = $relationConfig['related'];
        $foreignKey = $relationConfig['foreignKey'];
        $localKey = $relationConfig['localKey'];

        // Get all parent IDs
        $parentIds = $models->pluck($localKey)->unique()->filter()->toArray();

        if (empty($parentIds)) {
            return $models;
        }

        // Load all related models in ONE query
        $query = (new $relatedClass())->whereIn($foreignKey, $parentIds);

        // Apply constraints if provided
        if (is_callable($constraints)) {
            $constraints($query);
        }

        $relatedModels = $query->get();

        // Create a map: parent_id => related_model
        $relationMap = [];
        foreach ($relatedModels as $relatedModel) {
            $key = $relatedModel->getAttribute($foreignKey);
            $relationMap[$key] = $relatedModel;
        }

        // Assign relations to parent models
        foreach ($models as $model) {
            $parentKey = $model->getAttribute($localKey);
            $model->setRelation($relation, $relationMap[$parentKey] ?? null);
        }

        return $models;
    }

    /**
     * Eager load hasMany relation
     * One parent -> Many children
     */
    protected function eagerLoadHasMany(Collection $models, string $relation, $constraints = null): Collection
    {
        $sampleModel = $models->first();
        $relationConfig = $this->getRelationConfig($sampleModel, $relation, 'hasMany');

        $relatedClass = $relationConfig['related'];
        $foreignKey = $relationConfig['foreignKey'];
        $localKey = $relationConfig['localKey'];

        // Get all parent IDs
        $parentIds = $models->pluck($localKey)->unique()->filter()->toArray();

        if (empty($parentIds)) {
            // Set empty collections for all models
            foreach ($models as $model) {
                $model->setRelation($relation, new Collection([]));
            }
            return $models;
        }

        // Load all related models in ONE query
        $query = (new $relatedClass())->whereIn($foreignKey, $parentIds);

        if (is_callable($constraints)) {
            $constraints($query);
        }

        $relatedModels = $query->get();

        // Group related models by parent ID
        $relationMap = [];
        foreach ($relatedModels as $relatedModel) {
            $key = $relatedModel->getAttribute($foreignKey);
            if (!isset($relationMap[$key])) {
                $relationMap[$key] = [];
            }
            $relationMap[$key][] = $relatedModel;
        }

        // Assign relations to parent models
        foreach ($models as $model) {
            $parentKey = $model->getAttribute($localKey);
            $related = $relationMap[$parentKey] ?? [];
            $model->setRelation($relation, new Collection($related));
        }

        return $models;
    }

    /**
     * Eager load belongsTo relation
     * Many children -> One parent
     */
    protected function eagerLoadBelongsTo(Collection $models, string $relation, $constraints = null): Collection
    {
        $sampleModel = $models->first();
        $relationConfig = $this->getRelationConfig($sampleModel, $relation, 'belongsTo');

        $relatedClass = $relationConfig['related'];
        $foreignKey = $relationConfig['foreignKey'];
        $ownerKey = $relationConfig['ownerKey'];

        // Get all foreign key values
        $foreignIds = $models->pluck($foreignKey)->unique()->filter()->toArray();

        if (empty($foreignIds)) {
            // Set null for all models
            foreach ($models as $model) {
                $model->setRelation($relation, null);
            }
            return $models;
        }

        // Load all parent models in ONE query
        $query = (new $relatedClass())->whereIn($ownerKey, $foreignIds);

        if (is_callable($constraints)) {
            $constraints($query);
        }

        $relatedModels = $query->get();

        // Create a map: owner_key => parent_model
        $relationMap = [];
        foreach ($relatedModels as $relatedModel) {
            $key = $relatedModel->getAttribute($ownerKey);
            $relationMap[$key] = $relatedModel;
        }

        // Assign relations to child models
        foreach ($models as $model) {
            $foreignId = $model->getAttribute($foreignKey);
            $model->setRelation($relation, $relationMap[$foreignId] ?? null);
        }

        return $models;
    }

    /**
     * Eager load belongsToMany relation
     * Many to Many through pivot table
     */
    protected function eagerLoadBelongsToMany(Collection $models, string $relation, $constraints = null): Collection
    {
        $sampleModel = $models->first();
        $relationConfig = $this->getRelationConfig($sampleModel, $relation, 'belongsToMany');

        $relatedClass = $relationConfig['related'];
        $pivotTable = $relationConfig['pivotTable'];
        $foreignPivotKey = $relationConfig['foreignPivotKey'];
        $relatedPivotKey = $relationConfig['relatedPivotKey'];
        $parentKey = $relationConfig['parentKey'];
        $relatedKey = $relationConfig['relatedKey'];

        // Get all parent IDs
        $parentIds = $models->pluck($parentKey)->unique()->filter()->toArray();

        if (empty($parentIds)) {
            foreach ($models as $model) {
                $model->setRelation($relation, new Collection([]));
            }
            return $models;
        }

        $relatedInstance = new $relatedClass();
        $relatedTable = $relatedInstance->getTable();

        // Build the query with pivot join
        $placeholders = implode(',', array_fill(0, count($parentIds), '?'));

        $sql = "SELECT r.*, p.{$foreignPivotKey} as pivot_parent_id, p.*
            FROM {$pivotTable} p
            INNER JOIN {$relatedTable} r ON r.{$relatedKey} = p.{$relatedPivotKey}
            WHERE p.{$foreignPivotKey} IN ({$placeholders})";

        // Apply constraints if needed (more complex, would need query builder)
        $stmt = $sampleModel->executeQuery($sql, $parentIds);
        $results = $stmt->fetchAll(PDO::FETCH_ASSOC);

        // Group related models by parent ID
        $relationMap = [];
        foreach ($results as $row) {
            $parentId = $row['pivot_parent_id'];

            // Extract pivot data
            $pivotData = [];
            $modelData = [];

            foreach ($row as $key => $value) {
                if (strpos($key, 'pivot_') === 0 || $key === $foreignPivotKey || $key === $relatedPivotKey) {
                    $pivotData[$key] = $value;
                } else {
                    $modelData[$key] = $value;
                }
            }

            $relatedModel = $relatedInstance->newFromBuilder($modelData);
            $relatedModel->setAttribute('pivot', $pivotData);

            if (!isset($relationMap[$parentId])) {
                $relationMap[$parentId] = [];
            }
            $relationMap[$parentId][] = $relatedModel;
        }

        // Assign relations to parent models
        foreach ($models as $model) {
            $parentId = $model->getAttribute($parentKey);
            $related = $relationMap[$parentId] ?? [];
            $model->setRelation($relation, new Collection($related));
        }

        return $models;
    }

    /**
     * Eager load morphTo relation (polymorphic belongsTo)
     */
    protected function eagerLoadMorphTo(Collection $models, string $relation, $constraints = null): Collection
    {
        $sampleModel = $models->first();
        $relationConfig = $this->getRelationConfig($sampleModel, $relation, 'morphTo');

        $typeColumn = $relationConfig['typeColumn'];
        $idColumn = $relationConfig['idColumn'];

        // Group models by their morph type
        $morphMap = [];
        foreach ($models as $model) {
            $type = $model->getAttribute($typeColumn);
            $id = $model->getAttribute($idColumn);

            if (!$type || !$id) {
                continue;
            }

            // Get actual class name from morph map
            $class = static::getMorphedModel($type);

            if (!isset($morphMap[$class])) {
                $morphMap[$class] = [];
            }
            $morphMap[$class][] = $id;
        }

        // Load each morph type separately
        $loadedModels = [];
        foreach ($morphMap as $class => $ids) {
            $query = (new $class())->whereIn('id', array_unique($ids));

            if (is_callable($constraints)) {
                $constraints($query);
            }

            $relatedModels = $query->get();

            foreach ($relatedModels as $relatedModel) {
                $loadedModels[$class][$relatedModel->getKey()] = $relatedModel;
            }
        }

        // Assign relations to models
        foreach ($models as $model) {
            $type = $model->getAttribute($typeColumn);
            $id = $model->getAttribute($idColumn);
            $class = static::getMorphedModel($type);

            $relatedModel = $loadedModels[$class][$id] ?? null;
            $model->setRelation($relation, $relatedModel);
        }

        return $models;
    }

    /**
     * Eager load morphMany relation (polymorphic hasMany)
     */
    protected function eagerLoadMorphMany(Collection $models, string $relation, $constraints = null): Collection
    {
        $sampleModel = $models->first();
        $relationConfig = $this->getRelationConfig($sampleModel, $relation, 'morphMany');

        $relatedClass = $relationConfig['related'];
        $morphType = $relationConfig['morphType'];
        $morphId = $relationConfig['morphId'];
        $localKey = $relationConfig['localKey'];

        // Get all parent IDs
        $parentIds = $models->pluck($localKey)->unique()->filter()->toArray();

        if (empty($parentIds)) {
            foreach ($models as $model) {
                $model->setRelation($relation, new Collection([]));
            }
            return $models;
        }

        // Load all related models in ONE query
        $query = (new $relatedClass())
            ->where($morphType, get_class($sampleModel))
            ->whereIn($morphId, $parentIds);

        if (is_callable($constraints)) {
            $constraints($query);
        }

        $relatedModels = $query->get();

        // Group by parent ID
        $relationMap = [];
        foreach ($relatedModels as $relatedModel) {
            $key = $relatedModel->getAttribute($morphId);
            if (!isset($relationMap[$key])) {
                $relationMap[$key] = [];
            }
            $relationMap[$key][] = $relatedModel;
        }

        // Assign relations
        foreach ($models as $model) {
            $parentKey = $model->getAttribute($localKey);
            $related = $relationMap[$parentKey] ?? [];
            $model->setRelation($relation, new Collection($related));
        }

        return $models;
    }

    /**
     * Handle nested relations like 'posts.comments.author'
     */
    protected function eagerLoadNestedRelations(Collection $models, string $relations, $constraints = null): Collection
    {
        $parts = explode('.', $relations);
        $firstRelation = array_shift($parts);
        $nestedRelation = implode('.', $parts);

        // Load first level
        $models = $this->eagerLoadRelation($models, $firstRelation, function ($query) use ($nestedRelation, $constraints) {
            // Queue nested relation on the child query
            $query->with($nestedRelation);

            if (is_callable($constraints)) {
                $constraints($query);
            }
        });

        return $models;
    }

    /**
     * Extract relation configuration from relation method
     * This parses the relation method to get its parameters
     * FIXED VERSION - more robust extraction
     */
    protected function getRelationConfig($model, string $relation, string $type): array
    {
        $reflection = new \ReflectionMethod($model, $relation);
        $code = $this->getMethodCode($reflection);

        $config = [];

        // Try multiple patterns to extract the related class
        $classPatterns = [
            // Pattern 1: $related = ClassName::class or 'ClassName'
            '/\$related\s*=\s*([A-Za-z0-9_\\\\]+)::class/',
            '/\$related\s*=\s*[\'"]([A-Za-z0-9_\\\\]+)[\'"]/',

            // Pattern 2: new ClassName()
            '/new\s+([A-Za-z0-9_\\\\]+)\s*\(/',

            // Pattern 3: ->hasMany(ClassName::class)
            '/->' . $type . '\s*\(\s*([A-Za-z0-9_\\\\]+)::class/',

            // Pattern 4: ->hasMany('ClassName')
            '/->' . $type . '\s*\(\s*[\'"]([A-Za-z0-9_\\\\]+)[\'"]/',

            // Pattern 5: return $this->hasMany(ClassName::class)
            '/return\s+\$this->' . $type . '\s*\(\s*([A-Za-z0-9_\\\\]+)::class/',

            // Pattern 6: Direct call with variable
            '/\$this->' . $type . '\s*\(\s*\$([a-zA-Z_]+)/',
        ];

        $relatedClass = null;
        foreach ($classPatterns as $pattern) {
            if (preg_match($pattern, $code, $matches)) {
                $relatedClass = $matches[1];

                // If it's a variable name, try to find its value
                if (ctype_lower($relatedClass[0])) {
                    // It's a variable, look for its assignment
                    $varPattern = '/\$' . $relatedClass . '\s*=\s*([A-Za-z0-9_\\\\]+)::class/';
                    if (preg_match($varPattern, $code, $varMatches)) {
                        $relatedClass = $varMatches[1];
                    }
                }
                break;
            }
        }

        if (!$relatedClass) {
            throw new Exception("Could not extract related class for relation: {$relation} in " . get_class($model));
        }

        // Handle relative class names (without namespace)
        if (strpos($relatedClass, '\\') === false) {
            // Try to resolve using the model's namespace
            $modelNamespace = (new \ReflectionClass($model))->getNamespaceName();
            $fullClassName = $modelNamespace . '\\' . $relatedClass;

            // Check if the class exists
            if (class_exists($fullClassName)) {
                $relatedClass = $fullClassName;
            } elseif (!class_exists($relatedClass)) {
                // Try common namespaces
                $commonNamespaces = [
                    'App\\Models\\',
                    'App\\Model\\',
                    'Models\\',
                ];

                foreach ($commonNamespaces as $namespace) {
                    $testClass = $namespace . $relatedClass;
                    if (class_exists($testClass)) {
                        $relatedClass = $testClass;
                        break;
                    }
                }
            }
        }

        $config['related'] = $relatedClass;

        // Extract parameters based on relation type
        switch ($type) {
            case 'hasOne':
            case 'hasMany':
                $config['foreignKey'] = $this->extractParameter($code, 'foreignKey') ?? strtolower(class_basename(get_class($model))) . '_id';
                $config['localKey'] = $this->extractParameter($code, 'localKey') ?? $model->getKeyName();
                break;

            case 'belongsTo':
                $config['foreignKey'] = $this->extractParameter($code, 'foreignKey') ?? strtolower($relation) . '_id';
                $config['ownerKey'] = $this->extractParameter($code, 'ownerKey') ?? 'id';
                break;

            case 'belongsToMany':
                $config['pivotTable'] = $this->extractParameter($code, 'pivotTable') ?? $this->getPivotTableName($model, $relatedClass);
                $config['foreignPivotKey'] = $this->extractParameter($code, 'foreignPivotKey') ?? strtolower(class_basename(get_class($model))) . '_id';
                $config['relatedPivotKey'] = $this->extractParameter($code, 'relatedPivotKey') ?? strtolower(class_basename($relatedClass)) . '_id';
                $config['parentKey'] = $this->extractParameter($code, 'parentKey') ?? $model->getKeyName();
                $config['relatedKey'] = $this->extractParameter($code, 'relatedKey') ?? 'id';
                break;

            case 'morphTo':
                $config['typeColumn'] = $this->extractParameter($code, 'type') ?? $relation . '_type';
                $config['idColumn'] = $this->extractParameter($code, 'id') ?? $relation . '_id';
                break;

            case 'morphMany':
                $config['morphType'] = $this->extractParameter($code, 'type') ?? $relation . '_type';
                $config['morphId'] = $this->extractParameter($code, 'id') ?? $relation . '_id';
                $config['localKey'] = $this->extractParameter($code, 'localKey') ?? $model->getKeyName();
                break;
        }

        return $config;
    }

    /**
     * Alternative: Direct method parameter extraction
     * This is more reliable than regex parsing
     */
    protected function getRelationConfigDirect($model, string $relation, string $type): array
    {
        // Call the relation method and inspect the result
        try {
            // Temporarily disable query execution to inspect the relation setup
            $result = $model->$relation();

            $config = [];

            // For hasOne/hasMany/belongsTo, the result is usually a query builder or model
            if ($result instanceof Collection) {
                // hasMany returned a collection
                $config['related'] = get_class($result->first() ?? new \stdClass());
            } elseif ($result instanceof PlugModel) {
                // hasOne/belongsTo returned a model
                $config['related'] = get_class($result);
            } elseif ($result === null) {
                // Relation returned null, we need to parse the method
                return $this->getRelationConfig($model, $relation, $type);
            }

            // Set defaults based on type
            switch ($type) {
                case 'hasOne':
                case 'hasMany':
                    $config['foreignKey'] = strtolower(class_basename(get_class($model))) . '_id';
                    $config['localKey'] = $model->getKeyName();
                    break;

                case 'belongsTo':
                    $config['foreignKey'] = strtolower($relation) . '_id';
                    $config['ownerKey'] = 'id';
                    break;
            }

            return $config;

        } catch (\Exception $e) {
            // Fall back to code parsing
            return $this->getRelationConfig($model, $relation, $type);
        }
    }

    /**
     * BEST APPROACH: Use PHP Reflection to get method parameters
     * This is the most reliable method
     */
    protected function getRelationConfigReflection($model, string $relation, string $type): array
    {
        $reflection = new \ReflectionMethod($model, $relation);

        // Get the method source code
        $filename = $reflection->getFileName();
        $startLine = $reflection->getStartLine();
        $endLine = $reflection->getEndLine();

        if (!$filename) {
            throw new Exception("Could not read source file for relation: {$relation}");
        }

        $lines = file($filename);
        $methodCode = implode('', array_slice($lines, $startLine - 1, $endLine - $startLine + 1));

        // Enhanced regex patterns that handle different code styles
        $config = [];

        // Extract related class name with multiple patterns
        $patterns = [
            // hasMany(Post::class, ...)
            '/\$this->\w+\s*\(\s*([A-Za-z0-9_\\\\]+)::class/',

            // hasMany('App\Models\Post', ...)
            '/\$this->\w+\s*\(\s*[\'"]([A-Za-z0-9_\\\\]+)[\'"]/',

            // $related = Post::class; return $this->hasMany($related, ...)
            '/\$\w+\s*=\s*([A-Za-z0-9_\\\\]+)::class/',

            // new Post()
            '/new\s+([A-Za-z0-9_\\\\]+)\s*\(/',
        ];

        foreach ($patterns as $pattern) {
            if (preg_match($pattern, $methodCode, $matches)) {
                $config['related'] = $matches[1];
                break;
            }
        }

        if (empty($config['related'])) {
            throw new Exception("Could not extract related class from relation method: {$relation}\nCode: " . substr($methodCode, 0, 200));
        }

        // Extract other parameters
        switch ($type) {
            case 'hasOne':
            case 'hasMany':
                // Try to extract foreign key from method parameters
                if (preg_match('/\$this->\w+\s*\([^,]+,\s*[\'"]([^\'"]+)[\'"]/', $methodCode, $matches)) {
                    $config['foreignKey'] = $matches[1];
                } else {
                    $config['foreignKey'] = strtolower(class_basename(get_class($model))) . '_id';
                }

                $config['localKey'] = $model->getKeyName();
                break;

            case 'belongsTo':
                if (preg_match('/\$this->\w+\s*\([^,]+,\s*[\'"]([^\'"]+)[\'"]/', $methodCode, $matches)) {
                    $config['foreignKey'] = $matches[1];
                } else {
                    $config['foreignKey'] = strtolower($relation) . '_id';
                }

                $config['ownerKey'] = 'id';
                break;

            case 'belongsToMany':
                // This is more complex, use defaults for now
                $relatedClass = $config['related'];
                $config['pivotTable'] = $this->getPivotTableName($model, $relatedClass);
                $config['foreignPivotKey'] = strtolower(class_basename(get_class($model))) . '_id';
                $config['relatedPivotKey'] = strtolower(class_basename($relatedClass)) . '_id';
                $config['parentKey'] = $model->getKeyName();
                $config['relatedKey'] = 'id';
                break;
        }

        return $config;
    }

    /**
     * Extract parameter value from code - IMPROVED VERSION
     */
    protected function extractParameter(string $code, string $paramName): ?string
    {
        // Look for various parameter patterns
        $patterns = [
            // Pattern 1: $paramName = 'value'
            '/\$' . $paramName . '\s*=\s*[\'"]([^\'"]+)[\'"]/',

            // Pattern 2: $paramName ?? 'value'
            '/\$' . $paramName . '\s*\?\?\s*[\'"]([^\'"]+)[\'"]/',

            // Pattern 3: In method call - methodName($param1, 'value')
            '/,\s*[\'"]([^\'"]+)[\'"]\s*,/',

            // Pattern 4: Named parameter syntax
            '/' . $paramName . '\s*:\s*[\'"]([^\'"]+)[\'"]/',
        ];

        foreach ($patterns as $pattern) {
            if (preg_match($pattern, $code, $matches)) {
                return $matches[1];
            }
        }

        return null;
    }

    /**
     * Get pivot table name for belongsToMany
     */
    protected function getPivotTableName($model, string $relatedClass): string
    {
        $tables = [
            strtolower(class_basename(get_class($model))),
            strtolower(class_basename($relatedClass)),
        ];
        sort($tables);
        return implode('_', $tables);
    }

    /**
     * Set a loaded relation on the model
     */
    public function setRelation(string $relation, $value)
    {
        $this->relations[$relation] = $value;
        return $this;
    }

    /**
     * Get a loaded relation
     */
    public function getRelation(string $relation)
    {
        return $this->relations[$relation] ?? null;
    }

    /**
     * Check if relation is loaded
     */
    public function relationLoaded(string $relation): bool
    {
        return array_key_exists($relation, $this->relations);
    }

    /**
     * Unload a relation
     */
    public function unsetRelation(string $relation)
    {
        unset($this->relations[$relation]);
        return $this;
    }

    /**
     * Load relations on an existing collection
     */
    public static function loadRelations(Collection $models, $relations)
    {
        if (is_string($relations)) {
            $relations = func_get_args();
            array_shift($relations); // Remove $models
        }

        if ($models->isEmpty()) {
            return $models;
        }

        $instance = $models->first();

        foreach ($relations as $relation) {
            $instance->eagerLoadRelation($models, $relation);
        }

        return $models;
    }

    /**
     * Load missing relations
     */
    public function loadMissing($relations)
    {
        $relations = is_string($relations) ? func_get_args() : $relations;

        foreach ($relations as $relation) {
            if (!isset($this->relations[$relation])) {
                $this->load($relation);
            }
        }

        return $this;
    }

    public function load($relation)
    {
        if (!isset($this->relations[$relation])) {
            $this->relations[$relation] = $this->$relation();
        }
        return $this;
    }

    protected function fireModelEvent($event)
    {
        $method = $event;
        if (method_exists($this, $method)) {
            return $this->$method();
        }
        return true;
    }

    protected function retrieving()
    {
    }
    protected function retrieved()
    {
    }
    protected function creating()
    {
    }
    protected function created()
    {
    }
    protected function updating()
    {
    }
    protected function updated()
    {
    }
    protected function saving()
    {
    }
    protected function saved()
    {
    }
    protected function deleting()
    {
    }
    protected function deleted()
    {
    }
    protected function restoring()
    {
    }
    protected function restored()
    {
    }

    // ==================== MAGIC METHODS ====================

    public function __call($method, $parameters)
    {
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
        // For static calls, always create a new instance first
        $instance = new static();

        // Check if there's a static method defined
        if (method_exists(static::class, $method)) {
            return forward_static_call_array([static::class, $method], $parameters);
        }

        // Check for instance method and call it
        if (method_exists($instance, $method)) {
            return call_user_func_array([$instance, $method], $parameters);
        }

        // Check for scope method
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
