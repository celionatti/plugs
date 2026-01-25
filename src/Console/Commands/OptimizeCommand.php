<?php

declare(strict_types=1);

namespace Plugs\Console\Commands;

use Plugs\Console\Command;

class OptimizeCommand extends Command
{
    protected string $description = 'Cache framework files for production speed';

    public function handle(): int
    {
        $this->title('Production Optimization');

        $this->section('Optimizing Configuration');
        $this->call('config:cache');

        $this->section('Optimizing Routes');
        $this->call('route:cache');

        $this->newLine();
        $this->success('Framework optimized for production!');

        return 0;
    }
}
