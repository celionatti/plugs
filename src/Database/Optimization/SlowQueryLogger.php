<?php

declare(strict_types=1);

namespace Plugs\Database\Optimization;

class SlowQueryLogger
{
    protected string $logPath;

    public function __construct(?string $logPath = null)
    {
        $this->logPath = $logPath ?: 'storage/logs/slow_queries.json';
    }

    /**
     * Log a slow query.
     */
    public function log(array $data): void
    {
        $logDir = dirname($this->logPath);
        if (!is_dir($logDir)) {
            @mkdir($logDir, 0755, true);
        }

        $logEntry = array_merge([
            'timestamp' => date('Y-m-d H:i:s'),
            'pid' => getmypid(),
        ], $data);

        $currentLogs = $this->readLogs();
        $currentLogs[] = $logEntry;

        // Keep only last 1000 logs
        if (count($currentLogs) > 1000) {
            array_shift($currentLogs);
        }

        file_put_contents($this->logPath, json_encode($currentLogs, JSON_PRETTY_PRINT));
    }

    /**
     * Read existing logs.
     */
    public function readLogs(): array
    {
        if (!file_exists($this->logPath)) {
            return [];
        }

        $content = file_get_contents($this->logPath);
        return json_decode($content, true) ?: [];
    }

    /**
     * Clear logs.
     */
    public function clear(): void
    {
        if (file_exists($this->logPath)) {
            @unlink($this->logPath);
        }
    }
}
