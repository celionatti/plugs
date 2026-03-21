<?php

declare(strict_types=1);

namespace Plugs\Database\Grammars;
 

class MySqlGrammar extends Grammar
{
    /**
     * Wrap a single value in backticks.
     */
    protected function wrapValue(string $value): string
    {
        return '`' . str_replace('`', '``', $value) . '`';
    }

    public function compileWhereDate(string $column, string $operator, string $placeholder): string
    {
        return "DATE({$column}) {$operator} {$placeholder}";
    }

    public function compileWhereMonth(string $column, string $operator, string $placeholder): string
    {
        return "MONTH({$column}) {$operator} {$placeholder}";
    }

    public function compileWhereDay(string $column, string $operator, string $placeholder): string
    {
        return "DAY({$column}) {$operator} {$placeholder}";
    }

    public function compileWhereYear(string $column, string $operator, string $placeholder): string
    {
        return "YEAR({$column}) {$operator} {$placeholder}";
    }

    public function compileWhereTime(string $column, string $operator, string $placeholder): string
    {
        return "TIME({$column}) {$operator} {$placeholder}";
    }

    public function compileJsonContains(string $column, string $placeholder): string
    {
        return "JSON_CONTAINS({$column}, {$placeholder})";
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
        return "DESCRIBE " . $this->wrapIdentifier($table);
    }

    public function compileDropTable(string $table, bool $ifExists = false): string
    {
        $prefix = $ifExists ? 'IF EXISTS ' : '';
        return "DROP TABLE {$prefix}" . $this->wrapIdentifier($table);
    }

    public function compileRenameTable(string $from, string $to): string
    {
        return "RENAME TABLE " . $this->wrapIdentifier($from) . " TO " . $this->wrapIdentifier($to);
    }

    public function compileTableExists(string $table): string
    {
        return "SHOW TABLES LIKE '{$table}'";
    }

    public function compileTruncate(string $table): string
    {
        return "TRUNCATE TABLE " . $this->wrapIdentifier($table);
    }

    public function compileForeignKeyConstraints(bool $enable): string
    {
        return "SET FOREIGN_KEY_CHECKS=" . ($enable ? '1' : '0');
    }

    public function compileCreate(\Plugs\Database\Blueprint $blueprint): array
    {
        $columns = array_map([$this, 'compileColumn'], $blueprint->getColumns());
        $commands = array_filter(array_map(fn($cmd) => $this->compileCommand($blueprint, $cmd), $blueprint->getCommands()));

        $definitions = array_merge($columns, $commands);
        $temp = $blueprint->isTemporary() ? 'TEMPORARY ' : '';

        $sql = "CREATE {$temp}TABLE IF NOT EXISTS " . $this->wrapIdentifier($blueprint->getTable()) . " (\n  "
            . implode(",\n  ", $definitions)
            . "\n) ENGINE=" . $blueprint->getEngine() . " DEFAULT CHARSET=" . $blueprint->getCharset() . " COLLATE=" . $blueprint->getCollation();

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
        $sql = $this->wrapIdentifier($column->getName()) . " " . $column->type;

        if ($column->unsigned) {
            $sql .= ' UNSIGNED';
        }

        if ($column->charset) {
            $sql .= " CHARACTER SET {$column->charset}";
        }

        if ($column->collation) {
            $sql .= " COLLATE {$column->collation}";
        }

        $sql .= $column->nullable ? ' NULL' : ' NOT NULL';

        if ($column->hasDefault) {
            $sql .= " DEFAULT " . $this->formatDefault($column->default);
        } elseif ($column->useCurrent) {
            $sql .= ' DEFAULT CURRENT_TIMESTAMP';
        }

        if ($column->useCurrentOnUpdate) {
            $sql .= ' ON UPDATE CURRENT_TIMESTAMP';
        }

        if ($column->autoIncrement) {
            $sql .= ' AUTO_INCREMENT';
        }

        if ($column->primary) {
            $sql .= ' PRIMARY KEY';
        }

        if ($column->unique) {
            $sql .= ' UNIQUE';
        }

        if ($column->comment) {
            $sql .= " COMMENT '{$column->comment}'";
        }

        return $sql;
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
        // Handle indexes, foreign keys, etc.
        // Simplified for now, just to show the structure
        return null; 
    }

    protected function compileAlterCommand(\Plugs\Database\Blueprint $blueprint, array $command): ?string
    {
        return null;
    }

    public function compileShowTables(): string
    {
        return "SHOW TABLES";
    }
}
