<?php

declare(strict_types=1);

namespace Plugs\Database\Grammars;
 

class SqliteGrammar extends Grammar
{
    /**
     * Wrap a single value in double quotes.
     */
    protected function wrapValue(string $value): string
    {
        return '"' . str_replace('"', '""', $value) . '"';
    }

    public function compileWhereDate(string $column, string $operator, string $placeholder): string
    {
        return "strftime('%Y-%m-%d', {$column}) {$operator} {$placeholder}";
    }

    public function compileWhereMonth(string $column, string $operator, string $placeholder): string
    {
        return "strftime('%m', {$column}) {$operator} {$placeholder}";
    }

    public function compileWhereDay(string $column, string $operator, string $placeholder): string
    {
        return "strftime('%d', {$column}) {$operator} {$placeholder}";
    }

    public function compileWhereYear(string $column, string $operator, string $placeholder): string
    {
        return "strftime('%Y', {$column}) {$operator} {$placeholder}";
    }

    public function compileWhereTime(string $column, string $operator, string $placeholder): string
    {
        return "strftime('%H:%M:%S', {$column}) {$operator} {$placeholder}";
    }

    public function compileJsonContains(string $column, string $placeholder): string
    {
        // SQLite doesn't have a direct JSON_CONTAINS equivalent without extensions.
        // We'll use a basic LIKE as a fallback or throw if we want to be strict.
        return "{$column} LIKE '%' || {$placeholder} || '%'";
    }

    public function compileSavepoint(string $name): string
    {
        return "SAVEPOINT {$name}";
    }

    public function compileSavepointRelease(string $name): string
    {
        return "RELEASE SAVEPOINT {$name}";
    }

    public function compileSavepointRollback(string $name): string
    {
        return "ROLLBACK TO SAVEPOINT {$name}";
    }

    public function compileTableColumns(string $table): string
    {
        return "PRAGMA table_info(" . $this->wrapIdentifier($table) . ")";
    }

    public function compileDropTable(string $table, bool $ifExists = false): string
    {
        $prefix = $ifExists ? 'IF EXISTS ' : '';
        return "DROP TABLE {$prefix}" . $this->wrapIdentifier($table);
    }

    public function compileRenameTable(string $from, string $to): string
    {
        return "ALTER TABLE " . $this->wrapIdentifier($from) . " RENAME TO " . $this->wrapIdentifier($to);
    }

    public function compileTableExists(string $table): string
    {
        return "SELECT name FROM sqlite_master WHERE type='table' AND name='{$table}'";
    }

    public function compileTruncate(string $table): string
    {
        // SQLite doesn't have TRUNCATE, so we use DELETE
        return "DELETE FROM " . $this->wrapIdentifier($table);
    }

    public function compileForeignKeyConstraints(bool $enable): string
    {
        return "PRAGMA foreign_keys = " . ($enable ? 'ON' : 'OFF');
    }

    public function compileCreate(\Plugs\Database\Blueprint $blueprint): array
    {
        $columns = array_map([$this, 'compileColumn'], $blueprint->getColumns());
        $commands = array_filter(array_map(fn($cmd) => $this->compileCommand($blueprint, $cmd), $blueprint->getCommands()));

        $definitions = array_merge($columns, $commands);
        $temp = $blueprint->isTemporary() ? 'TEMPORARY ' : '';

        $sql = "CREATE {$temp}TABLE IF NOT EXISTS " . $this->wrapIdentifier($blueprint->getTable()) . " (\n  "
            . implode(",\n  ", $definitions)
            . "\n)";

        return [$sql];
    }

    public function compileAlter(\Plugs\Database\Blueprint $blueprint): array
    {
        $sql = [];

        foreach ($blueprint->getColumns() as $column) {
            $sql[] = "ALTER TABLE " . $this->wrapIdentifier($blueprint->getTable()) . " ADD COLUMN " . $this->compileColumn($column);
        }

        return array_filter($sql);
    }

    public function compileColumn(\Plugs\Database\ColumnDefinition $column): string
    {
        $sql = $this->wrapIdentifier($column->getName()) . " " . $this->getType($column);

        if ($column->primary) {
            $sql .= ' PRIMARY KEY';
            if ($column->autoIncrement) {
                $sql .= ' AUTOINCREMENT';
            }
        }

        $sql .= $column->nullable ? ' NULL' : ' NOT NULL';

        if ($column->hasDefault) {
            $sql .= " DEFAULT " . $this->formatDefault($column->default);
        } elseif ($column->useCurrent) {
            $sql .= ' DEFAULT CURRENT_TIMESTAMP';
        }

        if ($column->unique) {
            $sql .= ' UNIQUE';
        }

        return $sql;
    }

    protected function getType(\Plugs\Database\ColumnDefinition $column): string
    {
        $type = strtoupper($column->type);

        if ($column->autoIncrement && str_contains($type, 'INT')) {
            return 'INTEGER'; // SQLite requirement for autoincrement
        }

        return $type;
    }

    protected function formatDefault($value): string
    {
        if ($value === null) return 'NULL';
        if (is_bool($value)) return $value ? '1' : '0';
        if (is_numeric($value)) return (string)$value;
        return "'{$value}'";
    }

    protected function compileCommand(\Plugs\Database\Blueprint $blueprint, array $command): ?string
    {
        return null;
    }

    public function compileShowTables(): string
    {
        return "SELECT name FROM sqlite_master WHERE type='table'";
    }
}
