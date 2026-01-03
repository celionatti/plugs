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

    public function __construct(Connection $connection)
    {
        $this->connection = $connection;
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
                    'params' => $query->getParams()
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
            'boolean' => $boolean
        ];

        $this->params[$placeholder] = $value;
        return $this;
    }

    public function orWhere($column, $operator = null, $value = null): self
    {
        return $this->where($column, $operator, $value, 'OR');
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
            'boolean' => 'AND'
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

    public function get(): array
    {
        $sql = $this->buildSelectSql();
        return $this->connection->fetchAll($sql, $this->params);
    }

    public function first(): ?array
    {
        $this->limit(1);
        $sql = $this->buildSelectSql();
        return $this->connection->fetch($sql, $this->params);
    }

    public function find($id): ?array
    {
        return $this->where('id', '=', $id)->first();
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