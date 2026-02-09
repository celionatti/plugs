<?php

declare(strict_types=1);

namespace Plugs\Http\Controllers;

use Plugs\Http\ResponseFactory;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;

/**
 * Health Check Controller.
 * Provides standard /health endpoint for monitoring.
 */
class HealthController
{
    /**
     * Main health check endpoint.
     * Returns overall app health status.
     */
    public function index(ServerRequestInterface $request): ResponseInterface
    {
        $checks = $this->runChecks();
        $healthy = $this->isHealthy($checks);

        return ResponseFactory::json([
            'status' => $healthy ? 'healthy' : 'unhealthy',
            'timestamp' => date('c'),
            'checks' => $checks,
        ], $healthy ? 200 : 503);
    }

    /**
     * Detailed health with metrics.
     */
    public function detailed(ServerRequestInterface $request): ResponseInterface
    {
        $checks = $this->runChecks();
        $healthy = $this->isHealthy($checks);

        return ResponseFactory::json([
            'status' => $healthy ? 'healthy' : 'unhealthy',
            'timestamp' => date('c'),
            'version' => config('app.version', '1.0.0'),
            'environment' => config('app.env', 'production'),
            'checks' => $checks,
            'system' => $this->getSystemInfo(),
        ], $healthy ? 200 : 503);
    }

    /**
     * Liveness probe (K8s compatible).
     */
    public function liveness(ServerRequestInterface $request): ResponseInterface
    {
        return ResponseFactory::json(['status' => 'alive'], 200);
    }

    /**
     * Readiness probe (K8s compatible).
     */
    public function readiness(ServerRequestInterface $request): ResponseInterface
    {
        $ready = $this->checkDatabase() && $this->checkCache();

        return ResponseFactory::json(
            ['status' => $ready ? 'ready' : 'not_ready'],
            $ready ? 200 : 503
        );
    }

    /**
     * Run all health checks.
     */
    private function runChecks(): array
    {
        return [
            'database' => $this->checkDatabase(),
            'cache' => $this->checkCache(),
            'disk' => $this->checkDiskSpace(),
            'memory' => $this->checkMemory(),
        ];
    }

    /**
     * Check if all checks passed.
     */
    private function isHealthy(array $checks): bool
    {
        foreach ($checks as $check) {
            if ($check['status'] !== 'ok') {
                return false;
            }
        }
        return true;
    }

    /**
     * Check database connection.
     */
    private function checkDatabase(): array
    {
        try {
            $start = microtime(true);

            // Try to get a database connection
            if (function_exists('db')) {
                db()->query('SELECT 1');
            }

            $latency = round((microtime(true) - $start) * 1000, 2);

            return [
                'status' => 'ok',
                'latency_ms' => $latency,
            ];
        } catch (\Throwable $e) {
            return [
                'status' => 'error',
                'message' => $e->getMessage(),
            ];
        }
    }

    /**
     * Check cache availability.
     */
    private function checkCache(): array
    {
        try {
            $start = microtime(true);
            $key = '_health_check_' . time();

            if (function_exists('cache')) {
                cache($key, 'test', 10);
                $value = cache($key);
                cache()->delete($key);
            }

            $latency = round((microtime(true) - $start) * 1000, 2);

            return [
                'status' => 'ok',
                'latency_ms' => $latency,
            ];
        } catch (\Throwable $e) {
            return [
                'status' => 'error',
                'message' => $e->getMessage(),
            ];
        }
    }

    /**
     * Check disk space.
     */
    private function checkDiskSpace(): array
    {
        $path = base_path();
        $free = disk_free_space($path);
        $total = disk_total_space($path);
        $usedPercent = round((($total - $free) / $total) * 100, 2);

        return [
            'status' => $usedPercent < 90 ? 'ok' : 'warning',
            'free_gb' => round($free / 1024 / 1024 / 1024, 2),
            'total_gb' => round($total / 1024 / 1024 / 1024, 2),
            'used_percent' => $usedPercent,
        ];
    }

    /**
     * Check memory usage.
     */
    private function checkMemory(): array
    {
        $usage = memory_get_usage(true);
        $peak = memory_get_peak_usage(true);
        $limit = $this->getMemoryLimit();

        $usedPercent = $limit > 0 ? round(($usage / $limit) * 100, 2) : 0;

        return [
            'status' => $usedPercent < 80 ? 'ok' : 'warning',
            'usage_mb' => round($usage / 1024 / 1024, 2),
            'peak_mb' => round($peak / 1024 / 1024, 2),
            'limit_mb' => round($limit / 1024 / 1024, 2),
            'used_percent' => $usedPercent,
        ];
    }

    /**
     * Get system information.
     */
    private function getSystemInfo(): array
    {
        return [
            'php_version' => PHP_VERSION,
            'os' => PHP_OS,
            'server_time' => date('c'),
            'uptime' => $this->getUptime(),
        ];
    }

    /**
     * Get memory limit in bytes.
     */
    private function getMemoryLimit(): int
    {
        $limit = ini_get('memory_limit');

        if ($limit === '-1') {
            return 0;
        }

        $unit = strtolower(substr($limit, -1));
        $value = (int) $limit;

        return match ($unit) {
            'g' => $value * 1024 * 1024 * 1024,
            'm' => $value * 1024 * 1024,
            'k' => $value * 1024,
            default => $value,
        };
    }

    /**
     * Get server uptime if available.
     */
    private function getUptime(): ?string
    {
        if (PHP_OS_FAMILY === 'Windows') {
            return null;
        }

        $uptime = @file_get_contents('/proc/uptime');
        if ($uptime) {
            $seconds = (int) explode(' ', $uptime)[0];
            return sprintf(
                '%dd %dh %dm',
                floor($seconds / 86400),
                floor(($seconds % 86400) / 3600),
                floor(($seconds % 3600) / 60)
            );
        }

        return null;
    }
}
