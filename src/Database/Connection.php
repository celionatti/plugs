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
use Plugs\Exceptions\ConfigurationException;
use Plugs\Exceptions\DatabaseException as PlugsDatabaseException;
use Plugs\Database\Pooling\ConnectionPool;
use Plugs\Database\Analysis\QueryAnalyzer;
use Plugs\Database\Guard\QueryGuard;

class Connection
{
    private static array $instances = [];
    private static array $config = [];

    // Connection Pool Management
    private static array $connectionPools = [];
    private static array $poolConfig = [
        'min_connections' => 2,      // Minimum connections to keep alive
        'max_connections' => 10,     // Maximum connections allowed
        'connection_timeout' => 30,  // Timeout for acquiring connection (seconds)
        'idle_timeout' => 300,       // How long a connection can be idle (seconds)
        'validate_on_checkout' => true, // Validate connection before returning
        'persistent' => null,        // null = auto (true when pooled), true/false = force
    ];

    // Runtime environment detection cache
    private static ?string $detectedRuntime = null;
    private static array $poolLocks = [];

    // Load Balancer instances per connection name
    private static array $loadBalancers = [];

    // Prepared Statement Pool
    private static array $statementPool = [];
    private static int $statementPoolSize = 100; // Max cached statements per connection

    // Query Analysis
    private static array $queryStats = [];
    private static bool $enableQueryAnalysis = false;
    private static array $queryAnalysisThresholds = [
        'slow_query_time' => 1.0,    // Seconds
        'n_plus_one_threshold' => 10, // Similar queries in a row
    ];

    private ?PDO $pdo = null;
    private ?PDO $readPdo = null;
    private string $connectionName;
    private int $lastActivityTime = 0;
    private bool $isHealthy = true;
    private bool $isInPool = false;
    private ?string $poolId = null;
    /** @phpstan-ignore property.onlyWritten */
    private int $connectionAttempts = 0;
    private int $maxRetries = 3;

    private ?\Plugs\Database\Optimization\OptimizationManager $optimizationManager = null;

    // Security & Advanced Features
    private bool $sticky = false;
    private float $lastWriteTimestamp = 0;
    private static string $auditLogPath = 'storage/logs/security_audit.log';
    private bool $isConnecting = false;
    private int $lastHealthCheckAt = 0;
    private int $lastReadHealthCheckAt = 0;
    private bool $strictMode = false;
    private static array $schemaCache = [];

    /**
     * The number of active transactions.
     *
     * @var int
     */
    protected $transactions = 0;

    private function __construct(array $config, string $name = 'default')
    {
        $this->connectionName = $name;
        $this->poolId = bin2hex(random_bytes(16));
        $this->sticky = $config['sticky'] ?? false;
        self::$config[$name] = $config;
        $this->optimizationManager = new \Plugs\Database\Optimization\OptimizationManager($this, $config['optimizations'] ?? []);
        // Connection deferred until first query (Lazy Loading)
    }

    /**
     * Get the configuration for the connection.
     *
     * @return array
     */
    public function getConfig(): array
    {
        return self::$config[$this->connectionName] ?? [];
    }

    private function connect(array $config): void
    {
        if ($this->isConnecting) {
            return;
        }

        $this->isConnecting = true;

        try {
            // Handle Read/Write Splitting
            if (isset($config['read']) || isset($config['write'])) {
                $writeConfig = array_merge($config, $config['write'] ?? []);
                $readConfig = array_merge($config, $config['read'] ?? []);

                $this->pdo = $this->createPdo($writeConfig);

                // If read and write configs are identical, share the connection
                if ($writeConfig === $readConfig) {
                    $this->readPdo = &$this->pdo;
                } else {
                    $this->readPdo = $this->connectReadReplica($readConfig);
                }
            } else {
                $this->pdo = $this->createPdo($config);
                $this->readPdo = &$this->pdo;
            }

            $this->lastActivityTime = time();
            $this->isHealthy = true;
            $this->connectionAttempts = 0;
        } finally {
            $this->isConnecting = false;
        }
    }

