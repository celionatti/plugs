<?php

declare(strict_types=1);

namespace Plugs\Database;

use Closure;
use Exception;
use BadMethodCallException;
use Plugs\Database\Support\QueryUtils;
use Plugs\Http\StandardResponse;
use Plugs\Paginator\Pagination;

/*
|--------------------------------------------------------------------------
| QueryBuilder Class
|--------------------------------------------------------------------------
|
| This class provides a fluent interface for building and executing SQL
| queries. It supports common operations like select, insert, update,
| and delete, along with where clauses, ordering, and pagination.
*/

/**
 * @phpstan-consistent-constructor
 */
class QueryBuilder
{
    private $connection;
    private $table;
    private $select = ['*'];
    private $where = [];
    private $params = [];
    private $rawSql = null;
    private $orderBy = [];
    private $groupBy = [];
    private $having = [];
    private $distinct = false;
    private $limit = null;
    private $offset = null;
    protected $model = null;
    protected $with = [];
    protected $joins = [];
    protected $middleware = [];
    protected bool $useCache = false;
    protected int $cacheTTL = 3600;

    public function __construct(Connection $connection)
    {
        $this->connection = $connection;
    }

    public function rawSql(string $sql, array $params = []): self
    {
        $this->rawSql = $sql;
        $this->params = $params;

        return $this;
    }

    /**
     * Enable query caching for this request.
     *
     * @param int|null $ttl Time to live in seconds
     * @return $this
     */
    public function remember(?int $ttl = null): self
    {
        $this->useCache = true;
        if ($ttl !== null) {
            $this->cacheTTL = $ttl;
        }

        return $this;
    }

    /**
     * Set the relationships that should be eager loaded.
     *
     * @param  array|string  $relations
     * @return $this
     */
    public function with($relations): self
    {
        $this->with = is_string($relations) ? func_get_args() : $relations;

        return $this;
    }

    public function table(string $table): self
    {
        $this->table = $table;

        return $this;
    }

    public function getTable(): ?string
    {
        return $this->table;
    }

    public function select(array $columns = ['*']): self
    {
        $this->select = array_map([QueryUtils::class, 'sanitizeColumn'], $columns);

        return $this;
    }

    public function selectRaw(string $expression, array $bindings = []): self
    {
        $this->select[] = new \Plugs\Database\Raw($expression);
        $this->params = array_merge($this->params, $bindings);

        return $this;
    }

    public function distinct(): self
    {
        $this->distinct = true;

        return $this;
    }

    public function setModel(string $model): self
    {
        $this->model = $model;

        return $this;
    }

    public function getModel(): ?string
    {
        return $this->model;
    }

    public function where($column, $operator = null, $value = null, $boolean = 'AND'): self
    {
        if (is_array($column)) {
            foreach ($column as $key => $val) {
                $this->where($key, '=', $val, $boolean);
            }
            return $this;
        }

        // Handle closure for nested query
        if ($column instanceof Closure) {
            $query = new static($this->connection);
            $query->table($this->table);
            if ($this->model) {
                $query->setModel($this->model);
            }
            $column($query);

            $nestedWhere = $query->getWhereClause();
            if (!empty($nestedWhere)) {
                $nestedSql = substr($nestedWhere, 7); // Remove ' WHERE '
                $this->where[] = [
                    'type' => 'Raw',
                    'query' => "({$nestedSql})",
                    'boolean' => $boolean,
                    'params' => $query->getParams(),
                ];
                $this->params = array_merge($this->params, $query->getParams());
            }

            return $this;
        }

        // Handle subquery as value
        if ($value instanceof Closure || $value instanceof self) {
            if ($value instanceof Closure) {
                // IMPORTANT: Pass connection to subquery
                $subQuery = new static($this->connection);
                $value($subQuery);
            } else {
                $subQuery = $value;
            }

            $sql = $subQuery->buildSelectSql();

            // But we need to avoid collisions.
            foreach ($subQuery->getParams() as $key => $val) {
                if (!is_string($key))
                    continue;
                $cleanKey = ltrim($key, ':');
                $newKey = ':' . $cleanKey . '_sub';
                $this->params[$newKey] = $val;

                // We must search and replace in the SQL string
                $sql = str_replace($key, $newKey, $sql);
            }

            $wrappedColumn = QueryUtils::wrapIdentifier((string) $column);
            $this->where[] = [
                'type' => 'Subquery',
                'query' => "{$wrappedColumn} {$operator} ({$sql})",
                'boolean' => $boolean,
            ];

            return $this;
        }

        // Handle standard where: where('col', 'val') or where('col', null)
        $operators = ['=', '<', '>', '<=', '>=', '<>', '!=', 'LIKE', 'IN', 'IS', 'BETWEEN', 'NOT IN', 'IS NOT'];
        if ($value === null && ($operator === null || !in_array(strtoupper((string) $operator), $operators))) {
            $value = $operator;
            $operator = '=';
        }

        if ($value instanceof Raw) {
            $this->where[] = [
                'type' => 'Raw',
                'query' => "{$column} {$operator} {$value}",
                'boolean' => $boolean,
                'params' => [],
            ];
        } else {
            $placeholder = ':where_' . count($this->params) . '_' . str_replace('.', '_', (string) $column);
            $wrappedColumn = QueryUtils::wrapIdentifier((string) $column);

            $this->where[] = [
                'type' => 'Basic',
                'query' => "{$wrappedColumn} {$operator} {$placeholder}",
                'boolean' => $boolean,
            ];

            $this->params[$placeholder] = $value;
        }

        return $this;
    }

    public function orWhere($column, $operator = null, $value = null): self
    {
        return $this->where($column, $operator, $value, 'OR');
    }

    /**
     * Add a nested where clause (alias for where with a closure).
     */
    public function nestedWhere(Closure $callback, string $boolean = 'AND'): self
    {
        return $this->where($callback, null, null, $boolean);
    }

    /**
     * Add a nested or where clause (alias for orWhere with a closure).
     */
    public function orNestedWhere(Closure $callback): self
    {
        return $this->nestedWhere($callback, 'OR');
    }

