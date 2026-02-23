<?php

declare(strict_types=1);

namespace Plugs\Console\Commands;

use Plugs\Console\Command;
use Plugs\Container\Container;
use Plugs\Queue\DatabaseFailedJobProvider;
use Plugs\Database\Connection;
use Plugs\Queue\QueueManager;

class QueueRetryCommand extends Command
{
    protected string $name = 'queue:retry';
    protected string $description = 'Retry a failed queue job';

    public function handle(): int
    {
        $id = $this->argument('0');

        if (!$id) {
            $this->error('Please provide the ID of the failed job to retry.');
            return 1;
        }

        $container = Container::getInstance();
        $connection = $container->make(Connection::class);
        $provider = new DatabaseFailedJobProvider($connection);

        if ($id === 'all') {
            $failed = $provider->all();
            foreach ($failed as $job) {
                $this->retryJob($job, $provider);
            }
        } else {
            $job = $provider->find((int) $id);
            if (!$job) {
                $this->error("Failed job [{$id}] not found.");
                return 1;
            }
            $this->retryJob($job, $provider);
        }

        return 0;
    }

    protected function retryJob(array $job, DatabaseFailedJobProvider $provider): void
    {
        $this->info("Retrying job [{$job['id']}]...");

        try {
            /** @var QueueManager $queue */
            $queue = Container::getInstance()->make('queue');
            $payload = unserialize($job['payload']);

            // Push back to queue
            $queue->push($payload['job'], $payload['data'], $job['queue']);

            // Delete from failed jobs
            $provider->forget($job['id']);

            $this->success("Job [{$job['id']}] has been pushed back to the queue.");
        } catch (\Throwable $e) {
            $this->error("Failed to retry job [{$job['id']}]: " . $e->getMessage());
        }
    }
}
