<?php

declare(strict_types=1);

namespace Plugs\Http\Controllers;

use Plugs\Http\ResponseFactory;
use Plugs\Database\Optimization\SlowQueryLogger;
use Plugs\Router\Router;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Throwable;

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
     * Returns the modern Health Dashboard UI.
     */
    public function dashboard(ServerRequestInterface $request): ResponseInterface
    {
        ob_start();
        // Fallback to direct require since this is an internal framework UI
        require dirname(__DIR__, 2) . '/View/debug/health/dashboard.php';
        $content = ob_get_clean() ?: '';

        return ResponseFactory::html($content);
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
        } catch (Throwable $e) {
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
        } catch (Throwable $e) {
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
            'extensions' => get_loaded_extensions(),
            'framework_version' => config('app.version', '1.0.0'),
            'environment' => config('app.env', 'production'),
            'server_software' => $_SERVER['SERVER_SOFTWARE'] ?? 'Unknown',
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
     * Get latest application logs.
     */
    public function logs(ServerRequestInterface $request): ResponseInterface
    {
        $logPath = storage_path('logs/plugs.log');
        $logs = [];

        if (file_exists($logPath)) {
            $maxLines = 100;
            $fileSize = filesize($logPath);
            $readSize = min($fileSize, 50 * 1024); // Read max 50KB

            if ($readSize > 0) {
                $handle = fopen($logPath, 'r');
                fseek($handle, -$readSize, SEEK_END);
                $data = fread($handle, $readSize);
                fclose($handle);

                // Clean potential invalid UTF-8 bytes caused by cutting chunks
                if (function_exists('mb_convert_encoding')) {
                    $data = mb_convert_encoding($data, 'UTF-8', 'UTF-8');
                }

                $lines = explode("\n", $data);

                // Discard the very first item if we didn't read from byte 0, since it's likely a partial line
                if ($fileSize > $readSize && count($lines) > 1) {
                    array_shift($lines);
                }

                $logs = array_slice($lines, -$maxLines);
                $logs = array_filter(array_map('trim', $logs));

                $parsedLogs = array_map(function ($line) {
                    // Basic log parsing [timestamp] Level: Message
                    if (preg_match('/^\[(.*?)\] (.*?): (.*)$/', $line, $matches)) {
                        $level = strtolower($matches[2]);
                        // Handle "local.error" -> "error"
                        if (str_contains($level, '.')) {
                            $parts = explode('.', $level);
                            $level = end($parts);
                        }
                        return [
                            'timestamp' => $matches[1],
                            'level' => $level,
                            'message' => mb_strimwidth($matches[3], 0, 1500, '...'),
                        ];
                    }
                    return [
                        'message' => mb_strimwidth($line, 0, 1500, '...'),
                        'level' => 'info'
                    ];
                }, $logs);

                // Return in reverse chronological order for the dashboard
                $logs = array_reverse($parsedLogs);
            }
        }

        return ResponseFactory::json(['logs' => array_values($logs)]);
    }

    /**
     * Get detailed database information.
     */
    public function database(ServerRequestInterface $request): ResponseInterface
    {
        $tables = [];
        $slowQueries = [];
        $debugDbName = null;
        $debugDbClass = null;
        $debugError = null;

        try {
            // Get table sizes (MySQL specific)
            if (function_exists('db')) {
                $debugDbClass = is_object(db()) ? get_class(db()) : gettype(db());
                $dbName = config('database.connections.mysql.database');
                $debugDbName = $dbName;

                $result = db()->query("
                    SELECT table_name AS `name`, 
                           round(((data_length + index_length) / 1024 / 1024), 2) AS `size_mb`,
                           table_rows AS `rows`
                    FROM information_schema.TABLES 
                    WHERE table_schema = '{$dbName}'
                    ORDER BY (data_length + index_length) DESC
                ");

                $data = $result->fetchAll();
                // Ensure we return a clean array of objects/associative arrays
                $tables = array_map(function ($row) {
                    return [
                        'name' => $row['name'] ?? $row[0] ?? 'Unknown',
                        'size_mb' => $row['size_mb'] ?? $row[1] ?? 0,
                        'rows' => $row['rows'] ?? $row[2] ?? 0,
                    ];
                }, $data);
            } else {
                $debugDbClass = 'function_not_exists';
            }

            // Get slow queries
            $logger = new SlowQueryLogger();
            $slowQueries = $logger->readLogs();
        } catch (Throwable $e) {
            $debugError = $e->getMessage();
            // Log error internally if needed
            if (config('app.debug')) {
                error_log("Database Health Error: " . $e->getMessage());
            }
        }

        return ResponseFactory::json([
            'debug' => [
                'db_name' => $debugDbName,
                'db_class' => $debugDbClass,
                'error' => $debugError,
            ],
            'tables' => $tables,
            'slow_queries' => $slowQueries,
        ]);
    }

    /**
     * Get cache status and keys if possible.
     */
    public function cache_info(ServerRequestInterface $request): ResponseInterface
    {
        $stats = [
            'driver' => config('cache.default', 'file'),
            'hits' => 0,
            'misses' => 0,
            'keys_count' => 0,
        ];

        // Placeholder for driver-specific stats if we had them
        return ResponseFactory::json($stats);
    }

    /**
     * Get all registered routes.
     */
    public function route_map(ServerRequestInterface $request): ResponseInterface
    {
        $router = app(Router::class);
        $routesRaw = $router->getRoutes();
        $routes = [];

        foreach ($routesRaw as $method => $methodRoutes) {
            foreach ($methodRoutes as $route) {
                $routes[] = [
                    'method' => $method,
                    'uri' => $route->getPath(),
                    'name' => $route->getName(),
                    'handler' => $this->formatHandler($route->getHandler()),
                    'middleware' => $route->getMiddleware(),
                ];
            }
        }

        return ResponseFactory::json(['routes' => $routes]);
    }

    /**
     * Get composer dependencies.
     */
    public function dependencies(ServerRequestInterface $request): ResponseInterface
    {
        $composerPath = base_path('composer.json');
        $lockPath = base_path('composer.lock');
        $packages = [];

        if (file_exists($composerPath)) {
            $json = json_decode(file_get_contents($composerPath), true);
            $require = $json['require'] ?? [];
            $requireDev = $json['require-dev'] ?? [];

            foreach ($require as $name => $version) {
                $packages[] = ['name' => $name, 'version' => $version, 'dev' => false];
            }
            foreach ($requireDev as $name => $version) {
                $packages[] = ['name' => $name, 'version' => $version, 'dev' => true];
            }
        }

        return ResponseFactory::json(['packages' => $packages]);
    }

    /**
     * Run a basic security audit.
     */
    public function security_audit(ServerRequestInterface $request): ResponseInterface
    {
        $issues = [];
        $score = 100;
        $metrics = [
            'debug_mode' => 100,
            'storage_rw' => 100,
            'ssl_active' => 100,
            'app_key' => 100,
            'session_secure' => 100,
        ];

        // Check debug mode
        if (config('app.debug', false)) {
            $issues[] = ['severity' => 'warning', 'message' => 'Debug mode is enabled. Ensure this is disabled in production.'];
            $score -= 20;
            $metrics['debug_mode'] = 0;
        }

        // Check storage permissions
        $storagePath = storage_path();
        if (!is_writable($storagePath)) {
            $issues[] = ['severity' => 'critical', 'message' => 'Storage directory is not writable.'];
            $score -= 40;
            $metrics['storage_rw'] = 0;
        }

        // Check SSL
        $isSsl = !empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off';
        if (!$isSsl) {
            $issues[] = ['severity' => 'warning', 'message' => 'Site is not running over HTTPS.'];
            $score -= 15;
            $metrics['ssl_active'] = 0;
        }

        // Check APP_KEY
        $appKey = config('app.key');
        if (empty($appKey) || $appKey === 'base64:unconfigured') {
            $issues[] = ['severity' => 'critical', 'message' => 'Application key is not set or is insecure.'];
            $score -= 30;
            $metrics['app_key'] = 0;
        }

        // Check Session Secure Cookie
        if (!config('session.secure', false) && $isSsl) {
            $issues[] = ['severity' => 'info', 'message' => 'Consider enabling secure session cookies in production.'];
            $score -= 5;
            $metrics['session_secure'] = 50;
        }

        return ResponseFactory::json([
            'score' => max(0, $score),
            'issues' => $issues,
            'metrics' => $metrics,
        ]);
    }

    /**
     * AI analysis of current health telemetry.
     */
    public function ai_analyze(ServerRequestInterface $request): ResponseInterface
    {
        if (!class_exists('\Plugs\AI\Facades\AI')) {
            return ResponseFactory::json(['error' => 'AI module not found.'], 404);
        }

        $data = $this->detailed($request);
        $telemetry = json_decode((string) $data->getBody(), true);

        try {
            $prompt = "Analyze the following system health telemetry and provide optimization suggestions:\n" . json_encode($telemetry, JSON_PRETTY_PRINT);

            // Call dynamically to prevent static analysis / IDE errors about unknown class
            $aiClass = '\Plugs\AI\Facades\AI';
            $analysis = $aiClass::ask($prompt);

            return ResponseFactory::json(['analysis' => $analysis]);
        } catch (Throwable $e) {
            return ResponseFactory::json(['error' => 'AI analysis failed: ' . $e->getMessage()], 500);
        }
    }

    /**
     * Format route handler for display.
     */
    private function formatHandler($handler): string
    {
        if (is_string($handler)) {
            return $handler;
        }
        if (is_array($handler)) {
            $controller = is_object($handler[0]) ? get_class($handler[0]) : $handler[0];
            return "{$controller}@{$handler[1]}";
        }
        if ($handler instanceof \Closure) {
            return 'Closure';
        }
        return 'Unknown';
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
