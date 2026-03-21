<?php

declare(strict_types=1);

namespace Plugs\Database;

/*
|--------------------------------------------------------------------------
| Schema Class (Facade)
|--------------------------------------------------------------------------
|
| This class provides a static interface for managing database schemas.
| It acts as a facade to create, alter, and drop tables using the Blueprint
| class for fluent table definitions.
*/

class Schema
{
    private static $connection;
    private static $defaultConnection = 'default';
    private static array $schemaCache = [];

    /**
     * Set the database connection to use
     */
    public static function setConnection(Connection $connection): void
    {
        self::$connection = $connection;
    }

    /**
     * Get the database connection
     */
    private static function getConnection(): Connection
    {
        if (!self::$connection) {
            self::$connection = Connection::getInstance(null, self::$defaultConnection);
        }

        return self::$connection;
    }

    /**
     * Set the default connection name
     */
    public static function setDefaultConnection(string $name): void
    {
        self::$defaultConnection = $name;
    }

    /**
     * Create a new table
     */
    public static function create(string $table, callable $callback): void
    {
        $connection = self::getConnection();
        $blueprint = new Blueprint($connection, $table);

        $callback($blueprint);

        $blueprint->build();
    }

    /**
     * Modify an existing table
     */
    public static function table(string $table, callable $callback): void
    {
        $connection = self::getConnection();
        $blueprint = new Blueprint($connection, $table);

        $callback($blueprint);

        $blueprint->alter();
    }

    /**
     * Drop a table if it exists
     */
    public static function dropIfExists(string $table): void
    {
        $connection = self::getConnection();
        $sql = $connection->getGrammar()->compileDropTable($table, true);
        $connection->execute($sql);
    }

    /**
     * Drop a table
     */
    public static function drop(string $table): void
    {
        $connection = self::getConnection();
        $sql = $connection->getGrammar()->compileDropTable($table, false);
        $connection->execute($sql);
    }

    /**
     * Rename a table
     */
    public static function rename(string $from, string $to): void
    {
        $connection = self::getConnection();
        $sql = $connection->getGrammar()->compileRenameTable($from, $to);
        $connection->execute($sql);
    }

    /**
     * Check if a table exists
     */
    public static function hasTable(string $table): bool
    {
        $tables = self::getTables();

        return in_array($table, $tables);
    }

    /**
     * Check if a column exists in a table
     */
    public static function hasColumn(string $table, string $column): bool
    {
        $columns = self::getTableColumns($table);

        return in_array($column, $columns);
    }

    /**
     * Get all columns in a table
     */
    public static function getColumns(string $table): array
    {
        return self::getTableColumns($table, true);
    }

    /**
     * Get table columns with caching
     */
    protected static function getTableColumns(string $table, bool $full = false): array
    {
        $connection = self::getConnection();
        return $connection->getTableColumns($table); // Use the already dialect-aware Connection method
    }

    /**
     * Get all tables in the database
     */
    public static function getTables(): array
    {
        $connection = self::getConnection();
        $sql = $connection->getGrammar()->compileShowTables();
        $results = $connection->fetchAll($sql);

        $tables = [];
        foreach ($results as $result) {
            $tables[] = array_values($result)[0];
        }

        return $tables;
    }

    /**
     * Truncate a table
     */
    public static function truncate(string $table): void
    {
        $connection = self::getConnection();
        $sql = $connection->getGrammar()->compileTruncate($table);
        $connection->execute($sql);
    }

    /**
     * Disable foreign key checks
     */
    public static function disableForeignKeyConstraints(): void
    {
        $connection = self::getConnection();
        $connection->execute($connection->getGrammar()->compileForeignKeyConstraints(false));
    }

    /**
     * Enable foreign key checks
     */
    public static function enableForeignKeyConstraints(): void
    {
        $connection = self::getConnection();
        $connection->execute($connection->getGrammar()->compileForeignKeyConstraints(true));
    }

    /**
     * Execute a raw SQL statement
     */
    public static function raw(string $sql): void
    {
        $connection = self::getConnection();
        $connection->execute($sql);
    }

    /**
     * Get a blueprint instance for testing/inspection
     */
    public static function getBlueprint(string $table): Blueprint
    {
        $connection = self::getConnection();

        return new Blueprint($connection, $table);
    }
}