    /**
     * Add a "where has" relationship constraint.
     */
    public function whereHas(string $relation, ?Closure $callback = null, string $boolean = 'AND'): self
    {
        if (!$this->model) {
            throw new Exception("whereHas requires a model to be set on the builder.");
        }

        $modelInstance = new $this->model();

        // Use reflection to get relation details via a temporary proxy
        if (!method_exists($modelInstance, $relation)) {
            throw new Exception("Relationship [{$relation}] not found on model [" . get_class($modelInstance) . "].");
        }

        $proxy = $modelInstance->$relation();

        // We need to extract the related table and keys from the proxy
        // This is a bit hacky but works given the current architecture
        $reflection = new \ReflectionObject($proxy);

        $relatedBuilder = null;
        $foreignKey = null;
        $localKey = null;
        $pivotTable = null;
        $foreignPivotKey = null;
        $relatedPivotKey = null;

        if ($reflection->hasProperty('builder')) {
            $prop = $reflection->getProperty('builder');
            $prop->setAccessible(true);
            $relatedBuilder = $prop->getValue($proxy);
        }

        if ($reflection->hasProperty('foreignKey')) {
            $prop = $reflection->getProperty('foreignKey');
            $prop->setAccessible(true);
            $foreignKey = $prop->getValue($proxy);
        }

        if ($reflection->hasProperty('localKey')) {
            $prop = $reflection->getProperty('localKey');
            $prop->setAccessible(true);
            $localKey = $prop->getValue($proxy);
        }

        if ($reflection->hasProperty('ownerKey')) {
            $prop = $reflection->getProperty('ownerKey');
            $prop->setAccessible(true);
            $localKey = $prop->getValue($proxy); // ownerKey is like localKey for BelongsTo
        }

        // Handle BelongsToMany which uses BelongsToManyProxy
        if (str_contains(get_class($proxy), 'BelongsToManyProxy')) {
            $prop = $reflection->getProperty('config');
            $prop->setAccessible(true);
            $config = $prop->getValue($proxy);

            $pivotTable = $config['pivotTable'];
            $foreignPivotKey = $config['foreignPivotKey'];
            $relatedPivotKey = $config['relatedPivotKey'];
            $relatedClass = $config['relatedClass'];
            $relatedBuilder = $relatedClass::query();
        }

        if (!$relatedBuilder) {
            throw new Exception("Could not resolve relationship builder for [{$relation}].");
        }

        $relatedTable = $relatedBuilder->getTable();
        $parentTable = $this->table;

        // Build the subquery
        $subQuery = new static($this->connection);
        $subQuery->table($relatedTable);
        $subQuery->select(['1']);

        if ($pivotTable) {
            // WHERE EXISTS (SELECT 1 FROM related JOIN pivot ON pivot.related_id = related.id WHERE pivot.parent_id = parent.id AND ...)
            $subQuery->join($pivotTable, "{$pivotTable}.{$relatedPivotKey}", '=', "{$relatedTable}.id");
            $subQuery->where("{$pivotTable}.{$foreignPivotKey}", '=', new \Plugs\Database\Raw("{$parentTable}.id"));
        } else {
            // Basic relationship
            // WHERE EXISTS (SELECT 1 FROM related WHERE related.foreign_key = parent.local_key AND ...)
            $subQuery->where("{$relatedTable}.{$foreignKey}", '=', new \Plugs\Database\Raw("{$parentTable}.{$localKey}"));
        }

        if ($callback) {
            $callback($subQuery);
        }

        $sql = $subQuery->buildSelectSql();
        // Remove "SELECT 1 FROM table" and keep the rest? No, actually we can just use the whole query.

        $this->where[] = [
            'type' => 'Raw',
            'sql' => "EXISTS ({$sql})",
            'boolean' => $boolean,
            'params' => $subQuery->getParams(),
        ];

        $this->params = array_merge($this->params, $subQuery->getParams());

        return $this;
    }

    public function orWhereHas(string $relation, ?Closure $callback = null): self
    {
        return $this->whereHas($relation, $callback, 'OR');
    }

    /**
     * Add a "where doesn't have" relationship constraint.
     */
    public function whereDoesntHave(string $relation, ?Closure $callback = null, string $boolean = 'AND'): self
    {
        if (!$this->model) {
            throw new Exception("whereDoesntHave requires a model to be set on the builder.");
        }

        $modelInstance = new $this->model();

        if (!method_exists($modelInstance, $relation)) {
            throw new Exception("Relationship [{$relation}] not found on model [" . get_class($modelInstance) . "].");
        }

        $proxy = $modelInstance->$relation();
        $reflection = new \ReflectionObject($proxy);

        $relatedBuilder = null;
        $foreignKey = null;
        $localKey = null;
        $pivotTable = null;
        $foreignPivotKey = null;
        $relatedPivotKey = null;

        if ($reflection->hasProperty('builder')) {
            $prop = $reflection->getProperty('builder');
            $prop->setAccessible(true);
            $relatedBuilder = $prop->getValue($proxy);
        }

        if ($reflection->hasProperty('foreignKey')) {
            $prop = $reflection->getProperty('foreignKey');
            $prop->setAccessible(true);
            $foreignKey = $prop->getValue($proxy);
        }

        if ($reflection->hasProperty('localKey')) {
            $prop = $reflection->getProperty('localKey');
            $prop->setAccessible(true);
            $localKey = $prop->getValue($proxy);
        }

        if ($reflection->hasProperty('ownerKey')) {
            $prop = $reflection->getProperty('ownerKey');
            $prop->setAccessible(true);
            $localKey = $prop->getValue($proxy);
        }

        if (str_contains(get_class($proxy), 'BelongsToManyProxy')) {
            $prop = $reflection->getProperty('config');
            $prop->setAccessible(true);
            $config = $prop->getValue($proxy);

            $pivotTable = $config['pivotTable'];
            $foreignPivotKey = $config['foreignPivotKey'];
            $relatedPivotKey = $config['relatedPivotKey'];
            $relatedClass = $config['relatedClass'];
            $relatedBuilder = $relatedClass::query();
        }

        if (!$relatedBuilder) {
            throw new Exception("Could not resolve relationship builder for [{$relation}].");
        }

        $relatedTable = $relatedBuilder->getTable();
        $parentTable = $this->table;

        $subQuery = new static($this->connection);
        $subQuery->table($relatedTable);
        $subQuery->select(['1']);

        if ($pivotTable) {
            $subQuery->join($pivotTable, "{$pivotTable}.{$relatedPivotKey}", '=', "{$relatedTable}.id");
            $subQuery->where("{$pivotTable}.{$foreignPivotKey}", '=', new \Plugs\Database\Raw("{$parentTable}.id"));
        } else {
            $subQuery->where("{$relatedTable}.{$foreignKey}", '=', new \Plugs\Database\Raw("{$parentTable}.{$localKey}"));
        }

        if ($callback) {
            $callback($subQuery);
        }

        $sql = $subQuery->buildSelectSql();

        $this->where[] = [
            'type' => 'Raw',
            'sql' => "NOT EXISTS ({$sql})",
            'boolean' => $boolean,
            'params' => $subQuery->getParams(),
        ];

        $this->params = array_merge($this->params, $subQuery->getParams());

        return $this;
    }

    /**
     * Add an "or where doesn't have" relationship constraint.
     */
    public function orWhereDoesntHave(string $relation, ?Closure $callback = null): self
    {
        return $this->whereDoesntHave($relation, $callback, 'OR');
    }