    /**
     * Connect to a read replica with automatic failover across multiple hosts.
     *
     * If 'host' is an array, uses the LoadBalancer to try each replica
     * until one succeeds. Falls back to the write PDO if all replicas fail.
     */
    private function connectReadReplica(array $readConfig): PDO
    {
        $host = $readConfig['host'] ?? null;

        // Single host or no host — standard connection
        if (!is_array($host) || count($host) <= 1) {
            try {
                return $this->createPdo($readConfig);
            } catch (\Throwable $e) {
                // Fall back to write connection
                $this->auditLog(
                    "Read replica connection failed, falling back to write: " . $e->getMessage(),
                    'WARNING'
                );

                return $this->pdo;
            }
        }

        // Multiple read hosts — use LoadBalancer with failover
        $lb = $this->getOrCreateLoadBalancer($this->connectionName . '_read', $host, $readConfig);

        try {
            $result = $lb->selectWithFailover(function (array $hostEntry) use ($readConfig) {
                $attemptConfig = $readConfig;
                $attemptConfig['host'] = $hostEntry['host'];
                if ($hostEntry['port'] !== null) {
                    $attemptConfig['port'] = $hostEntry['port'];
                }

                return $this->createPdo($attemptConfig);
            });

            return $result['result'];
        } catch (\RuntimeException $e) {
            // All replicas failed — fall back to write connection
            $this->auditLog(
                "All read replicas failed, falling back to write: " . $e->getMessage(),
                'WARNING'
            );

            return $this->pdo;
        }
    }

    private function createPdo(array $config): PDO
    {
        $driver = $config['driver'] ?? 'mysql';
        $attempt = 0;

        while ($attempt < $this->maxRetries) {
            $attempt++;

            try {
                if ($driver === 'sqlite') {
                    $database = $config['database'];
                    $dsn = "sqlite:{$database}";
                    $pdo = new PDO($dsn);
                } else {
                    $dsn = $this->buildDsn($config);
                    $options = $this->buildOptions($config);

                    $pdo = new PDO(
                        $dsn,
                        $config['username'] ?? '',
                        $config['password'] ?? '',
                        $options
                    );
                }

                // Security Best Practices
                $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
                $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
                $pdo->setAttribute(PDO::ATTR_EMULATE_PREPARES, false);

                return $pdo;
            } catch (PDOException $e) {
                // If the error is "Too many connections", fail immediately to avoid load and slowness
                if ($e->getCode() === '08004') {
                    $host = is_array($config['host']) ? implode(',', $config['host']) : ($config['host'] ?? 'unknown');
                    $db = $config['database'] ?? 'unknown';

                    throw new PlugsDatabaseException(
                        "Database connection failed: Too many connections (SQLSTATE 08004) for [{$host}] database [{$db}]",
                        null,
                        [],
                        $e
                    );
                }

                if ($attempt === $this->maxRetries) {
                    $this->auditLog("Connection failure for [{$this->connectionName}]: " . $e->getMessage(), 'CRITICAL');

                    throw PlugsDatabaseException::fromPDOException($e);
                }
                usleep(100000 * $attempt);
            }
        }

        throw new PlugsDatabaseException("Failed to create PDO instance.");
    }

    private function buildDsn(array $config): string
    {
        $driver = $config['driver'];
        $host = $config['host'];

        if (is_array($host)) {
            $host = $this->selectHost($config);
        }

        $port = $config['port'] ?? ($driver === 'mysql' ? 3306 : 5432);
        $database = $config['database'];

        if ($driver === 'mysql') {
            $charset = $config['charset'] ?? 'utf8mb4';

            return "mysql:host={$host};port={$port};dbname={$database};charset={$charset}";
        }

        if ($driver === 'pgsql') {
            $charset = $config['charset'] ?? 'utf8';

            return "pgsql:host={$host};port={$port};dbname={$database};options='--client_encoding={$charset}'";
        }

        throw ConfigurationException::unsupportedDriver($driver);
    }

