<?php

declare(strict_types=1);

namespace Plugs\Database\Optimization;

class IndexSuggester
{
    /**
     * Suggest indexes based on SQL query structure.
     */
    public function suggest(string $sql): array
    {
        $suggestions = [];
        $table = $this->extractTable($sql);

        if (!$table) {
            return [];
        }

        // 1. Analyze WHERE clauses
        $whereColumns = $this->extractWhereColumns($sql);
        if (!empty($whereColumns)) {
            $suggestions[] = [
                'table' => $table,
                'columns' => $whereColumns,
                'reason' => 'Filtering optimization',
                'sql' => "CREATE INDEX idx_{$table}_" . implode('_', $whereColumns) . " ON {$table} (" . implode(', ', $whereColumns) . ");"
            ];
        }

        // 2. Analyze JOIN clauses
        $joinColumns = $this->extractJoinColumns($sql);
        foreach ($joinColumns as $joinTable => $cols) {
            $suggestions[] = [
                'table' => $joinTable,
                'columns' => $cols,
                'reason' => 'Join optimization',
                'sql' => "CREATE INDEX idx_{$joinTable}_" . implode('_', $cols) . " ON {$joinTable} (" . implode(', ', $cols) . ");"
            ];
        }

        // 3. Analyze ORDER BY
        $orderColumns = $this->extractOrderByColumns($sql);
        if (!empty($orderColumns)) {
            $suggestions[] = [
                'table' => $table,
                'columns' => $orderColumns,
                'reason' => 'Sorting optimization',
                'sql' => "CREATE INDEX idx_{$table}_sort_" . implode('_', $orderColumns) . " ON {$table} (" . implode(', ', $orderColumns) . ");"
            ];
        }

        return $suggestions;
    }

    protected function extractTable(string $sql): ?string
    {
        if (preg_match('/FROM\s+([a-z0-9_`]+)/i', $sql, $matches)) {
            return trim($matches[1], '`');
        }
        return null;
    }

    protected function extractWhereColumns(string $sql): array
    {
        if (preg_match('/WHERE\s+(.*?)(?:ORDER|GROUP|LIMIT|$)/is', $sql, $matches)) {
            $whereClause = $matches[1];
            // Match column = ?, column IN (...), etc.
            preg_match_all('/([a-z0-9_`]+)\s*(?:=|<>|!=|LIKE|IN|IS)/i', $whereClause, $matches);
            return array_unique(array_map(fn($c) => trim($c, '`'), $matches[1]));
        }
        return [];
    }

    protected function extractJoinColumns(string $sql): array
    {
        $joins = [];
        if (preg_match_all('/JOIN\s+([a-z0-9_`]+)\s+ON\s+(.*?)(?:JOIN|WHERE|ORDER|GROUP|LIMIT|$)/is', $sql, $matches, PREG_SET_ORDER)) {
            foreach ($matches as $match) {
                $table = trim($match[1], '`');
                $onClause = $match[2];
                preg_match_all('/' . preg_quote($table) . '\.([a-z0-9_`]+)/i', $onClause, $colMatches);
                if (!empty($colMatches[1])) {
                    $joins[$table] = array_unique(array_map(fn($c) => trim($c, '`'), $colMatches[1]));
                }
            }
        }
        return $joins;
    }

    protected function extractOrderByColumns(string $sql): array
    {
        if (preg_match('/ORDER\s+BY\s+(.*?)(?:LIMIT|$)/is', $sql, $matches)) {
            $orderClause = $matches[1];
            $parts = explode(',', $orderClause);
            $cols = [];
            foreach ($parts as $part) {
                if (preg_match('/([a-z0-9_`]+)(?:\s+(?:ASC|DESC))?/i', trim($part), $m)) {
                    $cols[] = trim($m[1], '`');
                }
            }
            return array_unique($cols);
        }
        return [];
    }
}
