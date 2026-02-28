<?php

declare(strict_types=1);

if (!function_exists('db')) {
    /**
     * Get the database manager or a connection/table instance.
     *
     * @param string|null $table
     * @param string|null $connection
     * @return \Plugs\Database\DatabaseManager|\Plugs\Database\QueryBuilder
     */
    function db(?string $table = null, ?string $connection = null)
    {
        $database = app('database');

        if ($table === null) {
            return $database;
        }

        return $database->table($table, $connection);
    }
}
