<?php

declare(strict_types=1);

namespace Plugs\Log;

use Psr\Log\LogLevel;

/*
|--------------------------------------------------------------------------
| RotatingFileLogger
|--------------------------------------------------------------------------
|
| A daily rotating file logger that creates a new log file each day and
| automatically prunes old log files beyond the configured retention.
|
| Usage:
|   $logger = new RotatingFileLogger(storage_path('logs/plugs.log'), 14);
|   $logger->info('User logged in', ['user_id' => 42]);
|
| This creates files like:
|   storage/logs/plugs-2026-02-23.log
|   storage/logs/plugs-2026-02-22.log
|   ...
*/

class RotatingFileLogger extends Logger
{
    private string $basePath;
    private string $baseFilename;
    private string $extension;
    private int $maxFiles;
    private ?string $currentDate = null;
    private ?string $currentLogPath = null;

    /**
     * @param string $logPath   The base log file path (e.g. storage/logs/plugs.log)
     * @param int    $maxFiles  Maximum number of daily log files to keep (0 = unlimited)
     */
    public function __construct(string $logPath, int $maxFiles = 14)
    {
        $this->maxFiles = $maxFiles;
        $this->basePath = dirname($logPath);
        $this->extension = pathinfo($logPath, PATHINFO_EXTENSION) ?: 'log';
        $this->baseFilename = basename($logPath, '.' . $this->extension);

        // Ensure the directory exists
        if (!is_dir($this->basePath)) {
            mkdir($this->basePath, 0755, true);
        }

        // Initialize with today's path
        $this->resolveLogPath();

        // Parent constructor expects the path — we pass the resolved daily path
        parent::__construct($this->currentLogPath);
    }

    /**
     * @inheritdoc
     */
    public function log($level, $message, array $context = []): void
    {
        // Check if the date has rolled over (long-running processes)
        $this->resolveLogPath();

        // Write using parent's log method
        parent::log($level, $message, $context);

        // Prune old files (only check once per day)
        $this->pruneOldFiles();
    }

    /**
     * Resolve the current log file path based on today's date.
     * If the date has changed, update the path and re-initialize.
     */
    private function resolveLogPath(): void
    {
        $today = date('Y-m-d');

        if ($this->currentDate === $today && $this->currentLogPath !== null) {
            return;
        }

        $this->currentDate = $today;
        $this->currentLogPath = sprintf(
            '%s/%s-%s.%s',
            $this->basePath,
            $this->baseFilename,
            $today,
            $this->extension
        );

        // Update the parent's log path via reflection (since it's private)
        $this->setLogPath($this->currentLogPath);
    }

    /**
     * Set the parent Logger's logPath.
     */
    private function setLogPath(string $path): void
    {
        $reflection = new \ReflectionClass(Logger::class);
        $property = $reflection->getProperty('logPath');
        $property->setAccessible(true);
        $property->setValue($this, $path);
    }

    /**
     * Remove old log files beyond the max retention.
     */
    private function pruneOldFiles(): void
    {
        if ($this->maxFiles <= 0) {
            return;
        }

        // Only prune once per request to avoid filesystem overhead
        static $pruned = [];
        $key = $this->basePath . '/' . $this->baseFilename;
        if (isset($pruned[$key])) {
            return;
        }
        $pruned[$key] = true;

        $pattern = sprintf(
            '%s/%s-*.%s',
            $this->basePath,
            $this->baseFilename,
            $this->extension
        );

        $files = glob($pattern);

        if ($files === false || count($files) <= $this->maxFiles) {
            return;
        }

        // Sort ascending by filename (date is embedded, so alphabetical = chronological)
        sort($files);

        // Remove oldest files beyond the limit
        $toDelete = array_slice($files, 0, count($files) - $this->maxFiles);

        foreach ($toDelete as $file) {
            @unlink($file);
        }
    }

    /**
     * Get the current log file path.
     */
    public function getCurrentLogPath(): string
    {
        $this->resolveLogPath();
        return $this->currentLogPath;
    }

    /**
     * Get all existing log files for this logger.
     *
     * @return array<string> Sorted file paths (newest first)
     */
    public function getLogFiles(): array
    {
        $pattern = sprintf(
            '%s/%s-*.%s',
            $this->basePath,
            $this->baseFilename,
            $this->extension
        );

        $files = glob($pattern) ?: [];
        rsort($files); // Newest first

        return $files;
    }
}
