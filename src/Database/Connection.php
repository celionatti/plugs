<?php

declare(strict_types=1);

namespace Plugs\Database;

/*
|--------------------------------------------------------------------------
| Database Connection Class
|--------------------------------------------------------------------------
|
| This class manages database connections using PDO. It supports multiple
| database types (MySQL, PostgreSQL, SQLite) and allows for easy querying
| and transaction management.
*/

use PDO;
use PDOException;

class Connection
{
    private static $instances = [];
    private $pdo;
    private $connectionName;

    private function __construct(array $config, string $name = 'default')
    {
        $this->connectionName = $name;
        $driver = $config['driver'] ?? 'mysql';

        try {
            if ($driver === 'sqlite') {
                $dsn = "sqlite:{$config['database']}";
                $this->pdo = new PDO($dsn);
            } else {
                $dsn = $this->buildDsn($config);
                $this->pdo = new PDO(
                    $dsn,
                    $config['username'] ?? '',
                    $config['password'] ?? '',
                    $config['options'] ?? []
                );
            }

            $this->pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
            $this->pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
            $this->pdo->setAttribute(PDO::ATTR_EMULATE_PREPARES, false);
        } catch (PDOException $e) {
            throw new \RuntimeException("Database connection failed: " . $e->getMessage());
        }
    }

    private function buildDsn(array $config): string
    {
        $driver = $config['driver'];
        $host = $config['host'];
        $port = $config['port'];
        $database = $config['database'];

        if ($driver === 'mysql') {
            $charset = $config['charset'] ?? 'utf8mb4';
            return "mysql:host={$host};port={$port};dbname={$database};charset={$charset}";
        }

        if ($driver === 'pgsql') {
            return "pgsql:host={$host};port={$port};dbname={$database}";
        }

        throw new \InvalidArgumentException("Unsupported database driver: {$driver}");
    }

    /**
     * Get a database connection instance
     * 
     * @param array|null $config Configuration array or null to use default
     * @param string $connectionName Name of the connection (for multiple connections)
     * @return self
     */
    public static function getInstance(array|null $config = null, string $connectionName = 'default'): self
    {
        if (!isset(self::$instances[$connectionName])) {
            if ($config === null) {
                $dbConfig = require BASE_PATH . 'config/database.php';
                $config = $dbConfig['connections'][$dbConfig['default']];
            }

            self::$instances[$connectionName] = new self($config, $connectionName);
        }

        return self::$instances[$connectionName];
    }

    /**
     * Get connection by name from config
     * 
     * @param string $name Connection name from config (mysql, pgsql, sqlite)
     * @return self
     */
    public static function connection(string $name): self
    {
        if (!isset(self::$instances[$name])) {
            $dbConfig = require BASE_PATH . 'config/database.php';

            if (!isset($dbConfig['connections'][$name])) {
                throw new \InvalidArgumentException("Connection [{$name}] not configured.");
            }

            self::$instances[$name] = new self($dbConfig['connections'][$name], $name);
        }

        return self::$instances[$name];
    }

    /**
     * Get the current connection name
     * 
     * @return string
     */
    public function getConnectionName(): string
    {
        return $this->connectionName;
    }

    /**
     * Get all active connections
     * 
     * @return array
     */
    public static function getActiveConnections(): array
    {
        return array_keys(self::$instances);
    }

    public function getPdo(): PDO
    {
        return $this->pdo;
    }

    public function query(string $sql, array $params = []): \PDOStatement
    {
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        return $stmt;
    }

    public function fetch(string $sql, array $params = []): ?array
    {
        $stmt = $this->query($sql, $params);
        $result = $stmt->fetch();
        return $result ?: null;
    }

    public function fetchAll(string $sql, array $params = []): array
    {
        $stmt = $this->query($sql, $params);
        return $stmt->fetchAll();
    }

    public function execute(string $sql, array $params = []): bool
    {
        $stmt = $this->query($sql, $params);
        return $stmt->rowCount() > 0;
    }

    public function lastInsertId(): string
    {
        return $this->pdo->lastInsertId();
    }

    public function beginTransaction(): bool
    {
        return $this->pdo->beginTransaction();
    }

    public function commit(): bool
    {
        return $this->pdo->commit();
    }

    public function rollBack(): bool
    {
        return $this->pdo->rollBack();
    }

    public function inTransaction(): bool
    {
        return $this->pdo->inTransaction();
    }
}