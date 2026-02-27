<?php

declare(strict_types=1);

namespace Plugs\Database\Pooling;

use Plugs\Database\Connection;
use Plugs\Exceptions\DatabaseException as PlugsDatabaseException;

class ConnectionPool
{
    private static array $connectionPools = [];
    private static array $poolConfig = [
        'min_connections' => 2,      // Minimum connections to keep alive
        'max_connections' => 10,     // Maximum connections allowed
        'connection_timeout' => 30,  // Timeout for acquiring connection (seconds)
        'idle_timeout' => 300,       // How long a connection can be idle (seconds)
        'validate_on_checkout' => true, // Validate connection before returning
        'persistent' => null,        // null = auto (true when pooled), true/false = force
    ];
    private static array $poolLocks = [];
    private static ?string $detectedRuntime = null;

    public static function configure(array $config): void
    {
        self::$poolConfig = array_merge(self::$poolConfig, $config);
    }

    public static function configureForEnvironment(string $env = 'production'): void
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

        self::configure($configs[$env] ?? $configs['production']);
    }

    public static function getConfig(): array
    {
        return self::$poolConfig;
    }

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

    public static function resetRuntimeDetection(): void
    {
        self::$detectedRuntime = null;
    }

    public static function initializePool(string $name): void
    {
        if (!isset(self::$connectionPools[$name])) {
            self::$connectionPools[$name] = [
                'available' => [],
                'in_use' => [],
                'total' => 0,
            ];
        }
    }

    public static function warmPool(string $name, array $config, \Closure $connectionFactory): int
    {
        self::initializePool($name);
        $pool = &self::$connectionPools[$name];
        $warmed = 0;

        while ($pool['total'] < self::$poolConfig['min_connections']) {
            try {
                /** @var Connection $conn */
                $conn = $connectionFactory();
                $conn->setIsInPool(true);
                // Call getPdo internally to trigger connection instead of private connect
                $conn->getPdo();
                $pool['available'][] = $conn;
                $pool['total']++;
                $warmed++;
            } catch (\Throwable $e) {
                error_log("[DB Pool] Failed to warm connection #{$warmed} for [{$name}]: " . $e->getMessage());
                break;
            }
        }

        return $warmed;
    }

    public static function acquireLock(string $name, string $runtime): void
    {
        if ($runtime === 'swoole') {
            if (!isset(self::$poolLocks[$name])) {
                if (class_exists('\Swoole\Lock')) {
                    self::$poolLocks[$name] = new \Swoole\Lock(SWOOLE_MUTEX);
                } else {
                    return;
                }
            }
            self::$poolLocks[$name]->lock();
        }
    }

    public static function releaseLock(string $name, string $runtime): void
    {
        if (isset(self::$poolLocks[$name]) && $runtime === 'swoole') {
            self::$poolLocks[$name]->unlock();
        }
    }

    public static function getConnection(string $name, string $runtime, array $config, \Closure $connectionFactory): Connection
    {
        self::acquireLock($name, $runtime);

        try {
            self::initializePool($name);
            $pool = &self::$connectionPools[$name];

            while (!empty($pool['available'])) {
                /** @var Connection $conn */
                $conn = array_shift($pool['available']);

                if (self::$poolConfig['validate_on_checkout']) {
                    if ($conn->ping() && !$conn->isStale(self::$poolConfig['idle_timeout'])) {
                        $pool['in_use'][$conn->getPoolId()] = $conn;
                        return $conn;
                    } else {
                        $conn->disconnect();
                        $pool['total']--;
                    }
                } else {
                    if ($conn->isStale(self::$poolConfig['idle_timeout'])) {
                        $conn->disconnect();
                        $pool['total']--;
                        continue;
                    }
                    $pool['in_use'][$conn->getPoolId()] = $conn;
                    return $conn;
                }
            }

            if ($pool['total'] < self::$poolConfig['max_connections']) {
                /** @var Connection $conn */
                $conn = $connectionFactory();
                $conn->setIsInPool(true);
                $conn->getPdo(); // Trigger connect securely
                $pool['in_use'][$conn->getPoolId()] = $conn;
                $pool['total']++;
                return $conn;
            }
        } finally {
            self::releaseLock($name, $runtime);
        }

        return self::waitAndGetConnection($name, $runtime, $config, $connectionFactory, $pool);
    }

    private static function waitAndGetConnection(string $name, string $runtime, array $config, \Closure $connectionFactory, array &$pool): Connection
    {
        $startTime = microtime(true);
        $timeout = self::$poolConfig['connection_timeout'];
        $waitMs = 10;
        $maxWaitMs = 500;

        while ((microtime(true) - $startTime) < $timeout) {
            $jitter = random_int(0, max(1, (int) ($waitMs * 0.3)));
            usleep(($waitMs + $jitter) * 1000);
            $waitMs = min($waitMs * 2, $maxWaitMs);

            self::acquireLock($name, $runtime);
            try {
                if (!empty($pool['available'])) {
                    /** @var Connection $conn */
                    $conn = array_shift($pool['available']);
                    $pool['in_use'][$conn->getPoolId()] = $conn;
                    return $conn;
                }

                self::pruneIdleConnections($name);

                if ($pool['total'] < self::$poolConfig['max_connections']) {
                    /** @var Connection $conn */
                    $conn = $connectionFactory();
                    $conn->setIsInPool(true);
                    $conn->getPdo();
                    $pool['in_use'][$conn->getPoolId()] = $conn;
                    $pool['total']++;
                    return $conn;
                }
            } finally {
                self::releaseLock($name, $runtime);
            }
        }

        throw new PlugsDatabaseException(
            sprintf(
                "Connection pool [%s] exhausted (max: %d, in-use: %d). Timeout after %ss waiting for available connection.",
                $name,
                self::$poolConfig['max_connections'],
                count($pool['in_use']),
                $timeout
            )
        );
    }

    public static function release(Connection $conn, string $runtime): void
    {
        $name = $conn->getName();

        self::acquireLock($name, $runtime);

        try {
            $pool = &self::$connectionPools[$name];
            $poolId = $conn->getPoolId();

            if (isset($pool['in_use'][$poolId])) {
                unset($pool['in_use'][$poolId]);
            }

            if ($conn->isHealthy()) {
                $pool['available'][] = $conn;
                $conn->touchActivity();
            } else {
                $conn->disconnect();
                $pool['total']--;
            }
        } finally {
            self::releaseLock($name, $runtime);
        }
    }

    public static function pruneIdleConnections(string $name = 'default'): int
    {
        if (!isset(self::$connectionPools[$name])) {
            return 0;
        }

        $pool = &self::$connectionPools[$name];
        $pruned = 0;
        $minConnections = self::$poolConfig['min_connections'];
        $idleTimeout = self::$poolConfig['idle_timeout'];

        while (count($pool['available']) > $minConnections) {
            /** @var Connection $conn */
            $conn = array_shift($pool['available']);

            if ($conn->isStale($idleTimeout)) {
                $conn->disconnect();
                $pool['total']--;
                $pruned++;
            } else {
                array_unshift($pool['available'], $conn);
                break;
            }
        }

        return $pruned;
    }

    public static function getStats(string $name = 'default'): array
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

    public static function closeAll(): void
    {
        foreach (self::$connectionPools as $name => $pool) {
            foreach ($pool['available'] as $conn) {
                $conn->disconnect();
            }
            foreach ($pool['in_use'] as $conn) {
                $conn->disconnect();
            }
        }
        self::$connectionPools = [];
    }
}
