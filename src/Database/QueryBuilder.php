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
    
    public function where(string $column, string $operator, $value): self
    {
        $placeholder = ':where_' . count($this->params);
        $this->where[] = "{$column} {$operator} {$placeholder}";
        $this->params[$placeholder] = $value;
        return $this;
    }
    
    public function orWhere(string $column, string $operator, $value): self
    {
        $placeholder = ':where_' . count($this->params);
        
        if (empty($this->where)) {
            $this->where[] = "{$column} {$operator} {$placeholder}";
        } else {
            $this->where[] = "OR {$column} {$operator} {$placeholder}";
        }
        
        $this->params[$placeholder] = $value;
        return $this;
    }
    
    public function whereIn(string $column, array $values): self
    {
        $placeholders = [];
        foreach ($values as $i => $value) {
            $placeholder = ":wherein_{$column}_{$i}";
            $placeholders[] = $placeholder;
            $this->params[$placeholder] = $value;
        }
        
        $this->where[] = "{$column} IN (" . implode(', ', $placeholders) . ")";
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
        $columns = array_keys($data);
        $placeholders = array_map(fn($col) => ":{$col}", $columns);
        
        $sql = "INSERT INTO {$this->table} (" . implode(', ', $columns) . ") VALUES (" . implode(', ', $placeholders) . ")";
        
        $params = [];
        foreach ($data as $key => $value) {
            $params[":{$key}"] = $value;
        }
        
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
        
        if (!empty($this->where)) {
            $sql .= " WHERE " . implode(' AND ', $this->where);
        }
        
        return $this->connection->execute($sql, $params);
    }
    
    public function delete(): bool
    {
        $sql = "DELETE FROM {$this->table}";
        
        if (!empty($this->where)) {
            $sql .= " WHERE " . implode(' AND ', $this->where);
        }
        
        return $this->connection->execute($sql, $this->params);
    }
    
    public function count(): int
    {
        $sql = "SELECT COUNT(*) as count FROM {$this->table}";
        
        if (!empty($this->where)) {
            $sql .= " WHERE " . implode(' AND ', $this->where);
        }
        
        $result = $this->connection->fetch($sql, $this->params);
        return (int) ($result['count'] ?? 0);
    }
    
    private function buildSelectSql(): string
    {
        $sql = "SELECT " . implode(', ', $this->select) . " FROM {$this->table}";
        
        if (!empty($this->where)) {
            $sql .= " WHERE " . implode(' AND ', $this->where);
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
}