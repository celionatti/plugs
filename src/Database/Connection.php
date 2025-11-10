<?php

declare(strict_types=1);

namespace Plugs\Database;

/*
|--------------------------------------------------------------------------
| Optimized Database Connection Class
|--------------------------------------------------------------------------
| Enhanced version with connection management, health checks, statement
| caching, and improved error handling for production environments.
|--------------------------------------------------------------------------
*/

use PDO;
use PDOException;

class Connection
{
    private static $instances = [];
    private static $statementCache = [];
    private static $config = [];

    private $pdo;
    private $connectionName;
    private $lastActivityTime;
    private $connectionAttempts = 0;
    private $maxRetries = 3;
    private $isHealthy = true;

    private function __construct(array $config, string $name = 'default')
    {
        $this->connectionName = $name;
        self::$config[$name] = $config;
        $this->connect($config);
    }

    private function connect(array $config): void
    {
        $driver = $config['driver'] ?? 'mysql';
        $this->connectionAttempts++;

        try {
            if ($driver === 'sqlite') {
                $dsn = "sqlite:{$config['database']}";
                $this->pdo = new PDO($dsn);
            } else {
                $dsn = $this->buildDsn($config);
                $options = $this->buildOptions($config);

                $this->pdo = new PDO(
                    $dsn,
                    $config['username'] ?? '',
                    $config['password'] ?? '',
                    $options
                );
            }

            $this->pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
            $this->pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
            $this->pdo->setAttribute(PDO::ATTR_EMULATE_PREPARES, false);

            $this->lastActivityTime = time();
            $this->isHealthy = true;
            $this->connectionAttempts = 0;
        } catch (PDOException $e) {
            $this->isHealthy = false;

            if ($this->connectionAttempts < $this->maxRetries) {
                usleep(100000 * $this->connectionAttempts); // Exponential backoff
                $this->connect($config);
                return;
            }

            throw new \RuntimeException(
                "Database connection failed after {$this->maxRetries} attempts: " . $e->getMessage()
            );
        }
    }

    private function buildOptions(array $config): array
    {
        $defaults = [
            PDO::ATTR_TIMEOUT => $config['timeout'] ?? 5,
            PDO::ATTR_PERSISTENT => $config['persistent'] ?? false,
        ];

        return array_merge($defaults, $config['options'] ?? []);
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
            $charset = $config['charset'] ?? 'utf8';
            return "pgsql:host={$host};port={$port};dbname={$database};options='--client_encoding={$charset}'";
        }

        throw new \InvalidArgumentException("Unsupported database driver: {$driver}");
    }

    /**
     * Get a database connection instance (singleton pattern with lazy loading)
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
        } else {
            // Check if connection is stale and reconnect if needed
            self::$instances[$connectionName]->ensureConnectionHealth();
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
        } else {
            self::$instances[$name]->ensureConnectionHealth();
        }

        return self::$instances[$name];
    }

    /**
     * Ensure connection is healthy and reconnect if needed
     */
    private function ensureConnectionHealth(): void
    {
        $maxIdleTime = self::$config[$this->connectionName]['max_idle_time'] ?? 3600;

        // Check if connection is stale
        if ((time() - $this->lastActivityTime) > $maxIdleTime) {
            $this->reconnect();
            return;
        }

        // Ping database to check if connection is alive
        if (!$this->ping()) {
            $this->reconnect();
        }
    }

    /**
     * Ping the database to check connection health
     * 
     * @return bool
     */
    public function ping(): bool
    {
        try {
            $this->pdo->query('SELECT 1');
            $this->lastActivityTime = time();
            return true;
        } catch (PDOException $e) {
            $this->isHealthy = false;
            return false;
        }
    }

    /**
     * Reconnect to the database
     */
    public function reconnect(): void
    {
        $this->disconnect();
        $this->connect(self::$config[$this->connectionName]);

        // Clear statement cache for this connection
        self::$statementCache[$this->connectionName] = [];
    }

    /**
     * Disconnect from database
     */
    public function disconnect(): void
    {
        $this->pdo = null;
        $this->isHealthy = false;
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
     * Check if connection is healthy
     * 
     * @return bool
     */
    public function isHealthy(): bool
    {
        return $this->isHealthy && $this->ping();
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

    /**
     * Close all active connections
     */
    public static function closeAll(): void
    {
        foreach (self::$instances as $instance) {
            $instance->disconnect();
        }

        self::$instances = [];
        self::$statementCache = [];
    }

    /**
     * Close specific connection
     * 
     * @param string $name Connection name
     */
    public static function close(string $name): void
    {
        if (isset(self::$instances[$name])) {
            self::$instances[$name]->disconnect();
            unset(self::$instances[$name]);
            unset(self::$statementCache[$name]);
        }
    }

    public function getPdo(): PDO
    {
        $this->ensureConnectionHealth();
        $this->lastActivityTime = time();
        return $this->pdo;
    }

    /**
     * Prepare a statement with caching
     * 
     * @param string $sql SQL query
     * @return \PDOStatement
     */
    private function prepareStatement(string $sql): \PDOStatement
    {
        $cacheKey = md5($sql);

        if (!isset(self::$statementCache[$this->connectionName][$cacheKey])) {
            self::$statementCache[$this->connectionName][$cacheKey] = $this->pdo->prepare($sql);
        }

        return self::$statementCache[$this->connectionName][$cacheKey];
    }

    /**
     * Execute a query with parameters
     * 
     * @param string $sql SQL query
     * @param array $params Query parameters
     * @return \PDOStatement
     */
    public function query(string $sql, array $params = []): \PDOStatement
    {
        $this->ensureConnectionHealth();
        $this->lastActivityTime = time();

        try {
            $stmt = $this->prepareStatement($sql);
            $stmt->execute($params);
            return $stmt;
        } catch (PDOException $e) {
            // Try to reconnect once on connection errors
            if ($this->isConnectionError($e)) {
                $this->reconnect();
                $stmt = $this->prepareStatement($sql);
                $stmt->execute($params);
                return $stmt;
            }

            throw $e;
        }
    }

    /**
     * Check if exception is a connection error
     * 
     * @param PDOException $e
     * @return bool
     */
    private function isConnectionError(PDOException $e): bool
    {
        $connectionErrors = [
            'HY000', // General error
            '2006',  // MySQL server has gone away
            '2013',  // Lost connection to MySQL server
            '08S01', // Communication link failure
        ];

        return in_array($e->getCode(), $connectionErrors, true);
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
        $this->ensureConnectionHealth();
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

    /**
     * Get connection statistics
     * 
     * @return array
     */
    public function getStats(): array
    {
        return [
            'connection_name' => $this->connectionName,
            'is_healthy' => $this->isHealthy,
            'last_activity' => $this->lastActivityTime,
            'idle_time' => time() - $this->lastActivityTime,
            'in_transaction' => $this->inTransaction(),
            'cached_statements' => count(self::$statementCache[$this->connectionName] ?? []),
        ];
    }

    /**
     * Clear statement cache for this connection
     */
    public function clearStatementCache(): void
    {
        self::$statementCache[$this->connectionName] = [];
    }

    /**
     * Clear all statement caches
     */
    public static function clearAllStatementCaches(): void
    {
        self::$statementCache = [];
    }
}
