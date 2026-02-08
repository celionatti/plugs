<?php

declare(strict_types=1);

namespace Plugs\Database;

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
    private $orderBy = [];
    private $limit = null;
    private $offset = null;
    protected $model = null;
    protected $with = [];
    protected $joins = [];

    public function __construct(Connection $connection)
    {
        $this->connection = $connection;
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

    public function select(array $columns = ['*']): self
    {
        $this->select = $columns;

        return $this;
    }

    public function setModel(string $model): self
    {
        $this->model = $model;

        return $this;
    }

    public function where($column, $operator = null, $value = null, $boolean = 'AND'): self
    {
        // Handle closure for nested query
        if ($column instanceof \Closure) {
            $query = new static($this->connection);
            $query->table($this->table);
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

        // Handle standard where: where('col', 'val') -> where('col', '=', 'val')
        if ($value === null && $operator !== null) {
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

            $this->where[] = [
                'type' => 'Basic',
                'query' => "{$column} {$operator} {$placeholder}",
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
     * Add a "where has" relationship constraint.
     */
    public function whereHas(string $relation, ?\Closure $callback = null, string $boolean = 'AND'): self
    {
        if (!$this->model) {
            throw new \Exception("whereHas requires a model to be set on the builder.");
        }

        $modelInstance = new $this->model();

        // Use reflection to get relation details via a temporary proxy
        if (!method_exists($modelInstance, $relation)) {
            throw new \Exception("Relationship [{$relation}] not found on model [" . get_class($modelInstance) . "].");
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
            throw new \Exception("Could not resolve relationship builder for [{$relation}].");
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

    public function orWhereHas(string $relation, ?\Closure $callback = null): self
    {
        return $this->whereHas($relation, $callback, 'OR');
    }

    public function join(string $table, string $first, string $operator, string $second, string $type = 'INNER'): self
    {
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
        $this->where[] = [
            'type' => 'Null',
            'query' => "{$column} IS NULL",
            'boolean' => $boolean,
        ];

        return $this;
    }

    public function whereNotNull(string $column, string $boolean = 'AND'): self
    {
        $this->where[] = [
            'type' => 'NotNull',
            'query' => "{$column} IS NOT NULL",
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
        $placeholders = [];
        foreach ($values as $i => $value) {
            $placeholder = ":wherein_{$column}_{$i}";
            $placeholders[] = $placeholder;
            $this->params[$placeholder] = $value;
        }

        $this->where[] = [
            'type' => 'In',
            'query' => "{$column} IN (" . implode(', ', $placeholders) . ")",
            'boolean' => 'AND',
        ];

        return $this;
    }

    public function orderBy(string $column, string $direction = 'ASC'): self
    {
        $this->orderBy[] = "{$column} {$direction}";

        return $this;
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
        if ($columns !== ['*']) {
            $this->select($columns);
        }

        $sql = $this->buildSelectSql();
        $results = $this->connection->fetchAll($sql, $this->params);

        if ($this->model && !empty($results)) {
            $models = array_map(fn ($item) => new $this->model($item, true), $results);

            if (!empty($this->with)) {
                $collection = new Collection($models);
                $this->model::loadRelations($collection, $this->with);

                return $collection;
            }

            return new Collection($models);
        }

        return $results;
    }

    public function first(array $columns = ['*']): mixed
    {
        $this->limit(1);

        if ($columns !== ['*']) {
            $this->select($columns);
        }

        $sql = $this->buildSelectSql();
        $result = $this->connection->fetch($sql, $this->params);

        if ($this->model && $result) {
            return new $this->model($result, true);
        }

        return $result;
    }

    public function find($id, array $columns = ['*']): mixed
    {
        return $this->where('id', '=', $id)->first($columns);
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
            throw new \Exception("No records found.");
        }

        if ($count > 1) {
            throw new \Exception("Multiple records found.");
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

        $sql = "INSERT INTO {$this->table} (" . implode(', ', $columns) . ") VALUES " . implode(', ', $placeholders);

        return $this->connection->execute($sql, $params);
    }

    public function update(array $data): bool
    {
        $sets = [];
        $params = $this->params;

        foreach ($data as $column => $value) {
            $placeholder = ":set_{$column}";
            $sets[] = "{$column} = {$placeholder}";
            $params[$placeholder] = $value;
        }

        $sql = "UPDATE {$this->table} SET " . implode(', ', $sets);
        $sql .= $this->getWhereClause();

        return $this->connection->execute($sql, $params);
    }

    public function delete(): bool
    {
        $sql = "DELETE FROM {$this->table}";
        $sql .= $this->getWhereClause();

        return $this->connection->execute($sql, $this->params);
    }

    public function count(): int
    {
        $sql = "SELECT COUNT(*) as count FROM {$this->table}";
        $sql .= $this->getWhereClause();

        $result = $this->connection->fetch($sql, $this->params);

        return (int) ($result['count'] ?? 0);
    }

    /**
     * Paginate the query.
     */
    public function paginate(int $perPage = 15, ?int $page = null, array $columns = ['*']): \Plugs\Paginator\Pagination
    {
        $page = $page ?? (int) ($_GET['page'] ?? $_REQUEST['page'] ?? 1);
        $page = max(1, $page);

        $total = $this->count();
        $offset = ($page - 1) * $perPage;

        $this->limit($perPage)->offset($offset);

        $results = $this->get($columns);

        return new \Plugs\Paginator\Pagination($results, $perPage, $page, $total);
    }

    public function buildSelectSql(): string
    {
        $sql = "SELECT " . implode(', ', $this->select) . " FROM {$this->table}";

        if (!empty($this->joins)) {
            foreach ($this->joins as $join) {
                $sql .= " {$join['type']} JOIN {$join['table']} ON {$join['first']} {$join['operator']} {$join['second']}";
            }
        }

        $sql .= $this->getWhereClause();

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

    public function __call($method, $parameters)
    {
        if ($this->model) {
            $instance = new $this->model();
            $scopeMethod = 'scope' . ucfirst($method);

            if (method_exists($instance, $scopeMethod)) {
                array_unshift($parameters, $this);

                return $instance->$scopeMethod(...$parameters);
            }
        }

        throw new \BadMethodCallException("Method [{$method}] does not exist on the query builder.");
    }
}
