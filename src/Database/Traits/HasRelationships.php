<?php

declare(strict_types=1);

namespace Plugs\Database\Traits;

use Exception;
use ReflectionMethod;
use Plugs\Database\Collection;
use Plugs\Database\BelongsToManyProxy;

trait HasRelationships
{
    protected $relations = [];
    protected $eagerLoad = [];
    protected static $relationLoaders = [
        'hasOne' => 'eagerLoadHasOne',
        'hasMany' => 'eagerLoadHasMany',
        'belongsTo' => 'eagerLoadBelongsTo',
        'belongsToMany' => 'eagerLoadBelongsToMany',
        'morphTo' => 'eagerLoadMorphTo',
        'morphMany' => 'eagerLoadMorphMany',
    ];
    protected static $morphMap = [];
    protected static $relationTypes = [];

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

    public function setRelation(string $relation, $value)
    {
        $this->relations[$relation] = $value;
        return $this;
    }

    public function getRelation(string $relation)
    {
        return $this->relations[$relation] ?? null;
    }

    public function relationLoaded(string $relation): bool
    {
        return array_key_exists($relation, $this->relations);
    }

    public function unsetRelation(string $relation)
    {
        unset($this->relations[$relation]);
        return $this;
    }

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

    /**
     * Get a BelongsToMany relationship accessor
     */
    protected function getBelongsToManyRelation(string $relation)
    {
        // Get relation configuration
        $config = $this->getPivotRelationConfig($relation);

        if (!$config) {
            throw new Exception("Relation {$relation} is not a belongsToMany relationship");
        }

        // Return a relationship proxy that wraps the collection with pivot methods
        return new BelongsToManyProxy($this, $relation, $config);
    }

