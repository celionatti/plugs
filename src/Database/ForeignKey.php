<?php

declare(strict_types=1);

namespace Plugs\Database;

/*
|--------------------------------------------------------------------------
| Foreign Key Class
|--------------------------------------------------------------------------
|
| This class provides a fluent interface for defining foreign key
| constraints in database schemas. It allows you to specify the
| referenced table and column, as well as actions on delete and update.
*/

class ForeignKey
{
    private $schema;
    private $column;
    private $references;
    private $on;
    private $onDelete = 'RESTRICT';
    private $onUpdate = 'RESTRICT';

    public function __construct(Schema $schema, string $column)
    {
        $this->schema = $schema;
        $this->column = $column;
    }

    public function references(string $column): self
    {
        $this->references = $column;

        return $this;
    }

    public function on(string $table): Schema
    {
        $this->on = $table;
        $this->build();

        return $this->schema;
    }

    public function onDelete(string $action): self
    {
        $this->onDelete = $action;

        return $this;
    }

    public function onUpdate(string $action): self
    {
        $this->onUpdate = $action;

        return $this;
    }

    public function cascadeOnDelete(): self
    {
        return $this->onDelete('CASCADE');
    }

    public function nullOnDelete(): self
    {
        return $this->onDelete('SET NULL');
    }

    private function build(): void
    {
        $constraint = "FOREIGN KEY ({$this->column}) REFERENCES {$this->on}({$this->references})";
        $constraint .= " ON DELETE {$this->onDelete} ON UPDATE {$this->onUpdate}";

        $this->schema->addForeignKey($constraint);
    }
}
