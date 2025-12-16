<?php

declare(strict_types=1);

namespace Plugs\Debug;

use Plugs\Database\Connection;

class Profiler
{
    private static ?Profiler $instance = null;

    private float $startTime;
    private array $meta = [];
    private bool $enabled = false;

    private function __construct()
    {
        $this->startTime = microtime(true);
    }

    public static function getInstance(): self
    {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    public function start(): void
    {
        $this->enabled = true;
        $this->startTime = microtime(true);

        // Enable detailed query analysis in Database Connection
        Connection::enableQueryAnalysis(true);
    }

    public function stop(array $requestInfo = []): array
    {
        if (!$this->enabled) {
            return [];
        }

        $endTime = microtime(true);
        $duration = ($endTime - $this->startTime) * 1000; // ms

        $memoryPeak = memory_get_peak_usage(true);

        $dbReport = Connection::getQueryAnalysisReport();

        $profile = [
            'id' => uniqid('prof_', true),
            'timestamp' => time(),
            'datetime' => date('Y-m-d H:i:s'),
            'duration' => round($duration, 2),
            'memory_peak' => $memoryPeak,
            'memory_peak_formatted' => $this->formatBytes($memoryPeak),
            'request' => $requestInfo,
            'database' => $dbReport,
        ];

        $this->save($profile);

        return $profile;
    }

    private function save(array $profile): void
    {
        $storageDir = BASE_PATH . 'storage/framework/profiler/';

        if (!is_dir($storageDir)) {
            mkdir($storageDir, 0755, true);
        }

        // Clean up old files (keep last 50)
        $files = glob($storageDir . '*.json');
        if (count($files) > 50) {
            // Sort by modification time
            array_multisort(array_map('filemtime', $files), SORT_ASC, $files);
            $toDelete = array_slice($files, 0, count($files) - 50);
            foreach ($toDelete as $file) {
                @unlink($file);
            }
        }

        $filename = $storageDir . $profile['id'] . '.json';
        file_put_contents($filename, json_encode($profile, JSON_PRETTY_PRINT));
    }

    private function formatBytes(int $bytes, int $precision = 2): string
    {
        $units = ['B', 'KB', 'MB', 'GB', 'TB'];
        $bytes = max($bytes, 0);
        $pow = floor(($bytes ? log($bytes) : 0) / log(1024));
        $pow = min($pow, count($units) - 1);
        $bytes /= (1 << (10 * $pow));

        return round($bytes, $precision) . ' ' . $units[$pow];
    }
}
