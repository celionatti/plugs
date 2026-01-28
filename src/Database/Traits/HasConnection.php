<?php

declare(strict_types=1);

namespace Plugs\Database\Traits;

use Exception;
use PDO;
use PDOException;
use Plugs\Database\Connection;

trait HasConnection
{
    protected static $connection;
    protected static $connectionName = 'default';

    /**
     * Set connection using configuration array (original method)
     */
    public static function setConnection(array $config): void
    {
        static::$connection = $config;
    }

    /**
     * Get connection name
     */
    public function getConnectionName(): string
    {
        return static::$connectionName;
    }

    /**
     * Set the connection name for Connection class usage (new method)
     */
    public static function connection(string $name): void
    {
        static::$connectionName = $name;
    }

    protected static function getConnection(): PDO
    {
        if (static::$connection instanceof PDO) {
            return static::$connection;
        }

        if (is_array(static::$connection)) {
            $connection = Connection::getInstance(static::$connection, static::$connectionName);
            static::$connection = $connection->getPdo();

            return static::$connection;
        }

        $connection = Connection::getInstance(null, static::$connectionName);

        return $connection->getPdo();
    }

    /**
     * Get the PDO instance (enhanced)
     */
    public static function getPdo(): PDO
    {
        $connection = static::getConnection();

        if ($connection instanceof PDO) {
            return $connection;
        }

        // If using Connection class
        return $connection->getPdo();
    }
}
