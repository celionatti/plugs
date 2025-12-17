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
}
