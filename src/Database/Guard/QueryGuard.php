<?php

declare(strict_types=1);

namespace Plugs\Database\Guard;

use Plugs\Exceptions\DatabaseException as PlugsDatabaseException;

class QueryGuard
{
    /**
     * Guard against dangerous queries (e.g. DELETE/UPDATE without WHERE)
     */
    public static function check(string $sql, bool $strictMode = false, \Closure $auditLogger = null): void
    {
        if (preg_match('/^\s*(update|delete)\b/i', $sql) && !preg_match('/\bWHERE\b/i', $sql)) {
            $message = "DANGEROUS QUERY DETECTED (No WHERE clause): " . trim($sql);

            if ($auditLogger) {
                $auditLogger($message, 'ALERT');
            }

            if ($strictMode) {
                throw new PlugsDatabaseException($message);
            }
        }
    }
}
