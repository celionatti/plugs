<?php

declare(strict_types=1);

namespace Plugs\Database;

/*
|--------------------------------------------------------------------------
| Foreign Key Definition Class
|--------------------------------------------------------------------------
|
| This class provides a fluent interface for defining foreign key
| constraints with reference tables, columns, and actions.
*/

class ForeignKeyDefinition
{
    private $columns;
    private $name;
    private $referenceTable;
    private $referenceColumns = [];
    private $onDelete = 'RESTRICT';
    private $onUpdate = 'RESTRICT';

    public function __construct(array $columns, string $name)
    {
        $this->columns = $columns;
        $this->name = $name;
    }

    /**
     * Set the referenced table and columns
     */
    public function references(string|array $columns): self
    {
        $this->referenceColumns = is_array($columns) ? $columns : [$columns];
        return $this;
    }

    /**
     * Set the referenced table
     */
    public function on(string $table): self
    {
        $this->referenceTable = $table;
        return $this;
    }

    /**
     * Set the ON DELETE action
     */
    public function onDelete(string $action): self
    {
        $this->onDelete = strtoupper($action);
        return $this;
    }

    /**
     * Set the ON UPDATE action
     */
    public function onUpdate(string $action): self
    {
        $this->onUpdate = strtoupper($action);
        return $this;
    }

    /**
     * Set CASCADE on delete
     */
    public function cascadeOnDelete(): self
    {
        return $this->onDelete('CASCADE');
    }

    /**
     * Set CASCADE on update
     */
    public function cascadeOnUpdate(): self
    {
        return $this->onUpdate('CASCADE');
    }

    /**
     * Set NULL on delete
     */
    public function nullOnDelete(): self
    {
        return $this->onDelete('SET NULL');
    }

    /**
     * Set RESTRICT on delete
     */
    public function restrictOnDelete(): self
    {
        return $this->onDelete('RESTRICT');
    }

    /**
     * Set NO ACTION on delete
     */
    public function noActionOnDelete(): self
    {
        return $this->onDelete('NO ACTION');
    }

    /**
     * Build the SQL for this foreign key
     */
    public function toSql(): string
    {
        $columns = implode('`, `', $this->columns);
        $refColumns = implode('`, `', $this->referenceColumns);

        $sql = "CONSTRAINT `{$this->name}` FOREIGN KEY (`{$columns}`) ";
        $sql .= "REFERENCES `{$this->referenceTable}` (`{$refColumns}`)";

        if ($this->onDelete) {
            $sql .= " ON DELETE {$this->onDelete}";
        }

        if ($this->onUpdate) {
            $sql .= " ON UPDATE {$this->onUpdate}";
        }

        return $sql;
    }

    /**
     * Get the constraint name
     */
    public function getName(): string
    {
        return $this->name;
    }
}
