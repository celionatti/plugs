<?php

declare(strict_types=1);

namespace Plugs\Console\Commands;

use Plugs\Console\Command;

class OptimizeCommand extends Command
{
    protected string $description = 'Cache framework files for production speed';

    public function handle(): int
    {
        $this->checkpoint('start');
        $this->advancedHeader('Production Optimization', 'Caching framework files for peak performance');

        $this->info("Preparing framework for peak production performance...");
        $this->newLine();

        $this->section('Task Execution');

        $this->loading('Caching configuration files', function () {
            $this->call('config:cache');
            usleep(100000);
        });

        $this->loading('Caching application routes', function () {
            $this->call('route:cache');
            usleep(100000);
        });

        $this->loading('Caching container metadata', function () {
            $this->call('container:cache');
            usleep(100000);
        });

        $this->loading('Pre-compiling application views', function () {
            $this->call('view:cache');
            usleep(100000);
        });

        $this->checkpoint('finished');

        $this->newLine(2);
        $this->box(
            "Framework successfully optimized for production!\n\n" .
            "Actions: Config, Route, Container & View Caching",
            "✅ Optimization Complete",
            "success"
        );

        $this->metrics($this->elapsed(), memory_get_peak_usage());

        if ($this->isVerbose()) {
            $this->displayTimings();
        }

        return 0;
    }
}