    public function join($table, $first, $operator = null, $second = null, string $type = 'INNER'): self
    {
        if ($table instanceof Closure || $table instanceof self) {
            if ($table instanceof Closure) {
                $subQuery = new static($this->connection);
                $table($subQuery);
            } else {
                $subQuery = $table;
            }

            $sql = $subQuery->buildSelectSql();
            $alias = $first;

            // Subquery params
            foreach ($subQuery->getParams() as $key => $val) {
                if (!is_string($key))
                    continue;
                $cleanKey = ltrim($key, ':');
                $newKey = ':' . $cleanKey . '_sub';
                $this->params[$newKey] = $val;

                $sql = str_replace($key, $newKey, $sql);
            }

            $tableSql = "({$sql}) AS " . QueryUtils::wrapIdentifier($alias);

            $joinFirst = $operator;
            $joinOperator = func_num_args() >= 5 ? $second : '=';
            $joinSecond = func_num_args() >= 5 ? $type : $second;
            $joinType = func_num_args() >= 6 ? func_get_arg(5) : 'INNER';

            $this->joins[] = [
                'table' => $tableSql,
                'first' => QueryUtils::wrapIdentifier($joinFirst),
                'operator' => $joinOperator,
                'second' => QueryUtils::wrapIdentifier($joinSecond),
                'type' => strtoupper($joinType),
                'isRaw' => true,
            ];

            return $this;
        }

        // Sanitize identifiers
        QueryUtils::sanitizeColumn($table);
        QueryUtils::sanitizeColumn($first);
        QueryUtils::sanitizeColumn($second);

        $this->joins[] = compact('table', 'first', 'operator', 'second', 'type');

        return $this;
    }

    public function leftJoin(string $table, string $first, string $operator, string $second): self
    {
        return $this->join($table, $first, $operator, $second, 'LEFT');
    }

    public function rightJoin(string $table, string $first, string $operator, string $second): self
    {
        return $this->join($table, $first, $operator, $second, 'RIGHT');
    }

    public function whereNull(string $column, string $boolean = 'AND'): self
    {
        $wrappedColumn = QueryUtils::wrapIdentifier($column);
        $this->where[] = [
            'type' => 'Null',
            'query' => "{$wrappedColumn} IS NULL",
            'boolean' => $boolean,
        ];

        return $this;
    }

    public function whereNotNull(string $column, string $boolean = 'AND'): self
    {
        $wrappedColumn = QueryUtils::wrapIdentifier($column);
        $this->where[] = [
            'type' => 'NotNull',
            'query' => "{$wrappedColumn} IS NOT NULL",
            'boolean' => $boolean,
        ];

        return $this;
    }

    // Helper to get raw where params
    public function getParams(): array
    {
        return $this->params;
    }

    // Helper to get constructed where clause string
    public function getWhereClause(): string
    {
        if (empty($this->where)) {
            return '';
        }

        $sql = '';
        foreach ($this->where as $index => $condition) {
            $boolean = $index === 0 ? 'WHERE' : $condition['boolean'];
            $queryPart = $condition['query'] ?? ($condition['sql'] ?? '');
            $sql .= " {$boolean} " . $queryPart;
        }

        return $sql;
    }

    public function whereIn(string $column, array $values): self
    {
        QueryUtils::sanitizeColumn($column);

        $placeholders = [];
        foreach ($values as $i => $value) {
            $placeholder = ":wherein_" . str_replace('.', '_', $column) . "_{$i}";
            $placeholders[] = $placeholder;
            $this->params[$placeholder] = $value;
        }

        $this->where[] = [
            'type' => 'In',
            'query' => QueryUtils::wrapIdentifier($column) . " IN (" . implode(', ', $placeholders) . ")",
            'boolean' => 'AND',
        ];

        return $this;
    }

    public function whereNotIn(string $column, array $values): self
    {
        QueryUtils::sanitizeColumn($column);

        $placeholders = [];
        foreach ($values as $i => $value) {
            $placeholder = ":wherenotin_" . str_replace('.', '_', $column) . "_{$i}";
            $placeholders[] = $placeholder;
            $this->params[$placeholder] = $value;
        }

        $this->where[] = [
            'type' => 'NotIn',
            'query' => QueryUtils::wrapIdentifier($column) . " NOT IN (" . implode(', ', $placeholders) . ")",
            'boolean' => 'AND',
        ];

        return $this;
    }

    public function whereBetween(string $column, array $values): self
    {
        QueryUtils::sanitizeColumn($column);

        $placeholder1 = ":wherebetween_" . str_replace('.', '_', $column) . "_1";
        $placeholder2 = ":wherebetween_" . str_replace('.', '_', $column) . "_2";

        $this->where[] = [
            'type' => 'Between',
            'query' => QueryUtils::wrapIdentifier($column) . " BETWEEN {$placeholder1} AND {$placeholder2}",
            'boolean' => 'AND',
        ];

        $this->params[$placeholder1] = $values[0];
        $this->params[$placeholder2] = $values[1];

        return $this;
    }

    public function whereNotBetween(string $column, array $values): self
    {
        QueryUtils::sanitizeColumn($column);

        $placeholder1 = ":wherenotbetween_" . str_replace('.', '_', $column) . "_1";
        $placeholder2 = ":wherenotbetween_" . str_replace('.', '_', $column) . "_2";

        $this->where[] = [
            'type' => 'NotBetween',
            'query' => QueryUtils::wrapIdentifier($column) . " NOT BETWEEN {$placeholder1} AND {$placeholder2}",
            'boolean' => 'AND',
        ];

        $this->params[$placeholder1] = $values[0];
        $this->params[$placeholder2] = $values[1];

        return $this;
    }

    public function whereDate(string $column, $operator, $value = null): self
    {
        if (func_num_args() === 2) {
            $value = $operator;
            $operator = '=';
        }

        QueryUtils::sanitizeColumn($column);
        $placeholder = ":wheredate_" . str_replace('.', '_', $column) . "_" . count($this->params);

        $this->where[] = [
            'type' => 'Date',
            'query' => "DATE(" . QueryUtils::wrapIdentifier($column) . ") {$operator} {$placeholder}",
            'boolean' => 'AND',
        ];

        $this->params[$placeholder] = $value;

        return $this;
    }

    public function whereMonth(string $column, $operator, $value = null): self
    {
        if (func_num_args() === 2) {
            $value = $operator;
            $operator = '=';
        }

        QueryUtils::sanitizeColumn($column);
        $placeholder = ":wheremonth_" . str_replace('.', '_', $column) . "_" . count($this->params);

        $this->where[] = [
            'type' => 'Month',
            'query' => "MONTH(" . QueryUtils::wrapIdentifier($column) . ") {$operator} {$placeholder}",
            'boolean' => 'AND',
        ];

        $this->params[$placeholder] = $value;

        return $this;
    }

