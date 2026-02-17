<?php

declare(strict_types=1);

namespace Plugs\Database\Optimization;

use Plugs\Database\Connection;

class QueryAnalyzer
{
    protected Connection $connection;

    public function __construct(Connection $connection)
    {
        $this->connection = $connection;
    }

    /**
     * Analyze a query using EXPLAIN.
     */
    public function analyze(string $sql, array $params = []): array
    {
        if (!$this->isAnalyzable($sql)) {
            return [];
        }

        try {
            $explainSql = "EXPLAIN " . $sql;
            $results = $this->connection->fetchAll($explainSql, $params);

            return $this->parseExplain($results);
        } catch (\Throwable $e) {
            // Silently fail analysis if syntax is not supported or other errors occur
            return [
                'error' => $e->getMessage(),
                'sql' => $sql
            ];
        }
    }

    /**
     * Determine if a query is analyzable.
     */
    protected function isAnalyzable(string $sql): bool
    {
        $sql = ltrim($sql);
        return (bool) preg_match('/^(select|update|delete|insert)\b/i', $sql);
    }

    /**
     * Parse EXPLAIN results (MySQL format).
     */
    protected function parseExplain(array $results): array
    {
        $issues = [];
        $analysis = [];

        foreach ($results as $row) {
            $table = $row['table'] ?? 'unknown';
            $type = $row['type'] ?? '';
            $extra = $row['Extra'] ?? '';
            $rows = $row['rows'] ?? 0;
            $possibleKeys = $row['possible_keys'] ?? '';
            $key = $row['key'] ?? '';

            $tableAnalysis = [
                'table' => $table,
                'type' => $type,
                'key' => $key,
                'rows' => $rows,
                'extra' => $extra,
                'points_of_origin' => $possibleKeys,
            ];

            // 1. Full Table Scan
            if ($type === 'ALL') {
                $issues[] = [
                    'severity' => 'CRITICAL',
                    'message' => "Full table scan on `{$table}` detectable.",
                    'suggestion' => "Add an index covering the columns in your WHERE clause."
                ];
            }

            // 2. Slow Join Types
            if (in_array($type, ['index', 'range']) && $rows > 1000) {
                $issues[] = [
                    'severity' => 'WARNING',
                    'message' => "Large range/index scan on `{$table}` ({$rows} rows).",
                    'suggestion' => "Consider narrowing your filters or optimizing the index."
                ];
            }

            // 3. Temporary Table & Filesort
            if (str_contains($extra, 'Using temporary')) {
                $issues[] = [
                    'severity' => 'HIGH',
                    'message' => "Query uses a temporary table for `{$table}`.",
                    'suggestion' => "Possible lack of index for GROUP BY or complex JOINs."
                ];
            }

            if (str_contains($extra, 'Using filesort')) {
                $issues[] = [
                    'severity' => 'HIGH',
                    'message' => "Query uses filesort for `{$table}`.",
                    'suggestion' => "Add an index to the columns used in ORDER BY."
                ];
            }

            // 4. No Key Used
            if (!$key && $type !== 'const' && $type !== 'system') {
                $issues[] = [
                    'severity' => 'MEDIUM',
                    'message' => "No index utilized for query on `{$table}`.",
                    'suggestion' => "Check if the columns in WHERE/JOIN are indexed."
                ];
            }

            $analysis[] = $tableAnalysis;
        }

        return [
            'issues' => $issues,
            'raw' => $analysis,
            'score' => $this->calculateScore($issues)
        ];
    }

    /**
     * Calculate a performance score (0-100).
     */
    protected function calculateScore(array $issues): int
    {
        $score = 100;

        foreach ($issues as $issue) {
            switch ($issue['severity']) {
                case 'CRITICAL':
                    $score -= 40;
                    break;
                case 'HIGH':
                    $score -= 20;
                    break;
                case 'WARNING':
                    $score -= 15;
                    break;
                case 'MEDIUM':
                    $score -= 10;
                    break;
                case 'LOW':
                    $score -= 5;
                    break;
            }
        }

        return max(0, $score);
    }
}
