<?php

declare(strict_types=1);

namespace Plugs\Base\Model;

use Exception;
use PDO;
use PDOException;
use Plugs\Database\Collection;
use Plugs\Database\Connection;
use Plugs\Database\Traits\HasAttributes;
use Plugs\Database\Traits\HasConnection;
use Plugs\Database\Traits\HasEvents;
use Plugs\Database\Traits\HasFactory;
use Plugs\Database\Traits\HasQueryBuilder;
use Plugs\Database\Traits\HasRelationships;
use Plugs\Database\Traits\HasTimestamps;

use Plugs\Database\Traits\Prunable;
use Plugs\Database\Traits\HasValidation;
use Plugs\Database\Traits\Searchable;
use Plugs\Database\Traits\SoftDeletes;
use Plugs\Database\Traits\HasDomainRules;
use Plugs\Database\Traits\HasScopes;
use Plugs\Database\Traits\HasTenancy;
use Plugs\Database\Traits\Authorizable;
use Plugs\Database\Traits\HasImmutability;
use Plugs\Database\Traits\HasVersioning;
use Plugs\Database\Traits\HasSerialization;
use Plugs\Database\Traits\HasObservability;
use Plugs\Database\Traits\HasDomainEvents;
use Plugs\Database\Traits\HasDiagnostics;
use Plugs\Database\Traits\HasSchema;
use Plugs\Database\Exceptions\ConcurrencyException;
use Plugs\AI\Traits\HasAI;

/**
 * PlugModel
 *
 * @method mixed getAttribute(string $key)
 * @method void setAttribute(string $key, mixed $value)
 */
abstract class PlugModel implements \JsonSerializable
{
    use \Plugs\Base\Model\Debuggable,
        Searchable,
        HasConnection,
        HasQueryBuilder,
        HasAttributes,
        HasRelationships,
        Prunable,
        HasValidation,
        HasTimestamps,
        SoftDeletes,
        HasFactory,
        HasDomainRules,
        HasTenancy,
        Authorizable,
        HasImmutability,
        HasVersioning,
        HasSerialization,
        HasObservability,
        HasDomainEvents,
        HasDiagnostics,
        HasSchema,
        HasAI;

    use HasEvents, HasScopes {
        HasScopes::addGlobalScope insteadof HasEvents;
        HasEvents::addGlobalScope as addGlobalEventScope;
        HasScopes::addGlobalScope as addGlobalQueryScope;
    }

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

    protected static bool $preventLazyLoading = false;

    public function __construct(array|object $attributes = [], bool $exists = false)
    {
        $this->bootIfNotBooted();
        $this->exists = $exists;

        // Validate configuration
        $this->validateModelConfiguration();

        if ($exists) {
            $this->setRawAttributes($attributes);
            $this->fireModelEvent('retrieved', ['attributes' => $attributes]);
        } else {
            $this->fill($attributes);
        }

        // Apply schema defaults and hidden fields
        if (method_exists($this, 'applySchemaDefaults')) {
            $this->applySchemaDefaults();
        }
        if (method_exists($this, 'mergeSchemaHidden')) {
            $this->mergeSchemaHidden();
        }

        if (!$this->table) {
            $this->table = static::getTable();
        }

        static::trackModelLoading();
    }

    protected static function boot(): void
    {
        static::bootTraits();
    }