    public function whereDay(string $column, $operator, $value = null): self
    {
        if (func_num_args() === 2) {
            $value = $operator;
            $operator = '=';
        }

        QueryUtils::sanitizeColumn($column);
        $placeholder = ":whereday_" . str_replace('.', '_', $column) . "_" . count($this->params);

        $this->where[] = [
            'type' => 'Day',
            'query' => "DAY(" . QueryUtils::wrapIdentifier($column) . ") {$operator} {$placeholder}",
            'boolean' => 'AND',
        ];

        $this->params[$placeholder] = $value;

        return $this;
    }

    public function whereYear(string $column, $operator, $value = null): self
    {
        if (func_num_args() === 2) {
            $value = $operator;
            $operator = '=';
        }

        QueryUtils::sanitizeColumn($column);
        $placeholder = ":whereyear_" . str_replace('.', '_', $column) . "_" . count($this->params);

        $this->where[] = [
            'type' => 'Year',
            'query' => "YEAR(" . QueryUtils::wrapIdentifier($column) . ") {$operator} {$placeholder}",
            'boolean' => 'AND',
        ];

        $this->params[$placeholder] = $value;

        return $this;
    }

    public function whereTime(string $column, $operator, $value = null): self
    {
        if (func_num_args() === 2) {
            $value = $operator;
            $operator = '=';
        }

        QueryUtils::sanitizeColumn($column);
        $placeholder = ":wheretime_" . str_replace('.', '_', $column) . "_" . count($this->params);

        $this->where[] = [
            'type' => 'Time',
            'query' => "TIME(" . QueryUtils::wrapIdentifier($column) . ") {$operator} {$placeholder}",
            'boolean' => 'AND',
        ];

        $this->params[$placeholder] = $value;

        return $this;
    }

    public function whereJsonContains(string $column, $value): self
    {
        QueryUtils::sanitizeColumn($column);
        $placeholder = ":wherejson_" . str_replace('.', '_', $column) . "_" . count($this->params);

        $this->where[] = [
            'type' => 'JsonContains',
            'query' => "JSON_CONTAINS(" . QueryUtils::wrapIdentifier($column) . ", {$placeholder})",
            'boolean' => 'AND',
        ];

        $this->params[$placeholder] = json_encode($value);

        return $this;
    }

    /**
     * Apply a callback to the query builder if a condition is true.
     */
    public function when($value, callable $callback, callable $default = null): self
    {
        if ($value) {
            $callback($this, $value);
        } elseif ($default) {
            $default($this, $value);
        }

        return $this;
    }

    /**
     * Apply a callback to the query builder if a condition is false.
     */
    public function unless($value, callable $callback, callable $default = null): self
    {
        if (!$value) {
            $callback($this, $value);
        } elseif ($default) {
            $default($this, $value);
        }

        return $this;
    }

    public function orderBy(string $column, string $direction = 'ASC'): self
    {
        QueryUtils::sanitizeColumn($column);
        $this->orderBy[] = QueryUtils::wrapIdentifier($column) . " " . (strtoupper($direction) === 'DESC' ? 'DESC' : 'ASC');

        return $this;
    }

    public function groupBy(...$columns): self
    {
        $columns = is_array($columns[0]) ? $columns[0] : $columns;

        foreach ($columns as $column) {
            QueryUtils::sanitizeColumn($column);
            $this->groupBy[] = QueryUtils::wrapIdentifier($column);
        }

        return $this;
    }

    public function having(string $column, string $operator, $value, string $boolean = 'AND'): self
    {
        QueryUtils::sanitizeColumn($column);
        $placeholder = ":having_" . count($this->params) . "_" . str_replace('.', '_', $column);

        $this->having[] = [
            'column' => QueryUtils::wrapIdentifier($column),
            'operator' => $operator,
            'value' => $placeholder,
            'boolean' => $boolean,
        ];

        $this->params[$placeholder] = $value;

        return $this;
    }

    public function orHaving(string $column, string $operator, $value): self
    {
        return $this->having($column, $operator, $value, 'OR');
    }

    public function latest(string $column = 'created_at'): self
    {
        return $this->orderBy($column, 'DESC');
    }

    public function oldest(string $column = 'created_at'): self
    {
        return $this->orderBy($column, 'ASC');
    }

    public function limit(int $limit): self
    {
        $this->limit = $limit;

        return $this;
    }

    public function offset(int $offset): self
    {
        $this->offset = $offset;

        return $this;
    }

    public function get(array $columns = ['*']): array|Collection
    {
        return $this->runThroughPipeline(function ($builder) use ($columns) {
            if ($builder->rawSql) {
                $sql = $builder->rawSql;
            } else {
                if ($columns !== ['*']) {
                    $builder->select($columns);
                }
                $sql = $builder->buildSelectSql();
            }

            // Caching check
            $cacheKey = null;
            if ($builder->useCache && $builder->model) {
                $cacheKey = $builder->model::getCacheKey($sql, $builder->params);
                $cached = $builder->model::getFromCache($cacheKey);
                if ($cached !== null) {
                    return $cached;
                }
            }

            $results = $builder->connection->fetchAll($sql, $builder->params);

            // Get the last query ID from monitor
            $monitor = RelationMonitor::getInstance();
            $queries = $monitor->getQueries();
            $lastQuery = end($queries);
            $queryId = $lastQuery ? $lastQuery['id'] : null;

            if ($builder->model && !empty($results)) {
                $models = array_map(function ($item) use ($builder, $queryId) {
                    $model = new $builder->model($item, true);
                    if ($queryId) {
                        $model->setParentQueryId($queryId);
                    }
                    return $model;
                }, $results);

                $collection = new Collection($models);
                $collection->setModelsCollectionContext();

                if (!empty($builder->with)) {
                    $builder->model::loadRelations($collection, $builder->with);
                }

                if ($builder->useCache && $cacheKey) {
                    $builder->model::putInCache($cacheKey, $collection);
                }

                return $collection;
            }

            if ($builder->useCache && $cacheKey && !empty($results)) {
                $builder->model::putInCache($cacheKey, $results);
            }

            return $results;
        });
    }

    public function first(array $columns = ['*']): mixed
    {
        return $this->runThroughPipeline(function ($builder) use ($columns) {
            $builder->limit(1);

            if ($columns !== ['*']) {
                $builder->select($columns);
            }

            $sql = $builder->buildSelectSql();

            // Caching check
            $cacheKey = null;
            if ($builder->useCache && $builder->model) {
                $cacheKey = $builder->model::getCacheKey($sql, $builder->params);
                $cached = $builder->model::getFromCache($cacheKey);
                if ($cached !== null) {
                    return $cached;
                }
            }

            $result = $builder->connection->fetch($sql, $builder->params);

            if ($builder->model && $result) {
                $model = new $builder->model($result, true);

                if (!empty($builder->with)) {
                    $collection = new Collection([$model]);
                    $builder->model::loadRelations($collection, $builder->with);
                }

                if ($builder->useCache && $cacheKey) {
                    $builder->model::putInCache($cacheKey, $model);
                }

                return $model;
            }

            if ($builder->useCache && $cacheKey && $result) {
                $builder->model::putInCache($cacheKey, $result);
            }

            return $result;
        });
    }

