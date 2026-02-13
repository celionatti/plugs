<?php

declare(strict_types=1);

namespace Plugs\Console\Commands;

use Plugs\Console\Command;
use Plugs\Support\OpCacheManager;

class OpCacheStatusCommand extends Command
{
    protected string $name = 'opcache:status';
    protected string $description = 'Show OPcache status and configuration.';

    public function handle(): int
    {
        $manager = new OpCacheManager();

        if (!$manager->isEnabled()) {
            $this->output->warning('OPcache is not enabled.');

            // Show config anyway
            $this->displayConfig($manager);

            return 1;
        }

        $status = $manager->getStatus();

        $this->output->title('OPcache Status');

        if (empty($status)) {
            $this->output->error('Could not retrieve OPcache status.');

            return 1;
        }

        // Memory Usage
        $memory = $status['memory_usage'] ?? [];
        $this->output->section('Memory Usage');
        $this->output->table(
            ['Metric', 'Value'],
            [
                ['Used Memory', $this->formatBytes($memory['used_memory'] ?? 0)],
                ['Free Memory', $this->formatBytes($memory['free_memory'] ?? 0)],
                ['Wasted Memory', $this->formatBytes($memory['wasted_memory'] ?? 0)],
                ['Current Wasted %', number_format($memory['current_wasted_percentage'] ?? 0, 2) . '%'],
            ]
        );

        // Statistics
        $stats = $status['opcache_statistics'] ?? [];
        $this->output->section('Statistics');
        $this->output->table(
            ['Metric', 'Value'],
            [
                ['Num Cached Scripts', $stats['num_cached_scripts'] ?? 0],
                ['Num Cached Keys', $stats['num_cached_keys'] ?? 0],
                ['Max Cached Keys', $stats['max_cached_keys'] ?? 0],
                ['Hits', $stats['hits'] ?? 0],
                ['Misses', $stats['misses'] ?? 0],
                ['Blacklist Misses', $stats['blacklist_misses'] ?? 0],
                ['Hit Rate', number_format($stats['opcache_hit_rate'] ?? 0, 2) . '%'],
            ]
        );

        $this->displayConfig($manager);

        return 0;
    }

    private function displayConfig(OpCacheManager $manager): void
    {
        $config = $manager->getConfig();
        $directives = $config['directives'] ?? [];

        if (empty($directives)) {
            return;
        }

        $this->output->section('Configuration');

        $rows = [];
        foreach ($directives as $key => $value) {
            $rows[] = [$key, is_bool($value) ? ($value ? 'true' : 'false') : $value];
        }

        $this->output->table(['Directive', 'Value'], $rows);
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
