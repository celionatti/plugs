<?php

declare(strict_types=1);

namespace Plugs\Console\Commands;

use Plugs\Console\Command;

class DownCommand extends Command
{
    protected string $signature = 'down {--message= : The message for the maintenance mode} {--retry=60 : The number of seconds after which the request may be retried} {--secret= : The secret phrase that may be used to bypass maintenance mode}';
    protected string $description = 'Put the application into maintenance / demo mode';

    public function handle(): int
    {
        $payload = [
            'time' => time(),
            'message' => $this->option('message'),
            'retry' => $this->option('retry'),
            'secret' => $this->option('secret'),
        ];

        $directory = storage_path('framework');
        if (!is_dir($directory)) {
            mkdir($directory, 0755, true);
        }

        file_put_contents(
            $directory . '/maintenance.json',
            json_encode($payload, JSON_PRETTY_PRINT)
        );

        $this->output->success('Application is now in maintenance mode.');

        if ($this->option('secret')) {
            $this->output->info("To bypass maintenance mode, visit: " . url("/" . $this->option('secret')));
        }

        return 0;
    }
}