    /**
     * Execute the query and get the first result or call a callback.
     *
     * @param  \Closure  $callback
     * @return mixed
     */
    public function firstOr(Closure $callback)
    {
        if (!is_null($model = $this->first())) {
            return $model;
        }

        return $callback();
    }

    public function find($id, array $columns = ['*']): mixed
    {
        return $this->where('id', '=', $id)->first($columns);
    }

    public function firstWhere(string $column, $operator = null, $value = null): mixed
    {
        return $this->where($column, $operator, $value)->first();
    }

    public function findOrFail($id, array $columns = ['*'])
    {
        $result = $this->find($id, $columns);
        if (!$result) {
            if ($this->model) {
                throw (new \Plugs\Database\Exceptions\ModelNotFoundException())->setModel($this->model, $id);
            }
            throw new Exception("Record not found with ID: " . (is_array($id) ? implode(', ', $id) : (string) $id));
        }

        return $result;
    }

    public function findMany(array $ids, array $columns = ['*']): array|Collection
    {
        return $this->whereIn('id', $ids)->get($columns);
    }

    public function firstOrFail(array $columns = ['*'])
    {
        $result = $this->first($columns);
        if (!$result) {
            throw new Exception("No records found");
        }

        return $result;
    }

    public function all(array $columns = ['*']): array|Collection
    {
        return $this->get($columns);
    }

    public function pluck(string $column, ?string $key = null): array
    {
        $results = $this->get([$column, $key ?: $column]);
        $data = $results instanceof Collection ? $results->all() : $results;

        if (empty($data)) {
            return [];
        }

        $plucked = [];
        foreach ($data as $row) {
            $value = is_object($row) ? $row->$column : $row[$column];

            if ($key) {
                $keyValue = is_object($row) ? $row->$key : $row[$key];
                $plucked[$keyValue] = $value;
            } else {
                $plucked[] = $value;
            }
        }

        return $plucked;
    }

    public function value(string $column)
    {
        $result = $this->first([$column]);

        if (!$result) {
            return null;
        }

        return is_object($result) ? $result->$column : $result[$column];
    }

    public function exists(): bool
    {
        return $this->first() !== null;
    }

    /**
     * Get the only record that matches the criteria.
     */
    public function sole(array $columns = ['*'])
    {
        $results = $this->limit(2)->get($columns);

        $count = count($results);

        if ($count === 0) {
            throw new Exception("No records found.");
        }

        if ($count > 1) {
            throw new Exception("Multiple records found.");
        }

        return $results[0];
    }

    /**
     * Chunk the results of the query.
     */
    public function chunk(int $count, callable $callback): bool
    {
        $page = 1;

        do {
            $results = $this->offset(($page - 1) * $count)->limit($count)->get();

            $countResults = count($results);

            if ($countResults == 0) {
                break;
            }

            if ($callback($results, $page) === false) {
                return false;
            }

            unset($results);

            $page++;
        } while ($countResults == $count);

        return true;
    }

    /**
     * Chunk the results of the query by ID.
     * More efficient than chunk() for large datasets as it avoids OFFSET performance issues.
     *
     * @param int $count Number of records per chunk
     * @param callable $callback Function to process each chunk
     * @param string|null $column The column to use for chunking (usually primary key)
     * @param string|null $alias The alias for the column if different from column name
     * @return bool
     */
    public function chunkById(int $count, callable $callback, ?string $column = 'id', ?string $alias = null): bool
    {
        $alias = $alias ?? $column;
        $lastId = null;

        do {
            $clone = clone $this;

            if ($lastId !== null) {
                $clone->where($column, '>', $lastId);
            }

            $results = $clone->orderBy($column, 'ASC')->limit($count)->get();
            $countResults = count($results);

            if ($countResults == 0) {
                break;
            }

            if ($callback($results) === false) {
                return false;
            }

            $lastRow = $results[$countResults - 1];
            $lastId = is_object($lastRow) ? $lastRow->$alias : $lastRow[$alias];

            unset($results);
        } while ($countResults == $count);

        return true;
    }

    public function insert(array $data): bool
    {
        // Check if data is multidimensional numeric array (bulk insert)
        $isBulk = isset($data[0]) && is_array($data[0]);

        if (!$isBulk) {
            $data = [$data];
        }

        /** @phpstan-ignore-next-line */
        if (empty($data)) {
            return false;
        }

        // Assume all rows have same keys as the first row
        $firstRow = $data[0];
        $columns = array_keys($firstRow);

        $placeholders = [];
        $params = [];

        foreach ($data as $rowIndex => $row) {
            $rowPlaceholders = [];
            foreach ($columns as $column) {
                // Use unique parameter names for each row and column
                $paramName = ":insert_{$rowIndex}_{$column}";
                $rowPlaceholders[] = $paramName;
                $params[$paramName] = $row[$column] ?? null;
            }
            $placeholders[] = '(' . implode(', ', $rowPlaceholders) . ')';
        }

        $sql = "INSERT INTO " . QueryUtils::wrapIdentifier($this->table) . " (" . implode(', ', array_map([QueryUtils::class, 'wrapIdentifier'], $columns)) . ") VALUES " . implode(', ', $placeholders);

        return $this->connection->execute($sql, $params) > 0;
    }

    /**
     * Insert new records or update the existing ones.
     */
    public function upsert(array $values, array $uniqueBy, array $update = null): int
    {
        $driver = $this->connection->getConfig()['driver'] ?? 'mysql';
        if ($driver !== 'mysql') {
            throw new Exception("upsert() is currently only supported for MySQL. Use updateOrInsert() for cross-platform support.");
        }

        if (empty($values)) {
            return 0;
        }

        if (!isset($values[0]) || !is_array($values[0])) {
            $values = [$values];
        }

        $columns = array_keys($values[0]);

        if (is_null($update)) {
            $update = $columns;
        }

        $params = [];
        $placeholders = [];

        foreach ($values as $rowIndex => $row) {
            $rowPlaceholders = [];
            foreach ($columns as $column) {
                $paramName = ":upsert_{$rowIndex}_{$column}";
                $rowPlaceholders[] = $paramName;
                $params[$paramName] = $row[$column] ?? null;
            }
            $placeholders[] = '(' . implode(', ', $rowPlaceholders) . ')';
        }

        $updateSql = [];
        foreach ($update as $column) {
            $updateSql[] = QueryUtils::wrapIdentifier($column) . " = VALUES(" . QueryUtils::wrapIdentifier($column) . ")";
        }

        $sql = "INSERT INTO " . QueryUtils::wrapIdentifier($this->table) . " (" . implode(', ', array_map([QueryUtils::class, 'wrapIdentifier'], $columns)) . ") VALUES " . implode(', ', $placeholders);
        $sql .= " ON DUPLICATE KEY UPDATE " . implode(', ', $updateSql);

        return $this->connection->execute($sql, $params);
    }

