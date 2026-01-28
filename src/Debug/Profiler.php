<?php

declare(strict_types=1);

namespace Plugs\Debug;

use Plugs\Database\Connection;

/**
 * Performance Profiler
 *
 * Collects and stores performance metrics for debugging and optimization.
 * Tracks request timing, memory usage, database queries, included files, and custom events.
 */
class Profiler
{
    private static ?Profiler $instance = null;

    private float $startTime;
    private int $startMemory;
    private array $meta = [];
    private array $timeline = [];
    private array $views = [];
    private array $logs = [];
    private array $models = [];
    private bool $enabled = false;
    private ?array $currentProfile = null;

    private const STORAGE_DIR = 'storage/framework/profiler/';
    private const MAX_PROFILES = 50;

    private function __construct()
    {
        $this->startTime = microtime(true);
        $this->startMemory = memory_get_usage(true);
    }

    /**
     * Get the current Git info (branch and commit)
     */
    public static function getGitInfo(): array
    {
        $basePath = defined('BASE_PATH') ? BASE_PATH : dirname(__DIR__, 3) . '/';
        $headFile = $basePath . '.git/HEAD';

        if (!file_exists($headFile)) {
            return [];
        }

        $headContent = trim((string) file_get_contents($headFile));

        if (str_starts_with($headContent, 'ref: ')) {
            $branch = substr($headContent, 5);
            $hashFile = $basePath . '.git/' . $branch;
            $hash = file_exists($hashFile) ? trim((string) file_get_contents($hashFile)) : '';
            $branchName = basename($branch);
        } else {
            $branchName = 'Detached';
            $hash = $headContent;
        }

        return [
            'branch' => $branchName,
            'hash' => $hash,
            'short_hash' => substr($hash, 0, 7)
        ];
    }

    public static function getInstance(): self
    {
        if (self::$instance === null) {
            self::$instance = new self();
        }

        return self::$instance;
    }

    /**
     * Start profiling
     */
    public function start(): void
    {
        $this->enabled = true;
        $this->startTime = microtime(true);
        $this->startMemory = memory_get_usage(true);
        $this->timeline = [];
        $this->views = [];
        $this->logs = [];

        // Enable detailed query analysis in Database Connection
        if (class_exists(Connection::class)) {
            Connection::enableQueryAnalysis(true);
        }

        $this->startSegment('total', 'Total Request');
    }

    /**
     * Stop profiling and save results
     */
    public function stop(array $requestInfo = []): array
    {
        if (!$this->enabled) {
            return [];
        }

        $this->stopSegment('total');

        $endTime = microtime(true);
        $duration = ($endTime - $this->startTime) * 1000; // ms

        $memoryPeak = memory_get_peak_usage(true);
        $memoryUsed = memory_get_usage(true) - $this->startMemory;

        $dbReport = class_exists(Connection::class)
            ? Connection::getQueryAnalysisReport()
            : ['queries' => [], 'total_time' => 0, 'query_count' => 0];

        $profile = [
            'id' => uniqid('prof_', true),
            'timestamp' => time(),
            'datetime' => date('Y-m-d H:i:s'),
            'duration' => round($duration, 2),
            'memory' => [
                'peak' => $memoryPeak,
                'peak_formatted' => $this->formatBytes($memoryPeak),
                'used' => $memoryUsed,
                'used_formatted' => $this->formatBytes($memoryUsed),
            ],
            'request' => $this->filterRequestData(array_merge([
                'method' => $_SERVER['REQUEST_METHOD'] ?? 'CLI',
                'uri' => $_SERVER['REQUEST_URI'] ?? '',
                'path' => parse_url($_SERVER['REQUEST_URI'] ?? '', PHP_URL_PATH) ?: '/',
                'ip' => $_SERVER['REMOTE_ADDR'] ?? 'unknown',
                'headers' => $this->getHeaders(),
                'cookies' => $_COOKIE,
                'session' => $_SESSION ?? [],
            ], $requestInfo)),
            'response' => [
                'headers' => $this->getResponseHeaders(),
            ],
            'database' => $dbReport,
            'timeline' => $this->timeline,
            'views' => $this->views,
            'logs' => $this->logs,
            'models' => $this->models,
            'files' => [
                'count' => count(get_included_files()),
                'list' => get_included_files(),
            ],
            'git' => self::getGitInfo(),
            'php' => [
                'version' => PHP_VERSION,
                'sapi' => PHP_SAPI,
            ],
        ];

        $this->currentProfile = $profile;
        $this->save($profile);

        return $profile;
    }

