<?php

declare(strict_types=1);

namespace Plugs\Database\Analysis;

class QueryAnalyzer
{
    private static array $queryStats = [];
    private static bool $enableQueryAnalysis = false;
    private static array $queryAnalysisThresholds = [
        'slow_query_time' => 1.0,    // Seconds
        'n_plus_one_threshold' => 10, // Similar queries in a row
    ];
    private static array $queryLog = [];
    private static bool $loggingQueries = false;

    public static function enable(bool $enable = true): void
    {
        self::$enableQueryAnalysis = $enable;
        self::$loggingQueries = $enable;
    }

    public static function isEnabled(): bool
    {
        return self::$enableQueryAnalysis;
    }

    public static function isLoggingEnabled(): bool
    {
        return self::$loggingQueries;
    }

    public static function configure(array $config): void
    {
        self::$queryAnalysisThresholds = array_merge(self::$queryAnalysisThresholds, $config);
    }

    public static function logQuery(string $sql, array $params, float $time, string $connectionType): void
    {
        if (self::$loggingQueries) {
            self::$queryLog[] = [
                'query' => $sql,
                'bindings' => $params,
                'time' => $time,
                'connection' => $connectionType,
            ];
        }
    }

    public static function analyze(string $sql, array $params, float $executionTime, ?array $backtrace): void
    {
        $normalizedSql = self::normalizeSql($sql);

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

        $location = null;
        if ($backtrace) {
            $location = self::formatBacktrace($backtrace);
            if (!in_array($location, $stats['locations'])) {
                $stats['locations'][] = $location;
            }
        }

        $currentTime = microtime(true);
        if ($stats['last_seen'] && ($currentTime - $stats['last_seen']) < 0.1) {
            $stats['consecutive_count']++;

            if ($stats['consecutive_count'] >= self::$queryAnalysisThresholds['n_plus_one_threshold']) {
                self::logWarning("Potential N+1 query detected", [
                    'query' => $normalizedSql,
                    'consecutive_count' => $stats['consecutive_count'],
                    'location' => $location ?? 'unknown',
                ]);
            }
        } else {
            $stats['consecutive_count'] = 0;
        }
        $stats['last_seen'] = $currentTime;

        if ($executionTime > self::$queryAnalysisThresholds['slow_query_time']) {
            self::logWarning("Slow query detected", [
                'query' => $normalizedSql,
                'execution_time' => $executionTime,
                'location' => $location ?? 'unknown',
            ]);
        }
    }

    public static function normalizeSql(string $sql): string
    {
        $sql = preg_replace('/\s+/', ' ', $sql);
        $sql = preg_replace('/:[a-zA-Z0-9_]+/', ':param', $sql);
        return trim($sql);
    }

    public static function captureBacktrace(string $callerClass): array
    {
        $trace = debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS, 10);
        foreach ($trace as $frame) {
            if (!isset($frame['class']) || $frame['class'] !== $callerClass) {
                return $frame;
            }
        }
        return $trace[0] ?? [];
    }

    private static function formatBacktrace(array $frame): string
    {
        $file = $frame['file'] ?? 'unknown';
        $line = $frame['line'] ?? 0;
        $function = $frame['function'] ?? 'unknown';
        return "{$file}:{$line} ({$function})";
    }

    private static function logWarning(string $message, array $context): void
    {
        error_log(sprintf("[DB Warning] %s: %s", $message, json_encode($context, JSON_UNESCAPED_SLASHES)));
    }

    public static function getReport(): array
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

            if ($stats['max_time'] > self::$queryAnalysisThresholds['slow_query_time']) {
                $report['slow_queries'][] = [
                    'query' => $sql,
                    'max_time' => $stats['max_time'],
                    'avg_time' => $avgTime,
                    'count' => $stats['count'],
                ];
            }

            if ($stats['count'] > self::$queryAnalysisThresholds['n_plus_one_threshold']) {
                $report['n_plus_one_suspects'][] = [
                    'query' => $sql,
                    'count' => $stats['count'],
                    'locations' => $stats['locations'],
                ];
            }
        }

        $sortedStats = self::$queryStats;
        uasort($sortedStats, function ($a, $b) {
            return $b['count'] <=> $a['count'];
        });

        $report['most_frequent'] = array_slice($sortedStats, 0, 10, true);
        $report['query_count'] = $report['total_queries'];
        $report['query_time_ms'] = $report['total_time'] * 1000;

        return $report;
    }

    public static function resetStats(): void
    {
        self::$queryStats = [];
    }

    public static function getLog(): array
    {
        return self::$queryLog;
    }

    public static function clearLog(): void
    {
        self::$queryLog = [];
    }
}
