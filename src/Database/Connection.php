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
    private static $config = [];

    // Connection Pool Management
    private static $connectionPools = [];
    private static $poolConfig = [
        'min_connections' => 2,      // Minimum connections to keep alive
        'max_connections' => 10,     // Maximum connections allowed
        'connection_timeout' => 30,  // Timeout for acquiring connection (seconds)
        'idle_timeout' => 300,       // How long a connection can be idle (seconds)
        'validate_on_checkout' => true, // Validate connection before returning
        'persistent' => null,        // null = auto (true when pooled), true/false = force
    ];

    // Runtime environment detection cache
    private static $detectedRuntime = null;
    private static $poolLocks = [];

    // Load Balancer instances per connection name
    private static $loadBalancers = [];

    // Prepared Statement Pool
    private static $statementPool = [];
    private static $statementPoolSize = 100; // Max cached statements per connection

    // Query Analysis
    private static $queryStats = [];
    private static $enableQueryAnalysis = false;
    private static $queryAnalysisThresholds = [
        'slow_query_time' => 1.0,    // Seconds
        'n_plus_one_threshold' => 10, // Similar queries in a row
    ];

    private $pdo;
    private $readPdo;
    private $connectionName;
    private $lastActivityTime;
    private $isHealthy = true;
    private $isInPool = false;
    private $poolId;
    /** @phpstan-ignore property.onlyWritten */
    private $connectionAttempts = 0;
    private $maxRetries = 3;
    private static $queryLog = [];
    private static $loggingQueries = false;

    // Security & Advanced Features
    private $sticky = false;
    private $lastWriteTimestamp = 0;
    private static $auditLogPath = 'storage/logs/security_audit.log';
    private $isConnecting = false;
    private $lastHealthCheckAt = 0;
    private $lastReadHealthCheckAt = 0;
    private $strictMode = false;
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
        // Connection deferred until first query (Lazy Loading)
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

                    throw new \Plugs\Exceptions\DatabaseException(
                        "Database connection failed: Too many connections (SQLSTATE 08004) for [{$host}] database [{$db}]",
                        null,
                        [],
                        $e
                    );
                }

                if ($attempt === $this->maxRetries) {
                    $this->auditLog("Connection failure for [{$this->connectionName}]: " . $e->getMessage(), 'CRITICAL');

                    throw \Plugs\Exceptions\DatabaseException::fromPDOException($e);
                }
                usleep(100000 * $attempt);
            }
        }

        throw new \RuntimeException("Failed to create PDO instance.");
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

        throw new \InvalidArgumentException("Unsupported database driver: {$driver}");
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
            if (self::$poolConfig['persistent'] !== null) {
                $persistent = self::$poolConfig['persistent'];
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

    // ==================== CONNECTION POOLING ====================

    /**
     * Configure connection pool settings
     */
    public static function configurePool(array $config): void
    {
        self::$poolConfig = array_merge(self::$poolConfig, $config);
    }

    /**
     * Configure pool with environment-specific presets.
     *
     * @param string $env One of 'production', 'development', 'testing'
     */
    public static function configurePoolForEnvironment(string $env = 'production'): void
    {
        $configs = [
            'production' => [
                'min_connections' => 5,
                'max_connections' => 20,
                'connection_timeout' => 10,
                'idle_timeout' => 300,
                'validate_on_checkout' => true,
                'persistent' => true,
            ],
            'development' => [
                'min_connections' => 1,
                'max_connections' => 5,
                'connection_timeout' => 30,
                'idle_timeout' => 600,
                'validate_on_checkout' => false,
                'persistent' => false,
            ],
            'testing' => [
                'min_connections' => 1,
                'max_connections' => 3,
                'connection_timeout' => 5,
                'idle_timeout' => 60,
                'validate_on_checkout' => false,
                'persistent' => false,
            ],
        ];

        self::configurePool($configs[$env] ?? $configs['production']);
    }

    /**
     * Detect the current PHP runtime environment.
     *
     * @return string One of 'swoole', 'frankenphp', 'roadrunner', 'standard'
     */
    public static function detectRuntime(): string
    {
        if (self::$detectedRuntime !== null) {
            return self::$detectedRuntime;
        }

        if (extension_loaded('swoole') || extension_loaded('openswoole')) {
            self::$detectedRuntime = 'swoole';
        } elseif (isset($_SERVER['FRANKENPHP_WORKER'])) {
            self::$detectedRuntime = 'frankenphp';
        } elseif (isset($_SERVER['RR_MODE'])) {
            self::$detectedRuntime = 'roadrunner';
        } else {
            self::$detectedRuntime = 'standard';
        }

        return self::$detectedRuntime;
    }

    /**
     * Acquire a pool-level lock (thread-safe for async runtimes).
     */
    private static function acquirePoolLock(string $name): void
    {
        $runtime = self::detectRuntime();

        if ($runtime === 'swoole') {
            if (!isset(self::$poolLocks[$name])) {
                // Swoole\Lock is available in Swoole environments
                if (class_exists('\Swoole\Lock')) {
                    self::$poolLocks[$name] = new \Swoole\Lock(SWOOLE_MUTEX);
                } else {
                    return; // Graceful fallback
                }
            }
            self::$poolLocks[$name]->lock();
        }
        // For standard PHP-FPM / CLI: no lock needed (single-threaded per request)
        // For RoadRunner/FrankenPHP: PHP workers are isolated processes, no lock needed
    }

    /**
     * Release a pool-level lock.
     */
    private static function releasePoolLock(string $name): void
    {
        if (isset(self::$poolLocks[$name]) && self::detectRuntime() === 'swoole') {
            self::$poolLocks[$name]->unlock();
        }
    }

    /**
     * Pre-warm the pool by eagerly creating up to min_connections.
     *
     * @param string $name Connection name
     * @return int Number of connections created
     */
    public static function warmPool(string $name = 'default'): int
    {
        $config = self::$config[$name] ?? self::loadConfigFromFile($name);
        self::$config[$name] = $config;

        if (!isset(self::$connectionPools[$name])) {
            self::$connectionPools[$name] = [
                'available' => [],
                'in_use' => [],
                'total' => 0,
            ];
        }

        $pool = &self::$connectionPools[$name];
        $warmed = 0;

        while ($pool['total'] < self::$poolConfig['min_connections']) {
            try {
                $conn = new self($config, $name);
                $conn->isInPool = true;
                $conn->connect($config); // Eagerly connect
                $pool['available'][] = $conn;
                $pool['total']++;
                $warmed++;
            } catch (\Throwable $e) {
                // Log but don't fail — partial warm-up is better than none
                error_log("[DB Pool] Failed to warm connection #{$warmed} for [{$name}]: " . $e->getMessage());
                break;
            }
        }

        return $warmed;
    }

    /**
     * Initialize the pool structure for a given connection name.
     */
    private static function initializePool(string $name): void
    {
        if (!isset(self::$connectionPools[$name])) {
            self::$connectionPools[$name] = [
                'available' => [],
                'in_use' => [],
                'total' => 0,
            ];
        }
    }

    /**
     * Get a connection from the pool (with connection pooling).
     *
     * Thread-safe for async runtimes (Swoole/OpenSwoole).
     * Uses exponential backoff with jitter instead of busy-waiting.
     */
    public static function getPooledConnection(string $name = 'default'): self
    {
        self::acquirePoolLock($name);

        try {
            self::initializePool($name);
            $pool = &self::$connectionPools[$name];

            // Try to get an available connection
            while (!empty($pool['available'])) {
                $conn = array_shift($pool['available']);

                // Validate connection if required
                if (self::$poolConfig['validate_on_checkout']) {
                    if ($conn->ping() && !$conn->isStale()) {
                        $pool['in_use'][$conn->poolId] = $conn;

                        return $conn;
                    } else {
                        // Connection is bad, destroy it
                        $conn->disconnect();
                        $pool['total']--;
                    }
                } else {
                    // Quick stale check even without full validation
                    if ($conn->isStale()) {
                        $conn->disconnect();
                        $pool['total']--;

                        continue;
                    }

                    $pool['in_use'][$conn->poolId] = $conn;

                    return $conn;
                }
            }

            // No available connections, check if we can create a new one
            if ($pool['total'] < self::$poolConfig['max_connections']) {
                $config = self::$config[$name] ?? self::loadConfigFromFile($name);
                $conn = new self($config, $name);
                $conn->isInPool = true;
                $pool['in_use'][$conn->poolId] = $conn;
                $pool['total']++;

                return $conn;
            }
        } finally {
            self::releasePoolLock($name);
        }

        // Pool is full — exponential backoff with jitter
        $startTime = microtime(true);
        $timeout = self::$poolConfig['connection_timeout'];
        $waitMs = 10;   // Start at 10ms
        $maxWaitMs = 500; // Cap between retries

        while ((microtime(true) - $startTime) < $timeout) {
            $jitter = random_int(0, max(1, (int) ($waitMs * 0.3)));
            usleep(($waitMs + $jitter) * 1000);
            $waitMs = min($waitMs * 2, $maxWaitMs);

            self::acquirePoolLock($name);

            try {
                // Check if a connection became available
                if (!empty($pool['available'])) {
                    $conn = array_shift($pool['available']);
                    $pool['in_use'][$conn->poolId] = $conn;

                    return $conn;
                }

                // Try pruning stale idle connections to free a slot
                self::pruneIdleConnections($name);

                if ($pool['total'] < self::$poolConfig['max_connections']) {
                    $config = self::$config[$name] ?? self::loadConfigFromFile($name);
                    $conn = new self($config, $name);
                    $conn->isInPool = true;
                    $pool['in_use'][$conn->poolId] = $conn;
                    $pool['total']++;

                    return $conn;
                }
            } finally {
                self::releasePoolLock($name);
            }
        }

        throw new \RuntimeException(
            "Connection pool [{$name}] exhausted (max: " . self::$poolConfig['max_connections'] .
            ", in-use: " . count($pool['in_use']) .
            "). Timeout after {$timeout}s waiting for available connection."
        );
    }

    /**
     * Return connection to pool.
     *
     * Thread-safe: acquires pool lock before modifying pool arrays.
     */
    public function release(): void
    {
        if (!$this->isInPool) {
            return; // Not a pooled connection
        }

        // Reset connection state before returning to pool
        $this->resetConnection();

        self::acquirePoolLock($this->connectionName);

        try {
            $pool = &self::$connectionPools[$this->connectionName];

            // Remove from in_use
            if (isset($pool['in_use'][$this->poolId])) {
                unset($pool['in_use'][$this->poolId]);
            }

            // Only return healthy connections to the pool
            if ($this->isHealthy) {
                $pool['available'][] = $this;
                $this->lastActivityTime = time();
            } else {
                // Unhealthy connection — discard and decrement total
                $this->disconnect();
                $pool['total']--;
            }
        } finally {
            self::releasePoolLock($this->connectionName);
        }
    }

    /**
     * Reset connection state thoroughly before returning to pool.
     *
     * Rolls back open transactions, resets the transaction counter,
     * clears sticky write flags, and resets MySQL session state.
     */
    private function resetConnection(): void
    {
        try {
            // Rollback any open transactions
            if ($this->pdo !== null && $this->pdo->inTransaction()) {
                $this->pdo->rollBack();
            }

            // Reset internal state
            $this->transactions = 0;
            $this->lastWriteTimestamp = 0;
            $this->sticky = self::$config[$this->connectionName]['sticky'] ?? false;

            // Reset database session state
            if ($this->pdo !== null) {
                $driver = $this->pdo->getAttribute(PDO::ATTR_DRIVER_NAME);

                if ($driver === 'mysql') {
                    // Reset user variables and session state
                    // Note: RESET QUERY CACHE is removed in MySQL 8.0+, so we suppress errors
                    try {
                        $this->pdo->exec('SET @_plugs_reset = NULL');
                    } catch (PDOException $e) {
                        // Non-critical, ignore
                    }
                }
            }
        } catch (PDOException $e) {
            // If reset fails, mark connection as unhealthy
            $this->isHealthy = false;
        }
    }

    /**
     * Check if connection is stale
     */
    private function isStale(): bool
    {
        $idleTime = time() - $this->lastActivityTime;

        return $idleTime > self::$poolConfig['idle_timeout'];
    }

    /**
     * Close idle connections in pool
     */
    public static function pruneIdleConnections(string $name = 'default'): int
    {
        if (!isset(self::$connectionPools[$name])) {
            return 0;
        }

        $pool = &self::$connectionPools[$name];
        $pruned = 0;
        $minConnections = self::$poolConfig['min_connections'];

        // Keep at least min_connections alive
        while (count($pool['available']) > $minConnections) {
            $conn = array_shift($pool['available']);

            if ($conn->isStale()) {
                $conn->disconnect();
                $pool['total']--;
                $pruned++;
            } else {
                // Put it back if not stale
                array_unshift($pool['available'], $conn);

                break;
            }
        }

        return $pruned;
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

    // ==================== QUERY ANALYSIS ====================

    /**
     * Enable query analysis for detecting N+1 queries
     */
    public static function enableQueryAnalysis(bool $enable = true): void
    {
        self::$enableQueryAnalysis = $enable;
        self::$loggingQueries = $enable; // Also enable logging for profiler
    }

    /**
     * Configure query analysis thresholds
     */
    public static function configureQueryAnalysis(array $config): void
    {
        self::$queryAnalysisThresholds = array_merge(self::$queryAnalysisThresholds, $config);
    }

    /**
     * Execute query with analysis
     */
    public function query(string $sql, array $params = []): \PDOStatement
    {
        $this->ensureConnectionHealth();
        $this->lastActivityTime = time();
        $pdo = $this->getPdoForQuery($sql);

        // Security: Query Guard - Alert on updates/deletes without WHERE
        $this->guardQuery($sql);

        $startTime = microtime(true);
        $backtrace = null;

        if (self::$enableQueryAnalysis) {
            $backtrace = $this->captureBacktrace();
        }

        try {
            $stmt = $pdo->prepare($sql);
            $stmt->execute($params);

            $executionTime = microtime(true) - $startTime;

            if (self::$enableQueryAnalysis) {
                $this->analyzeQuery($sql, $params, $executionTime, $backtrace);
            }

            if (self::$loggingQueries) {
                self::$queryLog[] = [
                    'query' => $sql,
                    'bindings' => $params,
                    'time' => $executionTime,
                    'connection' => ($pdo === $this->pdo ? 'write' : 'read'),
                ];
            }

            // --- Observability & Metrics ---
            $this->recordObservabilityMetrics($sql, $params, $executionTime);

            return $stmt;
        } catch (PDOException $e) {
            // Try to reconnect once on connection errors
            if ($this->isConnectionError($e)) {
                $this->reconnect();

                return $this->query($sql, $params);
            }

            $this->auditLog("Query error: " . $e->getMessage() . " | SQL: " . $sql, 'WARNING');

            throw $e;
        }
    }

    /**
     * Analyze query for potential issues
     */
    private function analyzeQuery(string $sql, array $params, float $executionTime, ?array $backtrace): void
    {
        // Normalize SQL for pattern matching
        $normalizedSql = $this->normalizeSql($sql);

        // Initialize stats for this query pattern
        if (!isset(self::$queryStats[$normalizedSql])) {
            self::$queryStats[$normalizedSql] = [
                'count' => 0,
                'total_time' => 0,
                'max_time' => 0,
                'min_time' => PHP_FLOAT_MAX,
                'locations' => [],
                'consecutive_count' => 0,
                'last_seen' => null,
            ];
        }

        $stats = &self::$queryStats[$normalizedSql];
        $stats['count']++;
        $stats['total_time'] += $executionTime;
        $stats['max_time'] = max($stats['max_time'], $executionTime);
        $stats['min_time'] = min($stats['min_time'], $executionTime);

        // Track query location
        if ($backtrace) {
            $location = $this->formatBacktrace($backtrace);
            if (!in_array($location, $stats['locations'])) {
                $stats['locations'][] = $location;
            }
        }

        // Detect N+1 queries (similar queries executed in quick succession)
        $currentTime = microtime(true);
        if ($stats['last_seen'] && ($currentTime - $stats['last_seen']) < 0.1) {
            $stats['consecutive_count']++;

            if ($stats['consecutive_count'] >= self::$queryAnalysisThresholds['n_plus_one_threshold']) {
                $this->logWarning("Potential N+1 query detected", [
                    'query' => $normalizedSql,
                    'consecutive_count' => $stats['consecutive_count'],
                    'location' => $location ?? 'unknown',
                ]);
            }
        } else {
            $stats['consecutive_count'] = 0;
        }
        $stats['last_seen'] = $currentTime;

        // Detect slow queries
        if ($executionTime > self::$queryAnalysisThresholds['slow_query_time']) {
            $this->logWarning("Slow query detected", [
                'query' => $normalizedSql,
                'execution_time' => $executionTime,
                'location' => $location ?? 'unknown',
            ]);
        }
    }

    /**
     * Normalize SQL for pattern matching
     */
    private function normalizeSql(string $sql): string
    {
        // Remove extra whitespace
        $sql = preg_replace('/\s+/', ' ', $sql);

        // Replace parameter placeholders with generic marker
        $sql = preg_replace('/\?/', '?', $sql);
        $sql = preg_replace('/:[a-zA-Z0-9_]+/', ':param', $sql);

        return trim($sql);
    }

    /**
     * Capture backtrace for query location
     */
    private function captureBacktrace(): array
    {
        $trace = debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS, 10);

        // Find the first frame outside this class
        foreach ($trace as $frame) {
            if (!isset($frame['class']) || $frame['class'] !== self::class) {
                return $frame;
            }
        }

        return $trace[0] ?? [];
    }

    /**
     * Format backtrace for logging
     */
    private function formatBacktrace(array $frame): string
    {
        $file = $frame['file'] ?? 'unknown';
        $line = $frame['line'] ?? 0;
        $function = $frame['function'] ?? 'unknown';

        return "{$file}:{$line} ({$function})";
    }

    /**
     * Get query analysis report
     */
    public static function getQueryAnalysisReport(): array
    {
        $report = [
            'total_queries' => 0,
            'query_count' => 0,
            'unique_queries' => count(self::$queryStats),
            'total_time' => 0,
            'query_time_ms' => 0,
            'slow_queries' => [],
            'n_plus_one_suspects' => [],
            'most_frequent' => [],
            'queries' => self::$queryLog,
        ];

        foreach (self::$queryStats as $sql => $stats) {
            $report['total_queries'] += $stats['count'];
            $report['total_time'] += $stats['total_time'];

            $avgTime = $stats['total_time'] / $stats['count'];

            // Identify slow queries
            if ($stats['max_time'] > self::$queryAnalysisThresholds['slow_query_time']) {
                $report['slow_queries'][] = [
                    'query' => $sql,
                    'max_time' => $stats['max_time'],
                    'avg_time' => $avgTime,
                    'count' => $stats['count'],
                ];
            }

            // Identify potential N+1 queries
            if ($stats['count'] > self::$queryAnalysisThresholds['n_plus_one_threshold']) {
                $report['n_plus_one_suspects'][] = [
                    'query' => $sql,
                    'count' => $stats['count'],
                    'locations' => $stats['locations'],
                ];
            }
        }

        // Sort by frequency
        $sortedStats = self::$queryStats;
        uasort($sortedStats, function ($a, $b) {
            return $b['count'] <=> $a['count'];
        });

        $report['most_frequent'] = array_slice($sortedStats, 0, 10, true);

        // Map for profiler bar compatibility
        $report['query_count'] = $report['total_queries'];
        $report['query_time_ms'] = $report['total_time'] * 1000;

        return $report;
    }

    /**
     * Reset query statistics
     */
    public static function resetQueryStats(): void
    {
        self::$queryStats = [];
    }

    /**
     * Log warning (extend this to use your logging system)
     */
    private function logWarning(string $message, array $context): void
    {
        error_log(sprintf(
            "[DB Warning] %s: %s",
            $message,
            json_encode($context, JSON_UNESCAPED_SLASHES)
        ));
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
                throw new \InvalidArgumentException("Connection [{$connectionName}] not configured.");
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
            throw new \InvalidArgumentException("Connection [{$name}] not configured.");
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
        if (!isset(self::$connectionPools[$name])) {
            return ['pool_exists' => false];
        }

        $pool = self::$connectionPools[$name];

        return [
            'pool_exists' => true,
            'total_connections' => $pool['total'],
            'available_connections' => count($pool['available']),
            'in_use_connections' => count($pool['in_use']),
            'pool_config' => self::$poolConfig,
            'runtime' => self::detectRuntime(),
            'persistent_enabled' => self::$poolConfig['persistent'],
        ];
    }

    /**
     * Get the current pool configuration.
     */
    public static function getPoolConfig(): array
    {
        return self::$poolConfig;
    }

    /**
     * Reset the runtime detection cache (useful for testing).
     */
    public static function resetRuntimeDetection(): void
    {
        self::$detectedRuntime = null;
    }

    public static function closeAll(): void
    {
        foreach (self::$instances as $instance) {
            $instance->disconnect();
        }

        // Close all pooled connections
        foreach (self::$connectionPools as $name => $pool) {
            foreach ($pool['available'] as $conn) {
                $conn->disconnect();
            }
            foreach ($pool['in_use'] as $conn) {
                $conn->disconnect();
            }
        }

        self::$instances = [];
        self::$connectionPools = [];
        self::$statementPool = [];
        self::$loadBalancers = [];
    }

    /**
     * Enable query logging
     */
    public function enableQueryLog(): void
    {
        self::$loggingQueries = true;
    }

    /**
     * Disable query logging
     */
    public function disableQueryLog(): void
    {
        self::$loggingQueries = false;
    }

    /**
     * Get the logged queries
     */
    public function getQueryLog(): array
    {
        return self::$queryLog;
    }

    /**
     * Clear the query log
     */
    public function clearQueryLog(): void
    {
        self::$queryLog = [];
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
    private function guardQuery(string $sql): void
    {
        if (preg_match('/^\s*(update|delete)\b/i', $sql) && !stripos($sql, 'where')) {
            $message = "DANGEROUS QUERY DETECTED (No WHERE clause): " . trim($sql);
            $this->auditLog($message, 'ALERT');

            if ($this->strictMode) {
                throw new \Plugs\Exceptions\DatabaseException($message);
            }
        }
    }

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
    private function recordObservabilityMetrics(string $sql, array $params, float $time): void
    {
        $backtrace = $this->captureBacktrace();
        $modelClass = null;

        // Try to identify the model class from the backtrace
        foreach (debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS) as $frame) {
            if (isset($frame['class']) && is_subclass_of($frame['class'], \Plugs\Base\Model\PlugModel::class)) {
                $modelClass = $frame['class'];
                break;
            }
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
