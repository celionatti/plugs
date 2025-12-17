<?php

declare(strict_types=1);

namespace Plugs\Database\Traits;

use PDO;
use Exception;
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
        $driver = $config['driver'] ?? 'mysql';
        $host = $config['host'] ?? 'localhost';
        $port = $config['port'] ?? 3306;
        $database = $config['database'] ?? '';
        $username = $config['username'] ?? 'root';
        $password = $config['password'] ?? '';
        $charset = $config['charset'] ?? 'utf8mb4';
        $options = $config['options'] ?? [];

        $defaultOptions = [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES => false,
        ];

        $pdoOptions = array_merge($defaultOptions, $options);

        try {
            switch ($driver) {
                case 'mysql':
                    $dsn = "mysql:host={$host};port={$port};dbname={$database};charset={$charset}";
                    break;
                case 'pgsql':
                    $dsn = "pgsql:host={$host};port={$port};dbname={$database}";
                    break;
                case 'sqlite':
                    $dsn = "sqlite:{$database}";
                    break;
                case 'sqlsrv':
                    $dsn = "sqlsrv:Server={$host},{$port};Database={$database}";
                    break;
                default:
                    throw new Exception("Unsupported database driver: {$driver}");
            }

            static::$connection = new PDO($dsn, $username, $password, $pdoOptions);
        } catch (PDOException $e) {
            throw new Exception("Database connection failed: " . $e->getMessage());
        }
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

        $connection = Connection::getInstance();
        return $connection instanceof PDO ? $connection : $connection->getPdo();
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
