<?php

declare(strict_types=1);

namespace Plugs\Database;

/*
|--------------------------------------------------------------------------
| Schema Class
|--------------------------------------------------------------------------
|
| This class provides a fluent interface for building and managing
| database schemas. It supports creating tables, adding columns,
| defining indexes, and setting up foreign key relationships.
*/

class Schema
{
    private $connection;
    private $table;
    private $columns = [];
    private $indexes = [];
    private $foreignKeys = [];
    
    public function __construct(Connection $connection, string $table)
    {
        $this->connection = $connection;
        $this->table = $table;
    }
    
    public function id(string $name = 'id'): self
    {
        $this->columns[] = "{$name} INT AUTO_INCREMENT PRIMARY KEY";
        return $this;
    }
    
    public function bigId(string $name = 'id'): self
    {
        $this->columns[] = "{$name} BIGINT AUTO_INCREMENT PRIMARY KEY";
        return $this;
    }
    
    public function string(string $name, int $length = 255): self
    {
        $this->columns[] = "{$name} VARCHAR({$length})";
        return $this;
    }
    
    public function text(string $name): self
    {
        $this->columns[] = "{$name} TEXT";
        return $this;
    }
    
    public function longText(string $name): self
    {
        $this->columns[] = "{$name} LONGTEXT";
        return $this;
    }
    
    public function integer(string $name): self
    {
        $this->columns[] = "{$name} INT";
        return $this;
    }
    
    public function bigInteger(string $name): self
    {
        $this->columns[] = "{$name} BIGINT";
        return $this;
    }
    
    public function decimal(string $name, int $precision = 8, int $scale = 2): self
    {
        $this->columns[] = "{$name} DECIMAL({$precision}, {$scale})";
        return $this;
    }
    
    public function boolean(string $name): self
    {
        $this->columns[] = "{$name} TINYINT(1)";
        return $this;
    }
    
    public function date(string $name): self
    {
        $this->columns[] = "{$name} DATE";
        return $this;
    }
    
    public function datetime(string $name): self
    {
        $this->columns[] = "{$name} DATETIME";
        return $this;
    }
    
    public function timestamp(string $name): self
    {
        $this->columns[] = "{$name} TIMESTAMP";
        return $this;
    }
    
    public function timestamps(): self
    {
        $this->columns[] = "created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP";
        $this->columns[] = "updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP";
        return $this;
    }
    
    public function nullable(): self
    {
        $lastIndex = count($this->columns) - 1;
        $this->columns[$lastIndex] .= " NULL";
        return $this;
    }
    
    public function notNullable(): self
    {
        $lastIndex = count($this->columns) - 1;
        $this->columns[$lastIndex] .= " NOT NULL";
        return $this;
    }
    
    public function default($value): self
    {
        $lastIndex = count($this->columns) - 1;
        if (is_string($value)) {
            $this->columns[$lastIndex] .= " DEFAULT '{$value}'";
        } else {
            $this->columns[$lastIndex] .= " DEFAULT {$value}";
        }
        return $this;
    }
    
    public function unique(string|null $column = null): self
    {
        if ($column) {
            $this->indexes[] = "UNIQUE KEY unique_{$column} ({$column})";
        } else {
            $lastIndex = count($this->columns) - 1;
            $this->columns[$lastIndex] .= " UNIQUE";
        }
        return $this;
    }
    
    public function index(string $column): self
    {
        $this->indexes[] = "INDEX idx_{$column} ({$column})";
        return $this;
    }
    
    public function foreign(string $column): ForeignKey
    {
        return new ForeignKey($this, $column);
    }
    
    public function addForeignKey(string $definition): void
    {
        $this->foreignKeys[] = $definition;
    }
    
    public function create(): void
    {
        $definitions = array_merge($this->columns, $this->indexes, $this->foreignKeys);
        $sql = "CREATE TABLE IF NOT EXISTS {$this->table} (\n    " 
            . implode(",\n    ", $definitions) 
            . "\n) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";
        
        $this->connection->execute($sql);
    }
}