<?php

declare(strict_types=1);

namespace Plugs\Database\Traits;

use Exception;
use PDO;
use PDOException;
use Plugs\Database\Connection;

/**
 * @phpstan-ignore trait.unused
 */
trait HasConnection
{
    protected static $storedConnection;
    protected static $connectionName = 'default';

    /**
     * Set connection using configuration array (original method)
     */
    public static function setConnection(array $config): void
    {
        static::$storedConnection = $config;
    }

    /**
     * Get connection name
     */
    public function getConnectionName(): string
    {
        if (isset($this->connection) && is_string($this->connection)) {
            return $this->connection;
        }

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
        if (static::$storedConnection instanceof PDO) {
            return static::$storedConnection;
        }

        if (is_array(static::$storedConnection)) {
            $connection = Connection::getInstance(static::$storedConnection, static::$connectionName);
            static::$storedConnection = $connection->getPdo();

            return static::$storedConnection;
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

        /** @phpstan-ignore-next-line */
        if ($connection instanceof PDO) {
            return $connection;
        }

        // If using Connection class
        /** @phpstan-ignore-next-line */
        return $connection->getPdo();
    }
}