    protected function getRelationConfig($model, string $relation, string $type): array
    {
        $reflection = new ReflectionMethod($model, $relation);

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

    protected function getPivotTableName($model, string $relatedClass): string
    {
        $tables = [
            strtolower(class_basename(get_class($model))),
            strtolower(class_basename($relatedClass)),
        ];
        sort($tables);
        return implode('_', $tables);
    }

    private function getPivotRelationConfig(string $relation): ?array
    {
        if (!method_exists($this, $relation)) {
            return null;
        }

        $reflection = new ReflectionMethod($this, $relation);
        $code = $this->getMethodCode($reflection);

        if (strpos($code, 'belongsToMany') === false) {
            return null;
        }

        $relatedClass = null;
        $patterns = [
            '/belongsToMany\s*\(\s*([A-Za-z0-9_\\\\]+)::class/',
            '/belongsToMany\s*\(\s*[\'"]([A-Za-z0-9_\\\\]+)[\'"]/',
        ];

        foreach ($patterns as $pattern) {
            if (preg_match($pattern, $code, $matches)) {
                $relatedClass = $matches[1];
                break;
            }
        }

        if (!$relatedClass) {
            return null;
        }

        $foreignPivotKey = strtolower(class_basename(static::class)) . '_id';
        $relatedPivotKey = strtolower(class_basename($relatedClass)) . '_id';
        $parentKey = $this->primaryKey;

        $tables = [
            strtolower(class_basename(static::class)),
            strtolower(class_basename($relatedClass)),
        ];
        sort($tables);
        $pivotTable = implode('_', $tables);

        return [
            'pivotTable' => $pivotTable,
            'foreignPivotKey' => $foreignPivotKey,
            'relatedPivotKey' => $relatedPivotKey,
            'parentKey' => $parentKey,
            'relatedClass' => $relatedClass,
        ];
    }

    /**
     * Get method code helper (needed by getPivotRelationConfig and getRelationConfig)
     * This might be better as a utility but it's used for relationship reflection
     */
    protected function getMethodCode(ReflectionMethod $reflection): string
    {
        $filename = $reflection->getFileName();
        $startLine = $reflection->getStartLine();
        $endLine = $reflection->getEndLine();

        if (!$filename) {
            return '';
        }

        $lines = file($filename);
        return implode('', array_slice($lines, $startLine - 1, $endLine - $startLine + 1));
    }

    /**
     * Eager load a relationship on a collection of models.
     */
    public function eagerLoadRelation(Collection $models, string $relation): void
    {
        // Check if the relation contains nested relations (e.g. 'posts.comments')
        $nested = [];
        if (str_contains($relation, '.')) {
            [$relation, $nested] = explode('.', $relation, 2);
            $nested = [$nested];
        }

        if (!method_exists($this, $relation)) {
            throw new Exception("Relationship method {$relation} not found on " . static::class);
        }

        // Determine the relationship type and configuration
        $config = $this->getRelationConfigForEagerLoading($relation);
        $type = $config['type'];

        if (!isset(static::$relationLoaders[$type])) {
            throw new Exception("Eager loading not supported for relationship type: {$type}");
        }

        $loader = static::$relationLoaders[$type];
        $this->$loader($models, $relation, $config, $nested);
    }

    protected function getRelationConfigForEagerLoading(string $relation): array
    {
        $reflection = new ReflectionMethod($this, $relation);
        $code = $this->getMethodCode($reflection);

        $type = null;
        foreach (array_keys(static::$relationLoaders) as $relType) {
            if (str_contains($code, $relType)) {
                $type = $relType;
                break;
            }
        }

        if (!$type) {
            throw new Exception("Could not determine relationship type for: {$relation}");
        }

        $config = $this->getRelationConfig($this, $relation, $type);
        $config['type'] = $type;

        return $config;
    }

    protected function eagerLoadHasOne(Collection $models, string $relation, array $config, array $nested): void
    {
        $related = $config['related'];
        $foreignKey = $config['foreignKey'];
        $localKey = $config['localKey'];

        $ids = $models->pluck($localKey)->unique()->all();

        $query = $related::query()->whereIn($foreignKey, $ids);
        if (!empty($nested)) {
            $query->with($nested);
        }

        $results = $query->get()->keyBy($foreignKey);

        foreach ($models as $model) {
            $id = $model->getAttribute($localKey);
            $model->setRelation($relation, $results[$id] ?? null);
        }
    }

    protected function eagerLoadHasMany(Collection $models, string $relation, array $config, array $nested): void
    {
        $related = $config['related'];
        $foreignKey = $config['foreignKey'];
        $localKey = $config['localKey'];

        $ids = $models->pluck($localKey)->unique()->all();

        $query = $related::query()->whereIn($foreignKey, $ids);
        if (!empty($nested)) {
            $query->with($nested);
        }

        $results = $query->get()->groupBy($foreignKey);

        foreach ($models as $model) {
            $id = $model->getAttribute($localKey);
            $model->setRelation($relation, $results[$id] ?? new Collection());
        }
    }

    protected function eagerLoadBelongsTo(Collection $models, string $relation, array $config, array $nested): void
    {
        $related = $config['related'];
        $foreignKey = $config['foreignKey'];
        $ownerKey = $config['ownerKey'];

        $ids = $models->pluck($foreignKey)->unique()->filter()->all();

        if (empty($ids)) {
            foreach ($models as $model) {
                $model->setRelation($relation, null);
            }
            return;
        }

        $query = $related::query()->whereIn($ownerKey, $ids);
        if (!empty($nested)) {
            $query->with($nested);
        }

        $results = $query->get()->keyBy($ownerKey);

        foreach ($models as $model) {
            $id = $model->getAttribute($foreignKey);
            $model->setRelation($relation, $results[$id] ?? null);
        }
    }

    protected function eagerLoadBelongsToMany(Collection $models, string $relation, array $config, array $nested): void
    {
        $related = $config['related'];
        $pivotTable = $config['pivotTable'];
        $foreignPivotKey = $config['foreignPivotKey'];
        $relatedPivotKey = $config['relatedPivotKey'];
        $parentKey = $config['parentKey'];

        $ids = $models->pluck($parentKey)->unique()->all();

        // This is complex. We need to join with the pivot table to get parent context
        $query = $related::query()
            ->select(["{$related::getTableName()}.*", "{$pivotTable}.{$foreignPivotKey} as pivot_{$foreignPivotKey}"])
            ->join($pivotTable, "{$pivotTable}.{$relatedPivotKey}", '=', "{$related::getTableName()}.id")
            ->whereIn("{$pivotTable}.{$foreignPivotKey}", $ids);

        if (!empty($nested)) {
            $query->with($nested);
        }

        $results = $query->get();
        $grouped = $results->groupBy("pivot_{$foreignPivotKey}");

        foreach ($models as $model) {
            $id = $model->getAttribute($parentKey);
            $model->setRelation($relation, $grouped[$id] ?? new Collection());
        }
    }

    protected function eagerLoadMorphTo(Collection $models, string $relation, array $config, array $nested): void
    {
        $groups = $models->groupBy($relation . '_type');

        foreach ($groups as $type => $groupModels) {
            $class = static::getMorphedModel($type);
            if (!class_exists($class))
                continue;

            $ids = $groupModels->pluck($relation . '_id')->unique()->all();
            $results = $class::query()->whereIn('id', $ids)->get()->keyBy('id');

            foreach ($groupModels as $model) {
                $id = $model->getAttribute($relation . '_id');
                $model->setRelation($relation, $results[$id] ?? null);
            }
        }
    }

    protected function eagerLoadMorphMany(Collection $models, string $relation, array $config, array $nested): void
    {
        $related = $config['related'];
        $type = $config['type'] ?? $relation . '_type';
        $id = $config['id'] ?? $relation . '_id';
        $localKey = $this->primaryKey;

        $ids = $models->pluck($localKey)->unique()->all();

        $query = $related::query()
            ->where($type, '=', static::class)
            ->whereIn($id, $ids);

        if (!empty($nested)) {
            $query->with($nested);
        }

        $results = $query->get()->groupBy($id);

        foreach ($models as $model) {
            $modelId = $model->getAttribute($localKey);
            $model->setRelation($relation, $results[$modelId] ?? new Collection());
        }
    }
}