    /**
     * Select a host from an array of hosts using the LoadBalancer.
     *
     * Falls back to array_rand if only one host is provided.
     */
    private function selectHost(array $config): string
    {
        $hosts = $config['host'];

        if (count($hosts) === 1) {
            return is_array($hosts[0]) ? $hosts[0]['host'] : $hosts[0];
        }

        $lb = $this->getOrCreateLoadBalancer($this->connectionName, $hosts, $config);
        $entry = $lb->select();

        return $entry['host'];
    }

    /**
     * Get or create a LoadBalancer instance for the given name.
     *
     * Reads strategy and options from the connection's 'load_balancing' config key.
     */
    private function getOrCreateLoadBalancer(string $name, array $hosts, array $config): LoadBalancer
    {
        if (!isset(self::$loadBalancers[$name])) {
            $lbConfig = $config['load_balancing'] ?? [];
            $strategy = $lbConfig['strategy'] ?? 'random';
            $options = [
                'health_check_cooldown' => $lbConfig['health_check_cooldown'] ?? 30,
                'max_failures' => $lbConfig['max_failures'] ?? 3,
            ];

            self::$loadBalancers[$name] = new LoadBalancer($hosts, $strategy, $options);
        }

        return self::$loadBalancers[$name];
    }

    /**
     * Get the LoadBalancer instance for a connection (if any).
     *
     * @return LoadBalancer|null
     */
    public static function getLoadBalancer(string $name = 'default'): ?LoadBalancer
    {
        return self::$loadBalancers[$name] ?? null;
    }

    private function buildOptions(array $config): array
    {
        // Determine persistence: pool config overrides, then per-connection config, then auto-detect
        $persistent = $config['persistent'] ?? false;

        if ($this->isInPool) {
            // Pool-level config takes highest priority when pooled
            if (ConnectionPool::getConfig()['persistent'] !== null) {
                $persistent = ConnectionPool::getConfig()['persistent'];
            } elseif (!isset($config['persistent'])) {
                // Auto-enable persistent connections for pooled connections
                // This is the only way PHP-FPM can reuse connections across requests
                $persistent = true;
            }
        }

        $defaults = [
            PDO::ATTR_TIMEOUT => $config['timeout'] ?? 5,
            PDO::ATTR_PERSISTENT => $persistent,
            PDO::ATTR_EMULATE_PREPARES => false, // CRITICAL for security
        ];

        return array_merge($defaults, $config['options'] ?? []);
    }

    // ==================== POOLING GETTERS/SETTERS ====================

    public function getPoolId(): ?string
    {
        return $this->poolId;
    }

    public function setIsInPool(bool $inPool): void
    {
        $this->isInPool = $inPool;
    }

    public function isStale(int $timeout = 300): bool
    {
        return (time() - $this->lastActivityTime) > $timeout;
    }

    public function isHealthy(): bool
    {
        return $this->isHealthy;
    }

    public function touchActivity(): void
    {
        $this->lastActivityTime = time();
    }

    // ==================== DELEGATED POOL METHODS ====================

    public static function configurePool(array $config): void
    {
        ConnectionPool::configure($config);
    }

    public static function configurePoolForEnvironment(string $env = 'production'): void
    {
        ConnectionPool::configureForEnvironment($env);
    }

    public static function warmPool(string $name = 'default'): int
    {
        $config = self::$config[$name] ?? self::loadConfigFromFile($name);
        self::$config[$name] = $config;

        return ConnectionPool::warmPool($name, $config, function () use ($config, $name) {
            return new self($config, $name);
        });
    }

    public static function getPooledConnection(string $name = 'default'): self
    {
        $config = self::$config[$name] ?? self::loadConfigFromFile($name);
        return ConnectionPool::getConnection($name, 'standard', $config, function () use ($config, $name) {
            return new self($config, $name);
        });
    }

    public function release(): void
    {
        if (!$this->isInPool)
            return;
        $this->resetConnection();
        ConnectionPool::release($this, 'standard');
    }

    public static function pruneIdleConnections(string $name = 'default'): int
    {
        return ConnectionPool::pruneIdleConnections($name);
    }

