<?php

declare(strict_types=1);

namespace Modules\Admin\Services;

use Plugs\Log\Logger;

class AdminLogService
{
    protected Logger $logger;

    public function __construct(Logger $logger = null)
    {
        $this->logger = $logger ?: app('log');
    }

    /**
     * Get the latest log entries.
     */
    public function getLatestLogs(int $limit = 100): array
    {
        // Assuming the logger has a method to get logs or we read the file
        // For now, let's assume it can return some data or we implement a simple file reader
        $logFile = storage_path('logs/plugs.log');
        
        if (!file_exists($logFile)) {
            return [];
        }

        $lines = file($logFile);
        $lines = array_slice($lines, -$limit);
        
        return array_map(function($line) {
            return ['message' => trim($line), 'timestamp' => date('Y-m-d H:i:s')];
        }, $lines);
    }

    /**
     * Clear all system logs.
     */
    public function clearLogs(): bool
    {
        $logFile = storage_path('logs/plugs.log');
        
        if (file_exists($logFile)) {
            return file_put_contents($logFile, '') !== false;
        }

        return true;
    }
}
