<?php

declare(strict_types=1);

namespace Plugs\Metrics;

/**
 * Metrics Collector for application monitoring.
 * Provides Prometheus-compatible output.
 */
class MetricsCollector
{
    private static array $metrics = [];
    private static float $requestStart;
    private static int $requestMemoryStart;

    /**
     * Start request tracking.
     */
    public static function startRequest(): void
    {
        self::$requestStart = microtime(true);
        self::$requestMemoryStart = memory_get_usage();
    }

    /**
     * End request tracking and record metrics.
     */
    public static function endRequest(string $route = 'unknown', string $method = 'GET', int $statusCode = 200): void
    {
        $duration = microtime(true) - self::$requestStart;
        $memoryUsed = memory_get_usage() - self::$requestMemoryStart;

        self::increment('http_requests_total', [
            'route' => $route,
            'method' => $method,
            'status' => (string) $statusCode,
        ]);

        self::observe('http_request_duration_seconds', $duration, [
            'route' => $route,
            'method' => $method,
        ]);

        self::set('http_request_memory_bytes', $memoryUsed, [
            'route' => $route,
        ]);
    }

    /**
     * Increment a counter metric.
     */
    public static function increment(string $name, array $labels = [], float $value = 1): void
    {
        $key = self::buildKey($name, $labels);

        if (!isset(self::$metrics[$name])) {
            self::$metrics[$name] = [
                'type' => 'counter',
                'values' => [],
            ];
        }

        if (!isset(self::$metrics[$name]['values'][$key])) {
            self::$metrics[$name]['values'][$key] = ['labels' => $labels, 'value' => 0];
        }

        self::$metrics[$name]['values'][$key]['value'] += $value;
    }

    /**
     * Observe a histogram/summary value.
     */
    public static function observe(string $name, float $value, array $labels = []): void
    {
        $key = self::buildKey($name, $labels);

        if (!isset(self::$metrics[$name])) {
            self::$metrics[$name] = [
                'type' => 'histogram',
                'values' => [],
            ];
        }

        if (!isset(self::$metrics[$name]['values'][$key])) {
            self::$metrics[$name]['values'][$key] = [
                'labels' => $labels,
                'count' => 0,
                'sum' => 0,
                'min' => PHP_FLOAT_MAX,
                'max' => 0,
            ];
        }

        $m = &self::$metrics[$name]['values'][$key];
        $m['count']++;
        $m['sum'] += $value;
        $m['min'] = min($m['min'], $value);
        $m['max'] = max($m['max'], $value);
    }

    /**
     * Set a gauge metric.
     */
    public static function set(string $name, float $value, array $labels = []): void
    {
        $key = self::buildKey($name, $labels);

        if (!isset(self::$metrics[$name])) {
            self::$metrics[$name] = [
                'type' => 'gauge',
                'values' => [],
            ];
        }

        self::$metrics[$name]['values'][$key] = ['labels' => $labels, 'value' => $value];
    }

    /**
     * Get all metrics in Prometheus text format.
     */
    public static function prometheus(): string
    {
        $output = [];

        // Add system metrics
        self::collectSystemMetrics();

        foreach (self::$metrics as $name => $metric) {
            $output[] = "# TYPE {$name} {$metric['type']}";

            foreach ($metric['values'] as $data) {
                $labelStr = self::formatLabels($data['labels']);

                if ($metric['type'] === 'histogram') {
                    $output[] = "{$name}_count{$labelStr} {$data['count']}";
                    $output[] = "{$name}_sum{$labelStr} {$data['sum']}";
                } else {
                    $output[] = "{$name}{$labelStr} {$data['value']}";
                }
            }
        }

        return implode("\n", $output) . "\n";
    }

    /**
     * Get metrics as JSON.
     */
    public static function json(): array
    {
        self::collectSystemMetrics();
        return self::$metrics;
    }

    /**
     * Collect system-level metrics.
     */
    private static function collectSystemMetrics(): void
    {
        // Memory
        self::set('php_memory_usage_bytes', memory_get_usage(true));
        self::set('php_memory_peak_bytes', memory_get_peak_usage(true));

        // CPU (if available)
        if (function_exists('sys_getloadavg')) {
            $load = sys_getloadavg();
            self::set('system_load_1m', $load[0] ?? 0);
            self::set('system_load_5m', $load[1] ?? 0);
            self::set('system_load_15m', $load[2] ?? 0);
        }

        // Process info
        self::set('php_info', 1, ['version' => PHP_VERSION]);
    }

    /**
     * Build a unique key for labels.
     */
    private static function buildKey(string $name, array $labels): string
    {
        ksort($labels);
        return $name . ':' . json_encode($labels);
    }

    /**
     * Format labels for Prometheus output.
     */
    private static function formatLabels(array $labels): string
    {
        if (empty($labels)) {
            return '';
        }

        $parts = [];
        foreach ($labels as $key => $value) {
            $parts[] = "{$key}=\"{$value}\"";
        }

        return '{' . implode(',', $parts) . '}';
    }

    /**
     * Reset all metrics (for testing).
     */
    public static function reset(): void
    {
        self::$metrics = [];
    }
}