    private function resetConnection(): void
    {
        try {
            if ($this->pdo !== null && $this->pdo->inTransaction()) {
                $this->pdo->rollBack();
            }
            $this->transactions = 0;
            $this->lastWriteTimestamp = 0;
            $this->sticky = self::$config[$this->connectionName]['sticky'] ?? false;

            if ($this->pdo !== null && $this->pdo->getAttribute(PDO::ATTR_DRIVER_NAME) === 'mysql') {
                try {
                    $this->pdo->exec('SET @_plugs_reset = NULL');
                } catch (PDOException $e) {
                }
            }
        } catch (PDOException $e) {
            $this->isHealthy = false;
        }
    }

    // ==================== PREPARED STATEMENT POOL ====================

    /**
     * Get or create a prepared statement (with caching)
     */
    public function preparePooled(string $sql): \PDOStatement
    {
        $cacheKey = md5($sql);

        if (!isset(self::$statementPool[$this->connectionName])) {
            self::$statementPool[$this->connectionName] = [];
        }

        $pool = &self::$statementPool[$this->connectionName];

        // Return cached statement if exists
        if (isset($pool[$cacheKey])) {
            return $pool[$cacheKey];
        }

        // Enforce pool size limit
        if (count($pool) >= self::$statementPoolSize) {
            // Remove oldest entry (FIFO)
            array_shift($pool);
        }

        // Create and cache new statement
        $stmt = $this->pdo->prepare($sql);
        $pool[$cacheKey] = $stmt;

        return $stmt;
    }

    /**
     * Clear statement pool
     */
    public static function clearStatementPool(?string $name = null): void
    {
        if ($name === null) {
            self::$statementPool = [];
        } else {
            unset(self::$statementPool[$name]);
        }
    }

    // ==================== DELEGATED QUERY ANALYSIS ====================

    public static function enableQueryAnalysis(bool $enable = true): void
    {
        QueryAnalyzer::enable($enable);
    }

    public static function configureQueryAnalysis(array $config): void
    {
        QueryAnalyzer::configure($config);
    }

    public static function getQueryAnalysisReport(): array
    {
        return QueryAnalyzer::getReport();
    }

    public static function resetQueryStats(): void
    {
        QueryAnalyzer::resetStats();
    }

    // ==================== EXISTING METHODS (Enhanced) ====================

    private function ensureConnectionHealth(): void
    {
        if ($this->pdo === null) {
            return;
        }

        // Only check health once every 60 seconds to improve performance
        if ((time() - $this->lastHealthCheckAt) < 60) {
            return;
        }

        $maxIdleTime = self::$config[$this->connectionName]['max_idle_time'] ?? 3600;

        if ((time() - $this->lastActivityTime) > $maxIdleTime) {
            $this->reconnect();

            return;
        }

        if (!$this->ping()) {
            $this->reconnect();
        }

        $this->lastHealthCheckAt = time();
    }

    public function ping(): bool
    {
        if ($this->pdo === null) {
            return false;
        }

        try {
            // Using a simple query to verify connection
            @$this->pdo->query('SELECT 1');
            $this->lastActivityTime = time();

            return true;
        } catch (PDOException $e) {
            $this->isHealthy = false;
            $this->auditLog("Ping failed: " . $e->getMessage(), 'WARNING');

            return false;
        }
    }

    public function reconnect(): void
    {
        $this->disconnect();
        $this->connect(self::$config[$this->connectionName]);

        // Clear statement cache for this connection
        unset(self::$statementPool[$this->connectionName]);
    }

    /**
     * Reconnect only the read PDO to a (potentially different) replica.
     */
    public function reconnectReadPdo(): void
    {
        $config = self::$config[$this->connectionName];

        if (isset($config['read'])) {
            $readConfig = array_merge($config, $config['read']);
            $this->readPdo = $this->connectReadReplica($readConfig);
        }
    }

    public function disconnect(): void
    {
        $this->pdo = null;
        $this->readPdo = null;
        $this->isHealthy = false;
        $this->isConnecting = false;
    }

