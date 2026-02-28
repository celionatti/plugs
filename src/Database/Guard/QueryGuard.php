<?php

declare(strict_types=1);

namespace Plugs\Database\Guard;

use Plugs\Exceptions\DatabaseException as PlugsDatabaseException;

class QueryGuard
{
    /**
     * Guard against dangerous queries (e.g. DELETE/UPDATE without WHERE) and advanced SQL injection payloads natively.
     */
    public static function check(string $sql, bool $strictMode = false, \Closure $auditLogger = null): void
    {
        $message = null;

        if (preg_match('/^\s*(update|delete)\b/i', $sql) && !preg_match('/\bWHERE\b/i', $sql)) {
            $message = "DANGEROUS QUERY DETECTED (No WHERE clause): " . trim($sql);
        } elseif (preg_match('/(\bwaitfor\b\s+\bdelay\b|\bbenchmark\s*\()/i', $sql)) {
            $message = "DANGEROUS QUERY DETECTED (Suspicious sleep payload): " . trim($sql);
        } elseif (preg_match('/(\bunion\b.*\bselect\b)/i', $sql)) {
            $message = "DANGEROUS QUERY DETECTED (UNION SELECT payload): " . trim($sql);
        }

        if ($message !== null) {
            if ($auditLogger) {
                $auditLogger($message, 'ALERT');
            }

            if ($strictMode) {
                throw new PlugsDatabaseException($message);
            }
        }
    }
}
