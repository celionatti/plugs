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
use Plugs\Database\Traits\HasFactory;

/**
 * PlugModel
 * 
 * @method mixed getAttribute(string $key)
 * @method void setAttribute(string $key, mixed $value)
 * @phpstan-consistent-constructor
 */
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
    use HasFactory;

    protected $table;
    protected $primaryKey = 'id';

    protected $exists = false;

    protected bool $allowRawQueries = false;

    // Static Query Logging and Caching
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

    public function __construct(array|object $attributes = [], bool $exists = false)
    {
        $this->bootIfNotBooted();
        $this->exists = $exists;

        // Validate configuration
        $this->validateModelConfiguration();

        if ($exists) {
            $this->setRawAttributes($attributes);
        } else {
            $this->fill($attributes);
        }

        if (!$this->table) {
            $this->table = static::getTable();
        }

        static::trackModelLoading();
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
        return $this->table ?? static::getTableName();
    }

    protected static function getTableName(): string
    {
        $basename = class_basename(static::class);

        // Handle anonymous classes (e.g. from factories falling back)
        if (str_contains($basename, '@anonymous') || str_contains($basename, ':')) {
            return 'anonymous_models';
        }

        return strtolower(
            preg_replace('/([a-z])([A-Z])/', '$1_$2', $basename)
        ) . 's';
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

    /**
     * Get a new query builder for the model's table.
     */
    public function newQuery()
    {
        return static::query();
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

            $stmt = $connection->prepare($sql);
            $stmt->execute($bindings);

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

    public function newFromBuilder(array|object $attributes = []): static
    {
        return new static($attributes, true);
    }

    public function __call($method, $parameters)
    {
        // 1. Check if the method exists on this instance
        if (method_exists($this, $method)) {
            return call_user_func_array([$this, $method], $parameters);
        }

        // 2. Handle model scopes (e.g. scopeActive)
        $scopeMethod = 'scope' . ucfirst($method);
        if (method_exists($this, $scopeMethod)) {
            array_unshift($parameters, $this->newQuery());
            return call_user_func_array([$this, $scopeMethod], $parameters);
        }

        // 3. Delegate to QueryBuilder
        return call_user_func_array([$this->newQuery(), $method], $parameters);
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

    public function toResponse(int $status = 200, ?string $message = null): \Plugs\Http\StandardResponse
    {
        return new \Plugs\Http\StandardResponse($this->toArray(), true, $status, $message);
    }

    /**
     * Convert the model to an API resource
     * 
     * @param string|null $resourceClass The resource class to use (auto-detected if null)
     * @return \Plugs\Http\Resources\PlugResource
     */
    public function resource(?string $resourceClass = null): \Plugs\Http\Resources\PlugResource
    {
        if ($resourceClass === null) {
            // Try to auto-detect resource class based on model name
            $modelName = class_basename(static::class);
            $resourceClass = "App\\Http\\Resources\\{$modelName}Resource";

            if (!class_exists($resourceClass)) {
                // Fall back to anonymous resource
                return new class ($this) extends \Plugs\Http\Resources\PlugResource {
                    public function toArray(): array
                    {
                        return $this->resource->toArray();
                    }
                };
            }
        }

        return new $resourceClass($this);
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
            'relations' => $this->relations ?? [],
            'exists' => $this->exists,
            'table' => $this->getTable(),
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