    private function isConnectionError(PDOException $e): bool
    {
        // 08004 is "Too many connections", we don't want to retry/reconnect on this
        // as it will likely fail again and increase the connection count.
        if ($e->getCode() === '08004') {
            return false;
        }

        $connectionErrors = ['2006', '2013', '08S01'];

        return in_array($e->getCode(), $connectionErrors, true);
    }

    // Standard connection method (without pooling)
    public static function getInstance(array|null $config = null, string $connectionName = 'default'): self
    {
        // Resolve actual connection name if 'default' or provided via config
        if ($config === null) {
            $dbConfig = config('database');
            if ($connectionName === 'default') {
                $connectionName = $dbConfig['default'] ?? 'mysql';
            }
            $config = $dbConfig['connections'][$connectionName] ?? null;

            if ($config === null) {
                throw ConfigurationException::connectionNotConfigured($connectionName);
            }
        }

        if (!isset(self::$instances[$connectionName])) {
            self::$instances[$connectionName] = new self($config, $connectionName);
        } else {
            self::$instances[$connectionName]->ensureConnectionHealth();
        }

        return self::$instances[$connectionName];
    }

    private static function loadConfigFromFile(string $name): array
    {
        $dbConfig = config('database');

        if ($name === 'default') {
            $name = $dbConfig['default'] ?? 'mysql';
        }

        if (!isset($dbConfig['connections'][$name])) {
            throw ConfigurationException::connectionNotConfigured($name);
        }

        return $dbConfig['connections'][$name];
    }

    public static function connection(string $name): self
    {
        return self::getInstance(null, $name);
    }

    public function getPdo(): PDO
    {
        if ($this->pdo === null) {
            $this->connect(self::$config[$this->connectionName]);
        }

        $this->ensureConnectionHealth();
        $this->lastActivityTime = time();

        return $this->pdo;
    }

