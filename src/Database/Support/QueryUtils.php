<?php

declare(strict_types=1);

namespace Plugs\Database\Support;

/**
 * Class QueryUtils
 * 
 * Helper utilities for building SQL queries, specifically focusing on security
 * and identifier quoting.
 */
class QueryUtils
{
    /**
     * Wrap a column or table name in the appropriate quotes for the current connection.
     * Currently supports MySQL (backticks) and ANSI SQL (double quotes).
     */
    public static function wrapIdentifier(string $value, string $driver = 'mysql'): string
    {
        if ($value === '*') {
            return $value;
        }

        // Split by dots for table.column notation
        if (str_contains($value, '.')) {
            $parts = explode('.', $value);
            return implode('.', array_map(fn($part) => self::wrapIdentifier($part, $driver), $parts));
        }

        // MySQL uses backticks
        if ($driver === 'mysql') {
            return '`' . str_replace('`', '``', $value) . '`';
        }

        // ANSI SQL uses double quotes
        return '"' . str_replace('"', '""', $value) . '"';
    }

    /**
     * Sanitize a column name to prevent basic injection.
     * Only allows alphanumeric, underscores, and dots.
     */
    public static function sanitizeColumn(string $column): string
    {
        if (!preg_match('/^[a-zA-Z0-9_\.\*]+$/', $column)) {
            throw new \Exception("Invalid column name: {$column}");
        }

        return $column;
    }

    /**
     * Check if a string is a raw SQL expression.
     */
    public static function isRaw($value): bool
    {
        return $value instanceof \Plugs\Database\Raw;
    }
}
