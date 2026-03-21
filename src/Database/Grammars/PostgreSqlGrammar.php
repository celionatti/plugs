<?php

declare(strict_types=1);

namespace Plugs\Database\Grammars;
 

class PostgreSqlGrammar extends Grammar
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
        return "{$column}::date {$operator} {$placeholder}";
    }

    public function compileWhereMonth(string $column, string $operator, string $placeholder): string
    {
        return "EXTRACT(MONTH FROM {$column}) {$operator} {$placeholder}";
    }

    public function compileWhereDay(string $column, string $operator, string $placeholder): string
    {
        return "EXTRACT(DAY FROM {$column}) {$operator} {$placeholder}";
    }

    public function compileWhereYear(string $column, string $operator, string $placeholder): string
    {
        return "EXTRACT(YEAR FROM {$column}) {$operator} {$placeholder}";
    }

    public function compileWhereTime(string $column, string $operator, string $placeholder): string
    {
        return "{$column}::time {$operator} {$placeholder}";
    }

    public function compileJsonContains(string $column, string $placeholder): string
    {
        return "{$column} @> {$placeholder}";
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
        return "SELECT column_name FROM information_schema.columns WHERE table_name = " . $this->wrapIdentifier($table);
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
        return "SELECT tablename FROM pg_catalog.pg_tables WHERE tablename = '{$table}'";
    }

    public function compileTruncate(string $table): string
    {
        return "TRUNCATE TABLE " . $this->wrapIdentifier($table) . " RESTART IDENTITY CASCADE";
    }

    public function compileForeignKeyConstraints(bool $enable): string
    {
        return "SET session_replication_role = " . ($enable ? "'origin'" : "'replica'");
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

        foreach ($blueprint->getCommands() as $command) {
            $sql[] = $this->compileAlterCommand($blueprint, $command);
        }

        return array_filter($sql);
    }

    public function compileColumn(\Plugs\Database\ColumnDefinition $column): string
    {
        $type = $this->getType($column);
        $sql = $this->wrapIdentifier($column->getName()) . " " . $type;

        $sql .= $column->nullable ? ' NULL' : ' NOT NULL';

        if ($column->hasDefault) {
            $sql .= " DEFAULT " . $this->formatDefault($column->default);
        } elseif ($column->useCurrent) {
            $sql .= ' DEFAULT CURRENT_TIMESTAMP';
        }

        return $sql;
    }

    protected function getType(\Plugs\Database\ColumnDefinition $column): string
    {
        $type = $column->type;

        if ($column->autoIncrement) {
            return str_contains(strtolower($type), 'bigint') ? 'BIGSERIAL' : 'SERIAL';
        }

        return $type;
    }

    protected function formatDefault($value): string
    {
        if ($value === null) return 'NULL';
        if (is_bool($value)) return $value ? 'true' : 'false';
        if (is_numeric($value)) return (string)$value;
        return "'{$value}'";
    }

    protected function compileCommand(\Plugs\Database\Blueprint $blueprint, array $command): ?string
    {
        return null;
    }

    protected function compileAlterCommand(\Plugs\Database\Blueprint $blueprint, array $command): ?string
    {
        return null;
    }

    public function compileShowTables(): string
    {
        return "SELECT tablename FROM pg_catalog.pg_tables WHERE schemaname = 'public'";
    }
}