    public function updateOrInsert(array $attributes, array $values = []): bool
    {
        $exists = (clone $this)->where($attributes)->exists();

        if (!$exists) {
            return (clone $this)->insert(array_merge($attributes, $values));
        }

        if (empty($values)) {
            return true;
        }

        return (clone $this)->where($attributes)->limit(1)->update($values) > 0;
    }

    public function update(array $values): int
    {
        return $this->runThroughPipeline(function ($builder) use ($values) {
            if (empty($values)) {
                return 0;
            }

            $sql = "UPDATE " . QueryUtils::wrapIdentifier($builder->table) . " SET ";
            $sets = [];

            foreach ($values as $column => $value) {
                if ($value instanceof Raw) {
                    $sets[] = QueryUtils::wrapIdentifier($column) . " = {$value}";
                } else {
                    $placeholder = ":update_" . count($builder->params) . "_" . str_replace('.', '_', $column);
                    $sets[] = QueryUtils::wrapIdentifier($column) . " = {$placeholder}";
                    $builder->params[$placeholder] = $value;
                }
            }

            $sql .= implode(', ', $sets);
            $sql .= $builder->getWhereClause();

            return $builder->connection->execute($sql, $builder->params);
        });
    }

    /**
     * Increment a column's value by a given amount.
     */
    public function increment(string $column, float|int $amount = 1, array $extra = []): int
    {
        return $this->incrementOrDecrement($column, $amount, $extra, '+');
    }

    /**
     * Decrement a column's value by a given amount.
     */
    public function decrement(string $column, float|int $amount = 1, array $extra = []): int
    {
        return $this->incrementOrDecrement($column, $amount, $extra, '-');
    }

    /**
     * Run an increment or decrement of a given column.
     */
    protected function incrementOrDecrement(string $column, float|int $amount, array $extra, string $operator): int
    {
        $wrapped = QueryUtils::wrapIdentifier($column);

        $columns = array_merge([$column => new Raw("{$wrapped} {$operator} {$amount}")], $extra);

        return $this->update($columns);
    }

    public function delete(): int
    {
        return $this->runThroughPipeline(function ($builder) {
            $sql = "DELETE FROM " . QueryUtils::wrapIdentifier($builder->table);
            $sql .= $builder->getWhereClause();

            return $builder->connection->execute($sql, $builder->params);
        });
    }

    public function count(string $column = '*'): int
    {
        return $this->runThroughPipeline(function ($builder) use ($column) {
            $column = $column === '*' ? $column : QueryUtils::wrapIdentifier($column);
            $sql = "SELECT COUNT({$column}) as count FROM " . QueryUtils::wrapIdentifier($builder->table);

            if (!empty($builder->joins)) {
                $driver = $builder->connection->getConfig()['driver'] ?? 'mysql';
                foreach ($builder->joins as $join) {
                    if (isset($join['isRaw']) && $join['isRaw']) {
                        $sql .= " {$join['type']} JOIN {$join['table']} ON {$join['first']} {$join['operator']} {$join['second']}";
                    } else {
                        $wrappedTable = QueryUtils::wrapIdentifier($join['table'], $driver);
                        $wrappedFirst = QueryUtils::wrapIdentifier($join['first'], $driver);
                        $wrappedSecond = QueryUtils::wrapIdentifier($join['second'], $driver);
                        $sql .= " {$join['type']} JOIN {$wrappedTable} ON {$wrappedFirst} {$join['operator']} {$wrappedSecond}";
                    }
                }
            }

            $sql .= $builder->getWhereClause();

            $result = $builder->connection->fetch($sql, $builder->params);

            return (int) ($result['count'] ?? 0);
        });
    }

    public function sum(string $column)
    {
        return $this->aggregate('SUM', $column);
    }

    public function avg(string $column)
    {
        return $this->aggregate('AVG', $column);
    }

    public function min(string $column)
    {
        return $this->aggregate('MIN', $column);
    }

    public function max(string $column)
    {
        return $this->aggregate('MAX', $column);
    }

    protected function aggregate(string $function, string $column)
    {
        QueryUtils::sanitizeColumn($column);
        $wrappedColumn = QueryUtils::wrapIdentifier($column);

        return $this->runThroughPipeline(function ($builder) use ($function, $wrappedColumn) {
            $sql = "SELECT {$function}({$wrappedColumn}) as aggregate FROM " . QueryUtils::wrapIdentifier($builder->table);
            $sql .= $builder->getWhereClause();

            $result = $builder->connection->fetch($sql, $builder->params);

            return $result['aggregate'] ?? 0;
        });
    }

    /**
     * Paginate the query.
     */
    public function paginate(int $perPage = 15, ?int $page = null, array $columns = ['*']): Pagination
    {
        $page = $page ?? (int) ($_GET['page'] ?? $_REQUEST['page'] ?? 1);
        $page = max(1, $page);

        $total = $this->count();
        $offset = ($page - 1) * $perPage;

        $this->limit($perPage)->offset($offset);

        $results = $this->get($columns);

        return new Pagination($results, $perPage, $page, $total);
    }

    /**
     * Paginate the query without a total count (lighter).
     */
    public function simplePaginate(int $perPage = 15, ?int $page = null, array $columns = ['*']): Pagination
    {
        $page = $page ?? (int) ($_GET['page'] ?? $_REQUEST['page'] ?? 1);
        $page = max(1, $page);

        $offset = ($page - 1) * $perPage;

        // Fetch perPage + 1 to determine if there is a next page
        $this->limit($perPage + 1)->offset($offset);

        $results = $this->get($columns);
        $collection = $results instanceof Collection ? $results : new Collection($results);

        $items = $collection->all();
        $hasNextPage = count($items) > $perPage;

        if ($hasNextPage) {
            array_pop($items);
        }

        $paginator = new Pagination($items, $perPage, $page, null);
        $paginator->setOptions(['simple_mode' => true]);
        $paginator->setSimpleHasMore($hasNextPage);

        return $paginator;
    }

