<?php

declare(strict_types=1);

namespace Plugs\Database;

use PDO;
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
        $this->select = array_map([QueryUtils::class, 'sanitizeColumn'], $columns);

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
        // Handle closure for nested query
        if ($column instanceof Closure) {
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

        // Handle standard where: where('col', 'val') or where('col', null)
        if (func_num_args() === 2) {
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

    public function orderBy(string $column, string $direction = 'ASC'): self
    {
        QueryUtils::sanitizeColumn($column);
        $this->orderBy[] = QueryUtils::wrapIdentifier($column) . " " . (strtoupper($direction) === 'DESC' ? 'DESC' : 'ASC');

        return $this;
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
        if ($columns !== ['*']) {
            $this->select($columns);
        }

        $sql = $this->buildSelectSql();
        $results = $this->connection->fetchAll($sql, $this->params);

        if ($this->model && !empty($results)) {
            $models = array_map(fn($item) => new $this->model($item, true), $results);

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
            $model = new $this->model($result, true);

            if (!empty($this->with)) {
                $collection = new Collection([$model]);
                $this->model::loadRelations($collection, $this->with);
            }

            return $model;
        }

        return $result;
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
                throw (new \Plugs\Database\Exception\ModelNotFoundException())->setModel($this->model, $id);
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

    public function update(array $data): int
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

    public function delete(): int
    {
        $sql = "DELETE FROM " . QueryUtils::wrapIdentifier($this->table);
        $sql .= $this->getWhereClause();

        return $this->connection->execute($sql, $this->params);
    }

    public function count(string $column = '*'): int
    {
        $sql = "SELECT COUNT({$column}) as count FROM " . QueryUtils::wrapIdentifier($this->table);

        if (!empty($this->joins)) {
            foreach ($this->joins as $join) {
                $sql .= " {$join['type']} JOIN {$join['table']} ON {$join['first']} {$join['operator']} {$join['second']}";
            }
        }

        $sql .= $this->getWhereClause();

        $result = $this->connection->fetch($sql, $this->params);

        return (int) ($result['count'] ?? 0);
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
    public function simplePaginate(int $perPage = 15, ?int $page = null, array $columns = ['*']): array
    {
        $page = $page ?? (int) ($_GET['page'] ?? $_REQUEST['page'] ?? 1);
        $page = max(1, $page);

        $offset = ($page - 1) * $perPage;

        // Fetch perPage + 1 to determine if there is a next page
        $this->limit($perPage + 1)->offset($offset);

        $results = $this->get($columns);
        $collection = $results instanceof Collection ? $results->all() : $results;

        $hasNextPage = count($collection) > $perPage;

        if ($hasNextPage) {
            array_pop($collection);
        }

        return [
            'data' => $collection,
            'current_page' => $page,
            'per_page' => $perPage,
            'has_next' => $hasNextPage,
            'has_prev' => $page > 1,
        ];
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
        $params = $params ?? $_GET ?? $_REQUEST ?? [];

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
        $columns = array_map([QueryUtils::class, 'wrapIdentifier'], $this->select);
        $sql = "SELECT " . implode(', ', $columns) . " FROM " . QueryUtils::wrapIdentifier($this->table);

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

    /**
     * Get a scope proxy for the builder.
     *
     * @return ScopeProxy
     */
    public function scoped(): ScopeProxy
    {
        return new ScopeProxy($this);
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

            // Check if scopeMethod has the attribute (even if called by short name)
            if (method_exists($instance, $scopeMethod)) {
                $refMethod = $reflection->getMethod($scopeMethod);
                if (!empty($refMethod->getAttributes(\Plugs\Database\Attributes\QueryScope::class))) {
                    array_unshift($parameters, $this);
                    return $instance->$scopeMethod(...$parameters);
                }
            }
        }

        throw new BadMethodCallException("Method [{$method}] does not exist on the query builder.");
    }
}
