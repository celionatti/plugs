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
        $this->title('Production Optimization');

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

        $this->checkpoint('finished');

        $this->newLine(2);
        $this->box(
            "Framework successfully optimized for production!\n\n" .
            "Actions: Config & Route Caching",
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