    /**
     * Boot all of the bootable traits on the model.
     *
     * @return void
     */
    protected static function bootTraits(): void
    {
        $class = static::class;

        static::$bootTraits[$class] = static::$bootTraits[$class] ?? [];

        foreach (class_uses_recursive($class) as $trait) {
            $method = 'boot' . class_basename($trait);

            if (method_exists($class, $method) && !in_array($method, static::$bootTraits[$class])) {
                forward_static_call([$class, $method]);

                static::$bootTraits[$class][] = $method;
            }
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

    public static function preventLazyLoading(bool $prevent = true): void
    {
        static::$preventLazyLoading = $prevent;
    }

    public static function isLazyLoadingPrevented(): bool
    {
        return static::$preventLazyLoading;
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
        return $this->exists || isset($this->attributes[$this->primaryKey]);
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
        \Plugs\Facades\Cache::driver()->clear();
    }

    /**
     * Apply all registered global scopes to the query builder.
     */
    public function applyGlobalScopes(\Plugs\Database\QueryBuilder $builder): \Plugs\Database\QueryBuilder
    {
        foreach ($this->getGlobalScopes() as $scope) {
            if ($scope instanceof \Closure) {
                $scope($builder, $this);
            } elseif ($scope instanceof \Plugs\Database\Contracts\GlobalScope) {
                $scope->apply($builder, $this);
            }
        }

        return $builder;
    }

    protected function getCacheKey(string $sql, array $bindings): string
    {
        return md5($sql . serialize($bindings));
    }

    protected function getFromCache(string $key)
    {
        if (!static::$cacheEnabled) {
            return null;
        }

        return \Plugs\Facades\Cache::get($key);
    }

    protected function putInCache(string $key, $data): void
    {
        if (static::$cacheEnabled) {
            \Plugs\Facades\Cache::set($key, $data, static::$cacheTTL);
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
        if ($this->fireModelEvent('saving') === false) {
            return false;
        }

        if ($this->exists()) {
            $saved = $this->performUpdate();
        } else {
            $saved = $this->performInsert();
        }

        if ($saved) {
            $this->fireModelEvent('saved');
            $this->fireModelEvent('afterPersist');
        }

        return $saved;
    }

    protected function performInsert(): bool
    {
        $data = $this->attributes;

        if ($this->timestamps) {
            $data['created_at'] = date('Y-m-d H:i:s');
            $data['updated_at'] = date('Y-m-d H:i:s');
        }

        if ($this->fireModelEvent('creating') === false) {
            return false;
        }

        $result = static::query()->insert($data);

        if ($result) {
            $connection = Connection::getInstance();
            if (empty($this->attributes[$this->primaryKey])) {
                $this->attributes[$this->primaryKey] = $connection->lastInsertId();
            }
            $this->original = $this->attributes;

            $this->fireModelEvent('created');
        }

        return $result;
    }

    protected function performUpdate(): bool
    {
        $dirty = $this->getDirty();

        if (empty($dirty)) {
            return true;
        }

        // Domain Rule: Update Restriction
        if (!$this->canBeUpdated($dirty)) {
            throw new \DomainException("Update denied by domain rules.");
        }

        // Domain Rule: State Transitions
        $this->validateStateTransitions();

        if ($this->fireModelEvent('updating', ['dirty' => $dirty]) === false) {
            return false;
        }

        if ($this->timestamps) {
            $dirty['updated_at'] = date('Y-m-d H:i:s');
        }

        $query = static::query()
            ->where($this->primaryKey, '=', $this->attributes[$this->primaryKey]);

        // Apply Versioning Constraints if applicable
        if (method_exists($this, 'applyVersioningConstraints')) {
            $this->applyVersioningConstraints($query);
        }

        $result = $query->update($dirty);

        if ($result === 0 && property_exists($this, 'original_version') && $this->original_version !== null) {
            throw new ConcurrencyException("Concurrent update detected. The record was modified by another process.");
        }

        if ($result > 0) {
            $this->original = $this->attributes;

            $this->fireModelEvent('updated', ['dirty' => $dirty]);
        }

        return $result > 0;
    }

    public function delete(): bool
    {
        if (!$this->exists()) {
            return false;
        }

        // Domain Rule: Deletion Restriction
        if (!$this->canBeDeleted()) {
            throw new \DomainException("Deletion denied by domain rules.");
        }

        if ($this->fireModelEvent('deleting') === false) {
            return false;
        }

        $result = $this->performDelete();

        if ($result) {
            $this->fireModelEvent('deleted');
        }

        return $result;
    }

    /**
     * Perform the actual delete query.
     */
    protected function performDelete(): bool
    {
        return static::query()
            ->where($this->primaryKey, '=', $this->attributes[$this->primaryKey])
            ->delete() > 0;
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

    /**
     * Execute a Closure within a transaction.
     *
     * @param  \Closure  $callback
     * @param  int  $attempts
     * @return mixed
     *
     * @throws \Throwable
     */
    public static function transaction(\Closure $callback, int $attempts = 1)
    {
        return static::connection(static::$connectionName)->transaction($callback, $attempts);
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
        if (method_exists(static::class, $method)) {
            return forward_static_call_array([static::class, $method], $parameters);
        }

        return (new static())->$method(...$parameters);
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

    /**
     * Convert the model instance to JSON.
     *
     * @return mixed
     */
    public function jsonSerialize(): mixed
    {
        return $this->toArray();
    }
}

/**
 * Get all traits used by a class, including traits used by traits.
 *
 * @param  object|string  $class
 * @return array
 */
function class_uses_recursive($class): array
{
    if (is_object($class)) {
        $class = get_class($class);
    }

    $results = [];

    foreach (array_reverse(class_parents($class)) + [$class => $class] as $class) {
        $results += trait_uses_recursive($class);
    }

    return array_unique($results);
}

/**
 * Get all traits used by a trait, including traits used by traits.
 *
 * @param  string  $trait
 * @return array
 */
function trait_uses_recursive($trait): array
{
    $traits = class_uses($trait) ?: [];

    foreach ($traits as $trait) {
        $traits += trait_uses_recursive($trait);
    }

    return $traits;
}

if (!function_exists('class_basename')) {
    function class_basename($class): string
    {
        $class = is_object($class) ? get_class($class) : $class;

        return basename(str_replace('\\', '/', $class));
    }
}
