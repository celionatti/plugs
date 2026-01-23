<?php

declare(strict_types=1);

namespace Plugs\Database;

/*
|--------------------------------------------------------------------------
| Database Manager
|--------------------------------------------------------------------------
|
| This class manages database connections and provides a fluent entry point
| for the query builder. It serves as the bridge between the connection
| and the query builder.
*/

class DatabaseManager
{
    /**
     * The database connection instance.
     *
     * @var Connection
     */
    protected $connection;

    /**
     * Create a new database manager instance.
     *
     * @param Connection $connection
     */
    public function __construct(Connection $connection)
    {
        $this->connection = $connection;
    }

    /**
     * Begin a fluent query against a database table.
     *
     * @param string $table
     * @param string|null $connection
     * @return QueryBuilder
     */
    public function table(string $table, ?string $connection = null): QueryBuilder
    {
        $conn = $connection ? Connection::connection($connection) : $this->connection;
        return (new QueryBuilder($conn))->table($table);
    }

    /**
     * Get the connection instance.
     *
     * @return Connection
     */
    public function getConnection(): Connection
    {
        return $this->connection;
    }

    /**
     * Pass dynamic methods to the connection instance.
     *
     * @param string $method
     * @param array $parameters
     * @return mixed
     */
    public function __call(string $method, array $parameters)
    {
        return $this->connection->$method(...$parameters);
    }
}