    /**
     * Get paginated results as a standardized API response.
     */
    public function paginateResponse(int $perPage = 15, ?int $page = null, array $columns = ['*']): StandardResponse
    {
        $paginator = $this->paginate($perPage, $page, $columns);
        $paginated = $paginator->toArray();

        $meta = [
            'total' => $paginated['total'],
            'per_page' => $paginated['per_page'],
            'current_page' => $paginated['current_page'],
            'last_page' => $paginated['last_page'],
            'from' => $paginated['from'],
            'to' => $paginated['to'],
            'path' => $paginated['path'],
        ];

        $links = [
            'first' => $paginated['first_page_url'],
            'last' => $paginated['last_page_url'],
            'prev' => $paginated['prev_page_url'],
            'next' => $paginated['next_page_url'],
        ];

        return StandardResponse::success($paginated['data'])
            ->withMeta($meta)
            ->withLinks($links);
    }

    public function allResponse(array $columns = ['*'], int $status = 200, ?string $message = null): StandardResponse
    {
        $results = $this->all($columns);
        $data = $results instanceof Collection ? $results->all() : $results;

        return new StandardResponse($data, true, $status, $message);
    }

    public function firstResponse(array $columns = ['*'], int $status = 200, ?string $message = null): StandardResponse
    {
        $result = $this->first($columns);

        if (!$result) {
            return StandardResponse::error("No records found", 404);
        }

        $data = is_object($result) && method_exists($result, 'toArray') ? $result->toArray() : $result;

        return new StandardResponse($data, true, $status, $message);
    }

    public function findResponse($id, array $columns = ['*'], int $status = 200, ?string $message = null): StandardResponse
    {
        $result = $this->find($id, $columns);

        if (!$result) {
            return StandardResponse::error("Record not found", 404);
        }

        $data = is_object($result) && method_exists($result, 'toArray') ? $result->toArray() : $result;

        return new StandardResponse($data, true, $status, $message);
    }

    /**
     * Apply filters from request parameters or a QueryFilter instance
     */
    public function filter(array|\Plugs\Database\Filters\QueryFilter $params): self
    {
        if ($params instanceof \Plugs\Database\Filters\QueryFilter) {
            return $params->apply($this);
        }

        $instance = $this->model ? new $this->model() : null;
        $allowedFilters = null;
        if ($instance && method_exists($instance, 'getFilterableColumns')) {
            $allowedFilters = $instance->getFilterableColumns();
        }

        foreach ($params as $key => $value) {
            if ($value === null || $value === '' || in_array($key, ['page', 'per_page', 'direction'])) {
                continue;
            }

            if ($key === 'search') {
                if ($instance && method_exists($instance, 'getSearchableColumns')) {
                    $searchColumns = $instance->getSearchableColumns();
                    if (!empty($searchColumns)) {
                        $this->where(function ($query) use ($searchColumns, $value) {
                            foreach ($searchColumns as $column) {
                                $query->orWhere($column, 'LIKE', "%{$value}%");
                            }
                        });
                    }
                }
                continue;
            }

            if ($key === 'sort') {
                $this->orderBy($value, strtoupper($params['direction'] ?? 'ASC'));
                continue;
            }

            // Security check: Only allow filtering by allowed columns if specified
            if ($allowedFilters !== null && !in_array($key, $allowedFilters, true)) {
                continue;
            }

            if (is_array($value)) {
                $this->whereIn($key, $value);
            } else {
                $this->where($key, '=', $value);
            }
        }

        return $this;
    }

    /**
     * Search and paginate with request parameters
     */
    public function search(?array $params = null): Pagination
    {
        $params = $params ?? $_GET;

        $perPage = (int) ($params['per_page'] ?? 15);
        $page = (int) ($params['page'] ?? 1);

        $this->filter($params);

        $paginator = $this->paginate($perPage, $page);
        $paginator->appends(array_filter($params, fn($k) => !in_array($k, ['page', 'per_page']), ARRAY_FILTER_USE_KEY));

        return $paginator;
    }

    public function searchResponse(?array $params = null): StandardResponse
    {
        $paginator = $this->search($params);
        $paginated = $paginator->toArray();

        $meta = [
            'total' => $paginated['total'],
            'per_page' => $paginated['per_page'],
            'current_page' => $paginated['current_page'],
            'last_page' => $paginated['last_page'],
            'from' => $paginated['from'],
            'to' => $paginated['to'],
            'path' => $paginated['path'],
        ];

        $links = [
            'first' => $paginated['first_page_url'],
            'last' => $paginated['last_page_url'],
            'prev' => $paginated['prev_page_url'],
            'next' => $paginated['next_page_url'],
        ];

        return StandardResponse::success($paginated['data'])
            ->withMeta($meta)
            ->withLinks($links);
    }

    public function buildSelectSql(): string
    {
        $driver = $this->connection->getConfig()['driver'] ?? 'mysql';
        $columns = array_map(function ($col) use ($driver) {
            if ($col instanceof \Plugs\Database\Raw) {
                return (string) $col;
            }
            return QueryUtils::wrapIdentifier((string) $col, $driver);
        }, $this->select);

        $distinct = $this->distinct ? 'DISTINCT ' : '';
        $sql = "SELECT {$distinct}" . implode(', ', $columns) . " FROM " . QueryUtils::wrapIdentifier($this->table, $driver);

        if (!empty($this->joins)) {
            foreach ($this->joins as $join) {
                $type = strtoupper($join['type'] ?? 'INNER');
                if (isset($join['isRaw']) && $join['isRaw']) {
                    $sql .= " {$type} JOIN {$join['table']} ON {$join['first']} {$join['operator']} {$join['second']}";
                } else {
                    $wrappedTable = QueryUtils::wrapIdentifier($join['table'], $driver);
                    $wrappedFirst = QueryUtils::wrapIdentifier($join['first'], $driver);
                    $wrappedSecond = QueryUtils::wrapIdentifier($join['second'], $driver);
                    $sql .= " {$type} JOIN {$wrappedTable} ON {$wrappedFirst} {$join['operator']} {$wrappedSecond}";
                }
            }
        }

        $sql .= $this->getWhereClause();

        if (!empty($this->groupBy)) {
            $sql .= " GROUP BY " . implode(', ', $this->groupBy);
        }

        if (!empty($this->having)) {
            $sql .= " HAVING ";
            foreach ($this->having as $index => $condition) {
                $boolean = $index === 0 ? '' : " {$condition['boolean']} ";
                $sql .= "{$boolean}{$condition['column']} {$condition['operator']} {$condition['value']}";
            }
        }

        if (!empty($this->orderBy)) {
            $sql .= " ORDER BY " . implode(', ', $this->orderBy);
        }

        if ($this->limit !== null) {
            $sql .= " LIMIT {$this->limit}";
        }

        if ($this->offset !== null) {
            $sql .= " OFFSET {$this->offset}";
        }

        return $sql;
    }

    /**
     * Get the SQL representation of the query.
     */
    public function toSql(): string
    {
        return $this->runThroughPipeline(function ($builder) {
            return $builder->buildSelectSql();
        });
    }

