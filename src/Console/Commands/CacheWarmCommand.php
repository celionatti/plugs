<?php

declare(strict_types=1);

namespace Plugs\Console\Commands;

use Plugs\Console\Command;
use Plugs\Cache\CacheWarmer;

/**
 * CLI command to warm caches on deploy.
 * Usage: php plg cache:warm [warmer]
 */
class CacheWarmCommand extends Command
{
    protected string $signature = 'cache:warm 
        {warmer? : Specific warmer to run (optional)}
        {--all : Run all registered warmers}';

    protected string $description = 'Pre-warm caches for improved performance';

    public function handle(): int
    {
        $this->info("Cache Warming...\n");

        $warmer = CacheWarmer::withDefaults();

        // Allow custom warmers from config
        $customWarmers = config('cache.warmers', []);
        foreach ($customWarmers as $name => $callback) {
            $warmer->register($name, $callback);
        }

        $specificWarmer = $this->argument('warmer');

        if ($specificWarmer) {
            $result = $warmer->warm($specificWarmer);
            $this->outputResult($result);
        } else {
            $results = $warmer->warmAll();
            foreach ($results as $result) {
                $this->outputResult($result);
            }
        }

        $this->info("\nCache warming complete!");
        return 0;
    }

    private function outputResult(array $result): void
    {
        $name = $result['name'] ?? 'unknown';
        $status = $result['status'] ?? 'unknown';

        if ($status === 'success') {
            $keys = $result['keys_warmed'] ?? 0;
            $time = $result['duration_ms'] ?? 0;
            $this->info("  ✓ {$name}: {$keys} keys ({$time}ms)");
        } else {
            $error = $result['error'] ?? 'Unknown error';
            $this->error("  ✗ {$name}: {$error}");
        }
    }
}
