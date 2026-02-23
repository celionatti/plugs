<?php

declare(strict_types=1);

namespace Plugs\Console\Commands;

use Plugs\Console\Command;
use Plugs\Container\Container;
use Plugs\Queue\DatabaseFailedJobProvider;
use Plugs\Database\Connection;

class QueueFailedCommand extends Command
{
    protected string $name = 'queue:failed';
    protected string $description = 'List all failed queue jobs';

    public function handle(): int
    {
        $this->title('Failed Queue Jobs');

        $container = Container::getInstance();
        $connection = $container->make(Connection::class);
        $provider = new DatabaseFailedJobProvider($connection);

        $failed = $provider->all();

        if (empty($failed)) {
            $this->info('No failed jobs found.');
            return 0;
        }

        $headers = ['ID', 'UUID', 'Queue', 'Class', 'Failed At'];
        $rows = [];

        foreach ($failed as $job) {
            $payload = unserialize($job['payload']);
            $rows[] = [
                $job['id'],
                $job['uuid'],
                $job['queue'],
                $payload['job'] ?? 'Unknown',
                $job['failed_at']
            ];
        }

        $this->table($headers, $rows);

        return 0;
    }
}
