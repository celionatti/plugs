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
    ];

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
    private $connectionAttempts = 0;
    private $maxRetries = 3;
    private $queryLog = [];
    private $loggingQueries = false;

    // Security & Advanced Features
    private $sticky = false;
    private $lastWriteTimestamp = 0;
    private static $auditLogPath = 'storage/logs/security_audit.log';

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
        // Handle Read/Write Splitting
        if (isset($config['read']) || isset($config['write'])) {
            $this->pdo = $this->createPdo(array_merge($config, $config['write'] ?? []));
            $this->readPdo = $this->createPdo(array_merge($config, $config['read'] ?? []));
        } else {
            $this->pdo = $this->createPdo($config);
            $this->readPdo = &$this->pdo;
        }

        $this->lastActivityTime = time();
        $this->isHealthy = true;
        $this->connectionAttempts = 0;
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
                if ($attempt === $this->maxRetries) {
                    $this->auditLog("Connection failure for [{$this->connectionName}]: " . $e->getMessage(), 'CRITICAL');
                    throw new \RuntimeException("Database connection failed: " . $e->getMessage());
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
            $host = $host[array_rand($host)];
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

    private function buildOptions(array $config): array
    {
        $defaults = [
            PDO::ATTR_TIMEOUT => $config['timeout'] ?? 5,
            PDO::ATTR_PERSISTENT => $config['persistent'] ?? false,
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
     * Get a connection from the pool (with connection pooling)
     */
    public static function getPooledConnection(string $name = 'default'): self
    {
        // Initialize pool if it doesn't exist
        if (!isset(self::$connectionPools[$name])) {
            self::$connectionPools[$name] = [
                'available' => [],
                'in_use' => [],
                'total' => 0,
            ];
        }

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

        // Pool is full, wait for a connection to become available
        $startTime = time();
        $timeout = self::$poolConfig['connection_timeout'];

        while ((time() - $startTime) < $timeout) {
            usleep(50000); // Wait 50ms

            if (!empty($pool['available'])) {
                return self::getPooledConnection($name);
            }
        }

        throw new \RuntimeException("Connection pool exhausted. Timeout waiting for available connection.");
    }

    /**
     * Return connection to pool
     */
    public function release(): void
    {
        if (!$this->isInPool) {
            return; // Not a pooled connection
        }

        $pool = &self::$connectionPools[$this->connectionName];

        // Remove from in_use
        if (isset($pool['in_use'][$this->poolId])) {
            unset($pool['in_use'][$this->poolId]);
        }

        // Reset connection state
        $this->resetConnection();

        // Add back to available pool
        $pool['available'][] = $this;
        $this->lastActivityTime = time();
    }

    /**
     * Reset connection state (rollback transactions, etc.)
     */
    private function resetConnection(): void
    {
        try {
            // Rollback any open transactions
            if ($this->pdo->inTransaction()) {
                $this->pdo->rollBack();
            }

            // Clear any temporary tables or session variables if needed
            // This depends on your application needs
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

            if ($this->loggingQueries) {
                $this->queryLog[] = [
                    'query' => $sql,
                    'params' => $params,
                    'time' => $executionTime,
                    'connection' => ($pdo === $this->pdo ? 'write' : 'read')
                ];
            }

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
            'unique_queries' => count(self::$queryStats),
            'slow_queries' => [],
            'n_plus_one_suspects' => [],
            'most_frequent' => [],
        ];

        foreach (self::$queryStats as $sql => $stats) {
            $report['total_queries'] += $stats['count'];

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

        $maxIdleTime = self::$config[$this->connectionName]['max_idle_time'] ?? 3600;

        if ((time() - $this->lastActivityTime) > $maxIdleTime) {
            $this->reconnect();

            return;
        }

        if (!$this->ping()) {
            $this->reconnect();
        }
    }

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

    public function reconnect(): void
    {
        $this->disconnect();
        $this->connect(self::$config[$this->connectionName]);

        // Clear statement cache for this connection
        unset(self::$statementPool[$this->connectionName]);
    }

    public function disconnect(): void
    {
        $this->pdo = null;
        $this->isHealthy = false;
    }

    private function isConnectionError(PDOException $e): bool
    {
        $connectionErrors = ['HY000', '2006', '2013', '08S01'];

        return in_array($e->getCode(), $connectionErrors, true);
    }

    // Standard connection method (without pooling)
    public static function getInstance(array|null $config = null, string $connectionName = 'default'): self
    {
        if (!isset(self::$instances[$connectionName])) {
            if ($config === null) {
                $config = self::loadConfigFromFile($connectionName);
            }
            self::$instances[$connectionName] = new self($config, $connectionName);
        } else {
            self::$instances[$connectionName]->ensureConnectionHealth();
        }

        return self::$instances[$connectionName];
    }

    private static function loadConfigFromFile(string $name): array
    {
        $dbConfig = require BASE_PATH . 'config/database.php';

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
            return $this->pdo->commit();
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
            return $this->pdo->rollBack();
        }

        $this->pdo->exec("ROLLBACK TO SAVEPOINT trans{$this->transactions}");
        return true;
    }

    public function inTransaction(): bool
    {
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
        ];
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
    }

    /**
     * Enable query logging
     */
    public function enableQueryLog(): void
    {
        $this->loggingQueries = true;
    }

    /**
     * Disable query logging
     */
    public function disableQueryLog(): void
    {
        $this->loggingQueries = false;
    }

    /**
     * Get the logged queries
     */
    public function getQueryLog(): array
    {
        return $this->queryLog;
    }

    /**
     * Clear the query log
     */
    public function clearQueryLog(): void
    {
        $this->queryLog = [];
    }

    /**
     * Get the last inserted ID
     */
    public function getLastInsertId(): string
    {
        return $this->pdo->lastInsertId();
    }

    /**
     * Determine which PDO instance to use for a query
     */
    private function getPdoForQuery(string $sql): PDO
    {
        if ($this->pdo === null) {
            $this->connect(self::$config[$this->connectionName]);
        }

        if ($this->shouldUseWritePdo($sql)) {
            return $this->pdo;
        }

        return $this->readPdo;
    }

    /**
     * Determine if the given SQL statement is a write operation
     */
    private function shouldUseWritePdo(string $sql): bool
    {
        $trimmedSql = ltrim($sql);
        $isWrite = !preg_match('/^(select|show|describe|explain)\b/i', $trimmedSql);

        if ($isWrite) {
            $this->lastWriteTimestamp = microtime(true);
            return true;
        }

        // Handle stickiness: if we just wrote, read from the same connection for a short window
        if ($this->sticky && (microtime(true) - $this->lastWriteTimestamp) < 0.5) {
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
            $this->auditLog("DANGEROUS QUERY DETECTED (No WHERE clause): " . trim($sql), 'ALERT');
        }
    }

    /**
     * Audit log message to file
     */
    private function auditLog(string $message, string $level = 'INFO'): void
    {
        $date = date('Y-m-d H:i:s');
        $log = "[{$date}] [{$level}] {$message}" . PHP_EOL;

        $logDir = dirname(BASE_PATH . self::$auditLogPath);
        if (!is_dir($logDir)) {
            @mkdir($logDir, 0755, true);
        }

        @file_put_contents(BASE_PATH . self::$auditLogPath, $log, FILE_APPEND);
    }
}
