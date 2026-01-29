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
                    'type' => 'Nested',
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

        $placeholder = ':where_' . count($this->params) . '_' . str_replace('.', '_', $column);

        $this->where[] = [
            'type' => 'Basic',
            'query' => "{$column} {$operator} {$placeholder}",
            'boolean' => $boolean,
        ];

        $this->params[$placeholder] = $value;

        return $this;
    }

    public function orWhere($column, $operator = null, $value = null): self
    {
        return $this->where($column, $operator, $value, 'OR');
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
            $sql .= " {$boolean} " . $condition['query'];
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

    private function buildSelectSql(): string
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
}
