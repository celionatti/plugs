<?php

declare(strict_types=1);

namespace Plugs\Database\Grammars;

use Plugs\Database\Support\QueryUtils;

abstract class Grammar
{
    /**
     * Wrap a value in keyword identifiers.
     */
    public function wrapIdentifier(string $value): string
    {
        if ($value === '*') {
            return $value;
        }

        // Handle AS keyword
        if (stripos($value, ' as ') !== false) {
            $parts = preg_split('/ as /i', $value);
            return $this->wrapIdentifier(trim($parts[0])) . ' AS ' . $this->wrapIdentifier(trim($parts[1]));
        }

        // Split by dots for table.column notation
        if (str_contains($value, '.')) {
            $parts = explode('.', $value);
            return implode('.', array_map(fn($part) => $this->wrapIdentifier($part), $parts));
        }

        return $this->wrapValue($value);
    }

    /**
     * Wrap a single value in keyword identifiers.
     */
    protected abstract function wrapValue(string $value): string;

    /**
     * Compile a "where date" clause.
     */
    public abstract function compileWhereDate(string $column, string $operator, string $placeholder): string;

    /**
     * Compile a "where month" clause.
     */
    public abstract function compileWhereMonth(string $column, string $operator, string $placeholder): string;

    /**
     * Compile a "where day" clause.
     */
    public abstract function compileWhereDay(string $column, string $operator, string $placeholder): string;

    /**
     * Compile a "where year" clause.
     */
    public abstract function compileWhereYear(string $column, string $operator, string $placeholder): string;

    /**
     * Compile a "where time" clause.
     */
    public abstract function compileWhereTime(string $column, string $operator, string $placeholder): string;

    /**
     * Compile a "json contains" clause.
     */
    public abstract function compileJsonContains(string $column, string $placeholder): string;

    /**
     * Compile the "limit" and "offset" clauses.
     */
    public function compileLimitOffset(?int $limit, ?int $offset): string
    {
        $sql = '';

        if ($limit !== null) {
            $sql .= " LIMIT {$limit}";
        }

        if ($offset !== null) {
            $sql .= " OFFSET {$offset}";
        }

        return $sql;
    }

    /**
     * Compile a savepoint command.
     */
    public abstract function compileSavepoint(string $name): string;

    /**
     * Compile a savepoint release command.
     */
    public abstract function compileSavepointRelease(string $name): string;

    /**
     * Compile a savepoint rollback command.
     */
    public abstract function compileSavepointRollback(string $name): string;

    /**
     * Compile a query to get table columns.
     */
    public abstract function compileTableColumns(string $table): string;

    /**
     * Compile a "drop table" command.
     */
    public abstract function compileDropTable(string $table, bool $ifExists = false): string;

    /**
     * Compile a table rename command.
     */
    public abstract function compileRenameTable(string $from, string $to): string;

    /**
     * Compile a "has table" query.
     */
    public abstract function compileTableExists(string $table): string;

    /**
     * Compile a truncation command.
     */
    public abstract function compileTruncate(string $table): string;

    /**
     * Compile a "foreign key checks" toggle.
     */
    public abstract function compileForeignKeyConstraints(bool $enable): string;

    /**
     * Compile a create table command.
     */
    public abstract function compileCreate(\Plugs\Database\Blueprint $blueprint): array;

    /**
     * Compile an alter table command.
     */
    public abstract function compileAlter(\Plugs\Database\Blueprint $blueprint): array;

    /**
     * Compile a column definition.
     */
    public abstract function compileColumn(\Plugs\Database\ColumnDefinition $column): string;

    /**
     * Compile a "show tables" query.
     */
    public abstract function compileShowTables(): string;
}
