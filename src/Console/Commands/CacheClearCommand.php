<?php

declare(strict_types=1);

namespace Plugs\Console\Commands;

/*
|--------------------------------------------------------------------------
| Make: CacheClear Command
|--------------------------------------------------------------------------
*/

use Plugs\Console\Command;
use Plugs\Console\Support\Filesystem;

class CacheClearCommand extends Command
{
    protected string $description = 'Clear the application cache';

    public function handle(): int
    {
        $this->checkpoint('start');
        $this->advancedHeader('Cache Management', 'Internal Framework Cache Purge');

        $cachePath = storage_path('cache');

        $this->section('General Cache');

        $this->task('Clearing application cache', function () use ($cachePath) {
            if (Filesystem::isDirectory($cachePath)) {
                $items = array_diff(scandir($cachePath), ['.', '..']);
                foreach ($items as $item) {
                    $path = $cachePath . DIRECTORY_SEPARATOR . $item;
                    if (Filesystem::isDirectory($path)) {
                        Filesystem::deleteDirectory($path);
                    } else {
                        unlink($path);
                    }
                }
            }
            return true;
        });

        $this->section('Framework Caches');

        // Clear Configuration Cache
        $this->task('Clearing configuration cache', function () {
            try {
                $this->call('config:clear');
                return true;
            } catch (\Throwable $e) {
                return false;
            }
        });

        // Clear Route Cache
        $this->task('Clearing route cache', function () {
            try {
                $this->call('route:clear');
                return true;
            } catch (\Throwable $e) {
                return false;
            }
        });

        // Clear View Cache
        $this->task('Clearing view cache', function () {
            try {
                $this->call('view:clear');
                return true;
            } catch (\Throwable $e) {
                return false;
            }
        });

        // Clear OPcache if enabled
        $this->task('Clearing OPcache', function () {
            try {
                // The call() handles non-existent commands gracefully or we can just try/catch
                $this->call('opcache:clear');
                return true;
            } catch (\Throwable $e) {
                return false;
            }
        });

        $this->checkpoint('finished');
        $this->newLine(2);

        $this->success('All system and application caches have been cleared!');

        $this->metrics($this->elapsed(), memory_get_peak_usage());

        if ($this->isVerbose()) {
            $this->displayTimings();
        }

        return 0;
    }
}