    /**
     * Get the current query bindings.
     */
    public function getBindings(): array
    {
        return $this->params;
    }

    /**
     * Set the middleware that the query should be sent through.
     *
     * @param  array|string|Closure  $middleware
     * @return $this
     */
    public function through($middleware): self
    {
        $this->middleware = is_array($middleware) ? $middleware : func_get_args();

        return $this;
    }

    /**
     * Run the query through the registered middleware pipeline.
     *
     * @param  Closure  $destination
     * @return mixed
     */
    protected function runThroughPipeline(Closure $destination)
    {
        // Trigger onQueryExecute for all queries
        if ($this->model && method_exists($this->model, 'query')) {
            $instance = new $this->model();
            if (method_exists($instance, 'fireModelEvent')) {
                $instance->fireModelEvent('onQueryExecute', ['builder' => $this]);
            }
        }

        if (empty($this->middleware)) {
            return $destination($this);
        }

        return QueryPipeline::send($this, $this->middleware, $destination);
    }

    /**
     * Get a scope proxy for the builder.
     *
     * @return ScopeProxy
     */
    public function scoped(): ScopeProxy
    {
        return new ScopeProxy($this);
    }

    public function withCount($relations): self
    {
        if (!$this->model) {
            throw new Exception("withCount requires a model to be set on the builder.");
        }

        $relations = is_string($relations) ? func_get_args() : $relations;

        $modelInstance = new $this->model();

        foreach ($relations as $relation) {
            if (!method_exists($modelInstance, $relation)) {
                throw new Exception("Relationship [{$relation}] not found on model [" . get_class($modelInstance) . "].");
            }

            $proxy = $modelInstance->$relation();
            $reflection = new \ReflectionObject($proxy);

            $relatedBuilder = null;
            $foreignKey = null;
            $localKey = null;
            $pivotTable = null;
            $foreignPivotKey = null;
            $relatedPivotKey = null;
            $morphType = null;
            $morphClass = null;

            if ($reflection->hasProperty('builder')) {
                $prop = $reflection->getProperty('builder');
                $prop->setAccessible(true);
                $relatedBuilder = $prop->getValue($proxy);
            }

            if ($reflection->hasProperty('foreignKey')) {
                $prop = $reflection->getProperty('foreignKey');
                $prop->setAccessible(true);
                $foreignKey = $prop->getValue($proxy);
            }

            if ($reflection->hasProperty('localKey')) {
                $prop = $reflection->getProperty('localKey');
                $prop->setAccessible(true);
                $localKey = $prop->getValue($proxy);
            }

            if ($reflection->hasProperty('ownerKey')) {
                $prop = $reflection->getProperty('ownerKey');
                $prop->setAccessible(true);
                $localKey = $prop->getValue($proxy);
            }

            if ($reflection->hasProperty('config')) {
                $prop = $reflection->getProperty('config');
                $prop->setAccessible(true);
                $config = $prop->getValue($proxy);

                if (isset($config['pivotTable'])) {
                    $pivotTable = $config['pivotTable'];
                    $foreignPivotKey = $config['foreignPivotKey'];
                    $relatedPivotKey = $config['relatedPivotKey'];
                    $relatedClass = $config['relatedClass'];
                    $relatedBuilder = $relatedClass::query();
                    $localKey = $config['parentKey'];

                    if (isset($config['isMorph']) && $config['isMorph']) {
                        $morphType = $config['morphType'];
                        $morphClass = $config['morphClass'];
                    }
                }
            }

            if (!$relatedBuilder) {
                throw new Exception("Could not resolve relationship builder for [{$relation}].");
            }

            $relatedTable = $relatedBuilder->getTable();
            $parentTable = $this->table;

            $subQuery = new static($this->connection);
            $subQuery->table($relatedTable);
            $subQuery->select = []; // Clear default '*'
            $subQuery->selectRaw('COUNT(*)');

            if ($pivotTable) {
                $subQuery->join($pivotTable, "{$pivotTable}.{$relatedPivotKey}", '=', "{$relatedTable}.id");
                $subQuery->where("{$pivotTable}.{$foreignPivotKey}", '=', new \Plugs\Database\Raw("{$parentTable}.{$localKey}"));

                if ($morphType && $morphClass) {
                    $subQuery->where("{$pivotTable}.{$morphType}", '=', $morphClass);
                }
            } else {
                if ($localKey === null) {
                    $localKey = 'id';
                }

                // For MorphMany, handle the morph type constraint
                if (str_contains(get_class($proxy), 'HasManyProxy') || str_contains(get_class($proxy), 'HasOneProxy')) {
                    if ($reflection->hasProperty('morphType') && $reflection->hasProperty('morphClass')) {
                        $mTypeProp = $reflection->getProperty('morphType');
                        $mTypeProp->setAccessible(true);
                        $mClassProp = $reflection->getProperty('morphClass');
                        $mClassProp->setAccessible(true);

                        $mType = $mTypeProp->getValue($proxy);
                        $mClass = $mClassProp->getValue($proxy);

                        if ($mType && $mClass) {
                            $subQuery->where("{$relatedTable}.{$mType}", '=', $mClass);
                        }
                    } else if (method_exists($proxy, 'getMorphType')) {
                        // Check if it's a polymorphic relation via specific method if needed
                    }
                }

                $subQuery->where("{$relatedTable}.{$foreignKey}", '=', new \Plugs\Database\Raw("{$parentTable}.{$localKey}"));
            }

            $sql = $subQuery->buildSelectSql();

            $this->selectRaw("({$sql}) AS " . QueryUtils::wrapIdentifier("{$relation}_count"), $subQuery->getParams());
        }

        return $this;
    }

    public function __call($method, $parameters)
    {
        if ($this->model) {
            $instance = new $this->model();
            $scopeMethod = 'scope' . ucfirst($method);

            // Prefer methods marked with #[QueryScope]
            $reflection = new \ReflectionClass($instance);

            // Try the exact method name if it has the attribute
            if ($reflection->hasMethod($method)) {
                $refMethod = $reflection->getMethod($method);
                if (!empty($refMethod->getAttributes(\Plugs\Database\Attributes\QueryScope::class))) {
                    array_unshift($parameters, $this);
                    return $instance->$method(...$parameters);
                }
            }

            // Fallback to legacy scopeMethod (scopeName)
            if (method_exists($instance, $scopeMethod)) {
                array_unshift($parameters, $this);

                return $instance->$scopeMethod(...$parameters);
            }
        }

        // Handle magic where methods (e.g. whereEmail)
        if (str_starts_with($method, 'where') && strlen($method) > 5) {
            $column = strtolower(preg_replace('/([a-z])([A-Z])/', '$1_$2', substr($method, 5)));
            return $this->where($column, '=', $parameters[0]);
        }

        throw new BadMethodCallException("Method [{$method}] does not exist on the query builder.");
    }
}