    public function query(string $sql, array $params = []): \PDOStatement
    {
        if (class_exists(QueryGuard::class)) {
            QueryGuard::check($sql, true, function ($msg, $lvl) {
                $this->auditLog($msg, $lvl);
            });
        }

        $pdo = $this->getPdoForQuery($sql);
        $stmt = $pdo->prepare($sql);

        $start = microtime(true);
        $stmt->execute($params);
        $time = microtime(true) - $start;

        if (class_exists(QueryAnalyzer::class)) {
            if (QueryAnalyzer::isLoggingEnabled()) {
                QueryAnalyzer::logQuery($sql, $params, $time, $pdo === $this->pdo ? 'write' : 'read');
            }
            if (QueryAnalyzer::isEnabled()) {
                QueryAnalyzer::analyze($sql, $params, $time, QueryAnalyzer::captureBacktrace(self::class));
            }
        }

        $this->recordObservabilityMetrics($sql, $params, $time);

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

    public function execute(string $sql, array $params = []): int
    {
        $stmt = $this->query($sql, $params);

        return $stmt->rowCount();
    }

    public function lastInsertId(): string
    {
        return $this->getPdo()->lastInsertId();
    }

    public function beginTransaction(): bool
    {
        $this->getPdo(); // Ensure connection exists

        if ($this->transactions === 0) {
            $result = $this->pdo->beginTransaction();
        } else {
            $this->pdo->exec("SAVEPOINT trans{$this->transactions}");
            $result = true;
        }

        $this->transactions++;

        return $result;
    }

    public function commit(): bool
    {
        $this->transactions--;

        if ($this->transactions === 0) {
            if ($this->pdo && $this->pdo->inTransaction()) {
                return $this->pdo->commit();
            }

            return false;
        }

        $this->pdo->exec("RELEASE SAVEPOINT trans{$this->transactions}");

        return true;
    }

    public function rollBack(): bool
    {
        if ($this->transactions === 0) {
            return false;
        }

        $this->transactions--;

        if ($this->transactions === 0) {
            if ($this->pdo && $this->pdo->inTransaction()) {
                return $this->pdo->rollBack();
            }

            return false;
        }

        $this->pdo->exec("ROLLBACK TO SAVEPOINT trans{$this->transactions}");

        return true;
    }

    public function inTransaction(): bool
    {
        if ($this->pdo === null) {
            return $this->transactions > 0;
        }

        return $this->transactions > 0 || $this->pdo->inTransaction();
    }

    /**
     * Execute a Closure within a transaction.
     *
     * @param \Closure $callback
     * @param int $attempts
     * @return mixed
     *
     * @throws \Throwable
     */
    public function transaction(\Closure $callback, int $attempts = 1)
    {
        for ($currentAttempt = 1; $currentAttempt <= $attempts; $currentAttempt++) {
            $this->beginTransaction();

            try {
                $result = $callback($this);
                $this->commit();

                return $result;
            } catch (\Throwable $e) {
                $this->rollBack();

                if ($this->isDeadlock($e) && $currentAttempt < $attempts) {
                    continue;
                }

                throw $e;
            }
        }
    }

    /**
     * Determine if the given exception was caused by a deadlock.
     *
     * @param \Throwable $e
     * @return bool
     */
    protected function isDeadlock(\Throwable $e): bool
    {
        $message = $e->getMessage();

        return str_contains($message, 'Deadlock found when trying to get lock') ||
            str_contains($message, 'database is locked') ||
            str_contains($message, 'Lock wait timeout exceeded') ||
            str_contains($message, 'Transaction rollback: serialization failure');
    }

    /**
     * Get the number of active transactions.
     *
     * @return int
     */
    public function transactionLevel(): int
    {
        return $this->transactions;
    }

    /**
     * Get the connection name
     */
    public function getName(): string
    {
        return $this->connectionName;
    }

    public function getStats(): array
    {
        return [
            'connection_name' => $this->connectionName,
            'is_healthy' => $this->isHealthy,
            'last_activity' => $this->lastActivityTime,
            'idle_time' => time() - $this->lastActivityTime,
            'in_transaction' => $this->inTransaction(),
            'cached_statements' => count(self::$statementPool[$this->connectionName] ?? []),
            'is_pooled' => $this->isInPool,
        ];
    }

    public static function getPoolStats(string $name = 'default'): array
    {
        return ConnectionPool::getStats($name);
    }

    /**
     * Get the current pool configuration.
     */


    /**
     * Reset the runtime detection cache (useful for testing).
     */


    public static function getPoolConfig(): array
    {
        return ConnectionPool::getConfig();
    }

    public static function detectRuntime(): string
    {
        return ConnectionPool::detectRuntime();
    }

    public static function resetRuntimeDetection(): void
    {
        ConnectionPool::resetRuntimeDetection();
    }

    public static function closeAll(): void
    {
        foreach (self::$instances as $instance) {
            $instance->disconnect();
        }
        ConnectionPool::closeAll();
        self::$instances = [];
        self::$statementPool = [];
        self::$loadBalancers = [];
    }

    /**
     * Enable query logging
     */
    public function enableQueryLog(): void
    {
        QueryAnalyzer::enable(true);
    }

    /**
     * Disable query logging
     */
    public function disableQueryLog(): void
    {
        QueryAnalyzer::enable(false);
    }

    /**
     * Get the logged queries
     */
    public function getQueryLog(): array
    {
        return QueryAnalyzer::getLog();
    }

    /**
     * Clear the query log
     */
    public function clearQueryLog(): void
    {
        QueryAnalyzer::clearLog();
    }

    /**
     * Enable or disable strict mode (prevents UPDATE/DELETE without WHERE)
     */
    public function setStrictMode(bool $strict = true): self
    {
        $this->strictMode = $strict;

        return $this;
    }

    /**
     * Get the last inserted ID
     */
    public function getLastInsertId(): string
    {
        return $this->pdo->lastInsertId();
    }

    /**
     * Determine which PDO instance to use for a query.
     *
     * Includes independent health checking for the read PDO so that
     * a dead replica is automatically replaced or falls back to write.
     */
    private function getPdoForQuery(string $sql): PDO
    {
        if ($this->shouldUseTenantConnection()) {
            return $this->getTenantPdo();
        }

        if ($this->pdo === null) {
            $this->connect(self::$config[$this->connectionName]);
        }

        if ($this->shouldUseWritePdo($sql)) {
            return $this->pdo;
        }

        // Independent read PDO health check
        $this->ensureReadConnectionHealth();

        return $this->readPdo;
    }

    protected function shouldUseTenantConnection(): bool
    {
        if (!class_exists(\Plugs\Support\Facades\App::class)) {
            return false;
        }

        try {
            /** @var \Plugs\Tenancy\TenantManager|null $manager */
            $manager = \Plugs\Support\Facades\App::getContainer()->bound(\Plugs\Tenancy\TenantManager::class)
                ? \Plugs\Support\Facades\App::make(\Plugs\Tenancy\TenantManager::class)
                : null;

            return $manager && $manager->isActive() && $manager->getTenant()->getTenantDatabaseConfig() !== null;
        } catch (\Throwable $e) {
            return false;
        }
    }

    protected function getTenantPdo(): PDO
    {
        /** @var \Plugs\Tenancy\TenantManager $manager */
        $manager = \Plugs\Support\Facades\App::make(\Plugs\Tenancy\TenantManager::class);
        $tenant = $manager->getTenant();
        $dbConfig = $tenant->getTenantDatabaseConfig();

        $tenantId = $tenant->getTenantKey();
        $connectionName = "tenant_{$tenantId}";

        if (isset(self::$instances[$connectionName])) {
            return self::$instances[$connectionName]->pdo;
        }

        // Create a temporary connection for the tenant
        $config = array_merge(self::$config[$this->connectionName], $dbConfig);
        $instance = new self($config, $connectionName);
        $instance->connect($config);

        self::$instances[$connectionName] = $instance;

        return $instance->pdo;
    }

    /**
     * Ensure the read PDO is healthy. If not, reconnect to a (potentially different) replica.
     *
     * Rate-limited to once per 60 seconds to avoid overhead.
     */
    private function ensureReadConnectionHealth(): void
    {
        // Skip if readPdo IS the write pdo (same connection)
        if ($this->readPdo === $this->pdo) {
            return;
        }

        if ($this->readPdo === null) {
            $this->reconnectReadPdo();

            return;
        }

        // Rate-limit health checks
        if ((time() - $this->lastReadHealthCheckAt) < 60) {
            return;
        }

        try {
            @$this->readPdo->query('SELECT 1');
            $this->lastReadHealthCheckAt = time();
        } catch (PDOException $e) {
            $this->auditLog(
                "Read replica health check failed, reconnecting: " . $e->getMessage(),
                'WARNING'
            );
            $this->reconnectReadPdo();
            $this->lastReadHealthCheckAt = time();
        }
    }

    /**
     * Determine if the given SQL statement is a write operation.
     */
    private function shouldUseWritePdo(string $sql): bool
    {
        $trimmedSql = ltrim($sql);
        $isWrite = !preg_match('/^(select|show|describe|explain)\b/i', $trimmedSql);

        if ($isWrite) {
            $this->lastWriteTimestamp = microtime(true);

            return true;
        }

        // Handle stickiness: if we just wrote, read from the write connection for a configurable window
        $stickyWindow = self::$config[$this->connectionName]['sticky_window'] ?? 0.5;

        if ($this->sticky && (microtime(true) - $this->lastWriteTimestamp) < $stickyWindow) {
            return true;
        }

        return false;
    }

    /**
     * Guard against dangerous queries (e.g. DELETE/UPDATE without WHERE)
     */

    /**
     * Audit log message to file
     */
    private function auditLog(string $message, string $level = 'INFO'): void
    {
        $date = date('Y-m-d H:i:s');
        $log = "[{$date}] [{$level}] {$message}" . PHP_EOL;

        $basePath = defined('BASE_PATH') ? BASE_PATH : dirname(__DIR__, 2) . '/';
        $logDir = dirname($basePath . self::$auditLogPath);
        if (!is_dir($logDir)) {
            @mkdir($logDir, 0755, true);
        }

        @file_put_contents($basePath . self::$auditLogPath, $log, FILE_APPEND);
    }

    /**
     * Record metrics and check for slow queries.
     */
    private function recordObservabilityMetrics(string $sql, array $params, float $time, ?array $backtrace = null): void
    {
        if ($backtrace === null) {
            $backtrace = QueryAnalyzer::captureBacktrace(self::class);
        }
        $modelClass = null;

        // Try to identify the model class from the backtrace
        foreach (debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS) as $frame) {
            if (isset($frame['class']) && is_subclass_of($frame['class'], \Plugs\Base\Model\PlugModel::class)) {
                $modelClass = $frame['class'];
                break;
            }
        }

        // Emit QueryExecuted event
        if (function_exists('app') && app()->has('events')) {
            app('events')->dispatch(
                new \Plugs\Event\Core\QueryExecuted($sql, $params, $time, $this->connectionName)
            );
        }

        $threshold = 0.1; // Default 100ms
        $shouldAlert = true;

        if ($modelClass && property_exists($modelClass, 'observabilityConfig') && $modelClass::$observabilityConfig) {
            $threshold = $modelClass::$observabilityConfig->slowQueryThreshold;
            $shouldAlert = $modelClass::$observabilityConfig->alertOnSlow;
        }

        $isSlow = $time > $threshold;

        \Plugs\Database\Observability\MetricsManager::getInstance()->recordQuery(
            (string) $modelClass,
            $sql,
            $time,
            $isSlow
        );

        if ($isSlow && $shouldAlert) {
            $this->auditLog("SLOW QUERY DETECTED ({$time}s): " . $sql, 'ALERT');

            // Fire event if event dispatcher is available
            if (method_exists(\Plugs\Facades\Auth::class, 'user')) { // Dummy check to see if framework is booted enough
                // Potential for $this->events->fire('slow_query', ...) if we have a global event dispatcher
            }
        }
    }

