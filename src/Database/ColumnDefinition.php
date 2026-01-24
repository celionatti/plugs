<?php

declare(strict_types=1);

namespace Plugs\Database;

/*
|--------------------------------------------------------------------------
| Column Definition Class
|--------------------------------------------------------------------------
|
| This class represents a single column definition and provides fluent
| modifiers for configuring column properties.
*/

class ColumnDefinition
{
    private $name;
    private $type;
    private $nullable = false;
    private $default = null;
    private $hasDefault = false;
    private $unsigned = false;
    private $autoIncrement = false;
    private $primary = false;
    private $unique = false;
    private $index = false;
    private $comment = null;
    private $after = null;
    private $first = false;
    private $charset = null;
    private $collation = null;
    private $useCurrent = false;
    private $useCurrentOnUpdate = false;
    private $onUpdate = null;
    private $onDelete = null;
    private $virtualAs = null;
    private $storedAs = null;

    public function __construct(string $name, string $type)
    {
        $this->name = $name;
        $this->type = $type;
    }

    /**
     * Make the column nullable
     */
    public function nullable(bool $value = true): self
    {
        $this->nullable = $value;

        return $this;
    }

    /**
     * Set a default value for the column
     */
    public function default($value): self
    {
        $this->default = $value;
        $this->hasDefault = true;

        return $this;
    }

    /**
     * Make the column unsigned
     */
    public function unsigned(): self
    {
        $this->unsigned = true;

        return $this;
    }

    /**
     * Make the column auto-incrementing
     */
    public function autoIncrement(): self
    {
        $this->autoIncrement = true;

        return $this;
    }

    /**
     * Set the column as primary key
     */
    public function primary(): self
    {
        $this->primary = true;

        return $this;
    }

    /**
     * Add a unique constraint to the column
     */
    public function unique(): self
    {
        $this->unique = true;

        return $this;
    }

    /**
     * Add an index to the column
     */
    public function index(): self
    {
        $this->index = true;

        return $this;
    }

    /**
     * Add a comment to the column
     */
    public function comment(string $comment): self
    {
        $this->comment = $comment;

        return $this;
    }

    /**
     * Place the column after another column
     */
    public function after(string $column): self
    {
        $this->after = $column;

        return $this;
    }

    /**
     * Place the column first in the table
     */
    public function first(): self
    {
        $this->first = true;

        return $this;
    }

    /**
     * Set the character set for the column
     */
    public function charset(string $charset): self
    {
        $this->charset = $charset;

        return $this;
    }

    /**
     * Set the collation for the column
     */
    public function collation(string $collation): self
    {
        $this->collation = $collation;

        return $this;
    }

    /**
     * Set the column to use CURRENT_TIMESTAMP as default
     */
    public function useCurrent(): self
    {
        $this->useCurrent = true;

        return $this;
    }

    /**
     * Set the column to use CURRENT_TIMESTAMP on update
     */
    public function useCurrentOnUpdate(): self
    {
        $this->useCurrentOnUpdate = true;

        return $this;
    }

    /**
     * Create a virtual generated column
     */
    public function virtualAs(string $expression): self
    {
        $this->virtualAs = $expression;

        return $this;
    }

    /**
     * Create a stored generated column
     */
    public function storedAs(string $expression): self
    {
        $this->storedAs = $expression;

        return $this;
    }

    /**
     * Build the SQL for this column definition
     */
    public function toSql(): string
    {
        $sql = "`{$this->name}` {$this->type}";

        // Add UNSIGNED modifier
        if ($this->unsigned) {
            $sql .= ' UNSIGNED';
        }

        // Add character set and collation
        if ($this->charset) {
            $sql .= " CHARACTER SET {$this->charset}";
        }
        if ($this->collation) {
            $sql .= " COLLATE {$this->collation}";
        }

        // Add generated column expression
        if ($this->virtualAs) {
            $sql .= " AS ({$this->virtualAs}) VIRTUAL";
        } elseif ($this->storedAs) {
            $sql .= " AS ({$this->storedAs}) STORED";
        }

        // Add NULL/NOT NULL
        if ($this->nullable) {
            $sql .= ' NULL';
        } else {
            $sql .= ' NOT NULL';
        }

        // Add DEFAULT value
        if ($this->hasDefault) {
            if ($this->default === null) {
                $sql .= ' DEFAULT NULL';
            } elseif (is_bool($this->default)) {
                $sql .= ' DEFAULT ' . ($this->default ? '1' : '0');
            } elseif (is_numeric($this->default)) {
                $sql .= ' DEFAULT ' . $this->default;
            } else {
                $sql .= " DEFAULT '{$this->default}'";
            }
        } elseif ($this->useCurrent) {
            $sql .= ' DEFAULT CURRENT_TIMESTAMP';
        }

        // Add ON UPDATE for timestamps
        if ($this->useCurrentOnUpdate) {
            $sql .= ' ON UPDATE CURRENT_TIMESTAMP';
        }

        // Add AUTO_INCREMENT
        if ($this->autoIncrement) {
            $sql .= ' AUTO_INCREMENT';
        }

        // Add PRIMARY KEY (inline)
        if ($this->primary) {
            $sql .= ' PRIMARY KEY';
        }

        // Add UNIQUE
        if ($this->unique) {
            $sql .= ' UNIQUE';
        }

        // Add COMMENT
        if ($this->comment) {
            $sql .= " COMMENT '{$this->comment}'";
        }

        // Add AFTER or FIRST (for ALTER TABLE)
        if ($this->first) {
            $sql .= ' FIRST';
        } elseif ($this->after) {
            $sql .= " AFTER `{$this->after}`";
        }

        return $sql;
    }

    /**
     * Get the column name
     */
    public function getName(): string
    {
        return $this->name;
    }

    /**
     * Check if column has an index
     */
    public function hasIndex(): bool
    {
        return $this->index;
    }

    /**
     * Check if column is primary
     */
    public function isPrimary(): bool
    {
        return $this->primary;
    }

    /**
     * Check if column is unique
     */
    public function isUnique(): bool
    {
        return $this->unique;
    }
}