    /**
     * Filter sensitive data from request info
     */
    private function filterRequestData(array $data): array
    {
        $pattern = '/(password|secret|token|key|auth|cred|db|database|connection|csrf)/i';

        foreach ($data as $key => &$value) {
            if (preg_match($pattern, (string) $key)) {
                $value = '******** [masked]';
                continue;
            }

            if (is_array($value)) {
                $value = $this->filterRequestData($value);
            } elseif (is_object($value)) {
                $value = 'Object(' . get_class($value) . ')';
            }
        }

        return $data;
    }

    /**
     * Start a named timeline segment
     */
    public function startSegment(string $name, string $label = ''): void
    {
        if (!$this->enabled) {
            return;
        }

        $this->timeline[$name] = [
            'label' => $label ?: $name,
            'start' => microtime(true),
            'end' => null,
            'duration' => null,
            'memory_start' => memory_get_usage(true),
            'memory_end' => null,
        ];
    }

    /**
     * Stop a named timeline segment
     */
    public function stopSegment(string $name): void
    {
        if (!$this->enabled || !isset($this->timeline[$name])) {
            return;
        }

        $endTime = microtime(true);
        $this->timeline[$name]['end'] = $endTime;
        $this->timeline[$name]['duration'] = round(
            ($endTime - $this->timeline[$name]['start']) * 1000,
            2
        );
        $this->timeline[$name]['memory_end'] = memory_get_usage(true);
    }

    /**
     * Add a view render event
     */
    public function addView(string $name, float $duration): void
    {
        if (!$this->enabled) {
            // Self-enable if we are in a request context but start() wasn't called
            // This can happen if ViewEngine is initialized early
            if (isset($_SERVER['REQUEST_METHOD'])) {
                $this->enabled = true;
            } else {
                return;
            }
        }

        $this->views[] = [
            'name' => $name,
            'duration' => round($duration, 2),
            'time' => microtime(true),
        ];

        // Also add to timeline for visibility
        $this->timeline['view_' . uniqid()] = [
            'label' => 'View: ' . $name,
            'start' => microtime(true) - ($duration / 1000),
            'end' => microtime(true),
            'duration' => round($duration, 2),
            'memory_start' => memory_get_usage(true),
            'memory_end' => memory_get_usage(true),
        ];
    }

    /**
     * Add a log entry
     */
    public function log(string $level, string $message, array $context = []): void
    {
        if (!$this->enabled) {
            return;
        }

        $this->logs[] = [
            'level' => $level,
            'message' => $message,
            'context' => $context,
            'timestamp' => microtime(true),
            'time_offset' => $this->getElapsedTime(),
        ];
    }

    /**
     * Track a database model event
     */
    public function recordModelEvent(string $model, string $event): void
    {
        if (!$this->enabled) {
            return;
        }

        $this->models[] = [
            'model' => $model,
            'event' => $event,
            'time' => microtime(true),
            'time_offset' => $this->getElapsedTime(),
        ];
    }

    /**
     * Get the current (or last) profile
     */
    public function getCurrentProfile(): ?array
    {
        return $this->currentProfile;
    }

    /**
     * Get all saved profiles
     */
    public static function getProfiles(int $limit = 50): array
    {
        $storageDir = self::getStorageDir();

        if (!is_dir($storageDir)) {
            return [];
        }

        $files = glob($storageDir . '*.json');

        // Sort by modification time (newest first)
        usort($files, fn($a, $b) => filemtime($b) <=> filemtime($a));

        $profiles = [];
        foreach (array_slice($files, 0, $limit) as $file) {
            $content = file_get_contents($file);
            if ($content) {
                $profile = json_decode($content, true);
                if ($profile) {
                    $profiles[] = $profile;
                }
            }
        }

        return $profiles;
    }

