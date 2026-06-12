<?php

declare(strict_types=1);

namespace Plugs\Console\Commands;

use Plugs\Console\Command;

class OptimizeClearCommand extends Command
{
    protected string $description = 'Clear all cached configuration, routes, views, and container files';

    public function handle(): int
    {
        $this->checkpoint('start');
        $this->advancedHeader('Clear Cache & Optimizations', 'Clearing cached framework files');

        $this->info("Clearing framework caches...");
        $this->newLine();

        $this->section('Task Execution');

        $this->loading('Clearing configuration cache', function () {
            $this->call('config:clear');
            usleep(100000);
        });

        $this->loading('Clearing route cache', function () {
            $this->call('route:clear');
            usleep(100000);
        });

        $this->loading('Clearing container cache', function () {
            $this->call('container:clear');
            usleep(100000);
        });

        $this->loading('Clearing pre-compiled views', function () {
            $this->call('view:clear');
            usleep(100000);
        });

        $this->checkpoint('finished');

        $this->newLine(2);
        $this->box(
            "All cached optimization files cleared successfully!",
            "✅ Cache Cleared",
            "success"
        );

        $this->metrics($this->elapsed(), memory_get_peak_usage());

        if ($this->isVerbose()) {
            $this->displayTimings();
        }

        return self::SUCCESS;
    }
}
