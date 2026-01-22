<?php

declare(strict_types=1);

namespace Plugs\Console\Commands;

use Plugs\Console\Command;

class UpCommand extends Command
{
    protected string $signature = 'up';
    protected string $description = 'Bring the application out of maintenance mode';

    public function handle(): int
    {
        $file = storage_path('framework/maintenance.json');

        if (file_exists($file)) {
            unlink($file);
            $this->output->success('Application is now live.');
        } else {
            $this->output->info('Application is already up.');
        }

        return 0;
    }
}
