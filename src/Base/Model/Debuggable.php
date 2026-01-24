<?php

declare(strict_types=1);

namespace Plugs\Base\Model;

/*
|--------------------------------------------------------------------------
| Debuggable Trait - Enhanced Query Logging for Models
|--------------------------------------------------------------------------
|
| Add this trait to your models or base model class to enable automatic
| query tracking and performance monitoring.
|--------------------------------------------------------------------------
*/

trait Debuggable
{
    protected static $debugEnabled = false;
    protected static $slowQueryThreshold = 0.1; // 100ms
    protected static $queryWarnings = [];

    /**
     * Enable debug mode for this model
     */
    public static function enableDebug(): void
    {
        static::$debugEnabled = true;
        static::enableQueryLog();
    }

    /**
     * Disable debug mode
     */
    public static function disableDebug(): void
    {
        static::$debugEnabled = false;
        static::disableQueryLog();
    }

    /**
     * Get debug status
     */
    public static function isDebugging(): bool
    {
        return static::$debugEnabled;
    }

    /**
     * Set slow query threshold (in seconds)
     */
    public static function setSlowQueryThreshold(float $seconds): void
    {
        static::$slowQueryThreshold = $seconds;
    }

    /**
     * Get query statistics with performance metrics
     */
    public static function getDebugStats(): array
    {
        $queries = static::getQueryLog();
        $slowQueries = [];
        $totalTime = 0;

        foreach ($queries as $query) {
            $time = $query['time'] ?? 0;
            $totalTime += $time;

            if ($time > static::$slowQueryThreshold) {
                $slowQueries[] = $query;
            }
        }

        return [
            'total_queries' => count($queries),
            'total_time' => $totalTime,
            'average_time' => count($queries) > 0 ? $totalTime / count($queries) : 0,
            'slow_queries' => count($slowQueries),
            'slow_query_threshold' => static::$slowQueryThreshold,
            'queries' => $queries,
            'warnings' => static::$queryWarnings,
        ];
    }

    /**
     * Dump queries executed by this model
     */
    public static function dumpQueries(bool $die = true): void
    {
        $stats = static::getDebugStats();

        if (function_exists('dq')) {
            dq($die);
        } else {
            dd($stats);
        }
    }

    /**
     * Analyze query performance
     */
    public static function analyzePerformance(): array
    {
        $stats = static::getDebugStats();
        $analysis = [
            'status' => 'good',
            'recommendations' => [],
        ];

        // Check query count
        if ($stats['total_queries'] > 20) {
            $analysis['status'] = 'critical';
            $analysis['recommendations'][] = 'Too many queries (' . $stats['total_queries'] . '). Use eager loading with with().';
        } elseif ($stats['total_queries'] > 10) {
            $analysis['status'] = 'warning';
            $analysis['recommendations'][] = 'High query count (' . $stats['total_queries'] . '). Consider optimization.';
        }

        // Check slow queries
        if ($stats['slow_queries'] > 0) {
            $analysis['status'] = $analysis['status'] === 'critical' ? 'critical' : 'warning';
            $analysis['recommendations'][] = 'Found ' . $stats['slow_queries'] . ' slow queries. Add indexes or optimize queries.';
        }

        // Check for N+1
        // $nPlusOne = $this->detectNPlusOne($stats['queries']);
        $nPlusOne = self::detectNPlusOne($stats['queries']);
        if ($nPlusOne['detected']) {
            $analysis['status'] = 'critical';
            $analysis['recommendations'][] = 'N+1 problem detected with ' . $nPlusOne['count'] . ' similar queries. Use eager loading.';
        }

        // Check total time
        if ($stats['total_time'] > 1.0) {
            $analysis['status'] = 'critical';
            $analysis['recommendations'][] = 'Total query time is very high (' . number_format($stats['total_time'] * 1000, 2) . 'ms). Optimize queries and add caching.';
        } elseif ($stats['total_time'] > 0.5) {
            $analysis['status'] = $analysis['status'] === 'good' ? 'warning' : $analysis['status'];
            $analysis['recommendations'][] = 'Query time is elevated (' . number_format($stats['total_time'] * 1000, 2) . 'ms). Consider optimization.';
        }

        $analysis['stats'] = $stats;

        return $analysis;
    }

    /**
     * Detect N+1 query problems
     */
    protected static function detectNPlusOne(array $queries): array
    {
        if (empty($queries)) {
            return ['detected' => false, 'count' => 0];
        }

        $patterns = [];

        foreach ($queries as $query) {
            $sql = $query['query'] ?? '';

            if (!is_string($sql)) {
                continue;
            }

            // Normalize query
            $normalized = preg_replace('/\b\d+\b/', '?', $sql);
            $normalized = preg_replace('/(IN\s*\([^)]+\))/i', 'IN (?)', $normalized);
            $normalized = preg_replace('/(["\'])(?:(?=(\\?))\2.)*?\1/', '?', $normalized);

            if (!isset($patterns[$normalized])) {
                $patterns[$normalized] = 0;
            }
            $patterns[$normalized]++;
        }

        // If same pattern appears more than 5 times, it's likely N+1
        foreach ($patterns as $count) {
            if ($count > 5) {
                return ['detected' => true, 'count' => $count];
            }
        }

        return ['detected' => false, 'count' => 0];
    }

    /**
     * Get memory usage statistics
     */
    public static function getMemoryStats(): array
    {
        return [
            'current_usage' => memory_get_usage(true),
            'peak_usage' => memory_get_peak_usage(true),
            'limit' => ini_get('memory_limit'),
        ];
    }

    /**
     * Profile a callback and return results with stats
     */
    public static function profile(callable $callback): array
    {
        static::flushQueryLog();
        static::enableDebug();

        $startTime = microtime(true);
        $startMemory = memory_get_usage(true);

        $result = $callback();

        $endTime = microtime(true);
        $endMemory = memory_get_usage(true);

        $stats = static::getDebugStats();

        return [
            'result' => $result,
            'execution_time' => $endTime - $startTime,
            'memory_used' => $endMemory - $startMemory,
            'queries' => $stats['queries'],
            'query_count' => $stats['total_queries'],
            'query_time' => $stats['total_time'],
        ];
    }

    /**
     * Dump this model instance with queries
     */
    public function dump(bool $die = true): void
    {
        if (function_exists('dm')) {
            dm($this, $die);
        } else {
            if ($die) {
                dd($this);
            } else {
                d($this);
            }
        }
    }

    /**
     * Print debug information to console log
     */
    public static function log(string $message, $data = null): void
    {
        $timestamp = date('Y-m-d H:i:s');
        $class = static::class;

        error_log("[$timestamp] [$class] $message");

        if ($data !== null) {
            error_log(print_r($data, true));
        }
    }
}