    /**
     * Get a single profile by ID
     */
    public static function getProfile(string $id): ?array
    {
        $storageDir = self::getStorageDir();
        $file = $storageDir . $id . '.json';

        if (!file_exists($file)) {
            // Check if it's in the current instance
            if (self::$instance && self::$instance->currentProfile && self::$instance->currentProfile['id'] === $id) {
                return self::$instance->currentProfile;
            }
            return null;
        }

        $content = file_get_contents($file);

        return $content ? json_decode($content, true) : null;
    }

    /**
     * Delete a profile by ID
     */
    public static function deleteProfile(string $id): bool
    {
        $storageDir = self::getStorageDir();
        $file = $storageDir . $id . '.json';

        if (file_exists($file)) {
            return unlink($file);
        }

        return false;
    }

    /**
     * Clear all profiles
     */
    public static function clearProfiles(): int
    {
        $storageDir = self::getStorageDir();

        if (!is_dir($storageDir)) {
            return 0;
        }

        $files = glob($storageDir . '*.json');
        $deleted = 0;

        foreach ($files as $file) {
            if (unlink($file)) {
                $deleted++;
            }
        }

        return $deleted;
    }

    /**
     * Check if profiler is enabled
     */
    public function isEnabled(): bool
    {
        return $this->enabled;
    }

    /**
     * Get elapsed time since start
     */
    public function getElapsedTime(): float
    {
        return round((microtime(true) - $this->startTime) * 1000, 2);
    }

    /**
     * Save profile to storage
     */
    private function save(array $profile): void
    {
        $storageDir = self::getStorageDir();

        if (!is_dir($storageDir)) {
            mkdir($storageDir, 0755, true);
        }

        // Clean up old files (keep last MAX_PROFILES)
        $files = glob($storageDir . '*.json');
        if (count($files) > self::MAX_PROFILES) {
            // Sort by modification time
            array_multisort(array_map('filemtime', $files), SORT_ASC, $files);
            $toDelete = array_slice($files, 0, count($files) - self::MAX_PROFILES);
            foreach ($toDelete as $file) {
                @unlink($file);
            }
        }

        $filename = $storageDir . $profile['id'] . '.json';
        file_put_contents($filename, json_encode($profile, JSON_PRETTY_PRINT));
    }

    /**
     * Get storage directory path
     */
    private static function getStorageDir(): string
    {
        $basePath = defined('BASE_PATH') ? BASE_PATH : dirname(__DIR__, 3) . '/';

        return $basePath . self::STORAGE_DIR;
    }

    /**
     * Get request headers
     */
    private function getHeaders(): array
    {
        if (function_exists('getallheaders')) {
            return getallheaders();
        }

        $headers = [];
        foreach ($_SERVER as $name => $value) {
            if (str_starts_with($name, 'HTTP_')) {
                $headers[str_replace(' ', '-', ucwords(strtolower(str_replace('_', ' ', substr($name, 5)))))] = $value;
            }
        }

        return $headers;
    }

    /**
     * Get response headers
     */
    private function getResponseHeaders(): array
    {
        $rawHeaders = headers_list();
        $headers = [];
        foreach ($rawHeaders as $header) {
            $parts = explode(':', $header, 2);
            if (count($parts) === 2) {
                $headers[trim($parts[0])] = trim($parts[1]);
            }
        }

        return $headers;
    }

    /**
     * Format bytes to human readable
     */
    private function formatBytes(float|int $bytes, int $precision = 2): string
    {
        $units = ['B', 'KB', 'MB', 'GB', 'TB'];
        $bytes = max($bytes, 0);
        $pow = floor(($bytes ? log($bytes) : 0) / log(1024));
        $pow = min($pow, count($units) - 1);
        $bytes /= (1 << (10 * $pow));

        return round($bytes, $precision) . ' ' . $units[$pow];
    }
}