    /**
     * Get indexes for a given table.
     */
    public function getTableIndexes(string $table): array
    {
        if (isset(self::$schemaCache[$this->connectionName][$table]['indexes'])) {
            return self::$schemaCache[$this->connectionName][$table]['indexes'];
        }

        if ($this->pdo === null) {
            $this->connect(self::$config[$this->connectionName]);
        }
        $driver = $this->pdo->getAttribute(PDO::ATTR_DRIVER_NAME);
        $indexes = [];

        if ($driver === 'mysql') {
            $stmt = $this->pdo->query("SHOW INDEX FROM `{$table}`");
            foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
                $indexes[$row['Key_name']]['columns'][] = $row['Column_name'];
                $indexes[$row['Key_name']]['unique'] = $row['Non_unique'] == 0;
            }
        } elseif ($driver === 'sqlite') {
            $stmt = $this->pdo->query("PRAGMA index_list(`{$table}`)");
            foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
                $idxStmt = $this->pdo->query("PRAGMA index_info(`" . $row['name'] . "`)");
                foreach ($idxStmt->fetchAll(PDO::FETCH_ASSOC) as $info) {
                    $indexes[$row['name']]['columns'][] = $info['name'];
                }
                $indexes[$row['name']]['unique'] = $row['unique'] == 1;
            }
        }

        self::$schemaCache[$this->connectionName][$table]['indexes'] = $indexes;

        return $indexes;
    }

    /**
     * Get columns for a given table.
     */
    public function getTableColumns(string $table): array
    {
        if (isset(self::$schemaCache[$this->connectionName][$table]['columns'])) {
            return self::$schemaCache[$this->connectionName][$table]['columns'];
        }

        if ($this->pdo === null) {
            $this->connect(self::$config[$this->connectionName]);
        }
        $driver = $this->pdo->getAttribute(PDO::ATTR_DRIVER_NAME);
        $columns = [];

        if ($driver === 'mysql') {
            $stmt = $this->pdo->query("DESCRIBE `{$table}`");
            foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
                $columns[] = $row['Field'];
            }
        } elseif ($driver === 'sqlite') {
            $stmt = $this->pdo->query("PRAGMA table_info(`{$table}`)");
            foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
                $columns[] = $row['name'];
            }
        }

        self::$schemaCache[$this->connectionName][$table]['columns'] = $columns;

        return $columns;
    }
}
