<?php

declare(strict_types=1);

namespace Plugs\Console\Commands;

use Plugs\Console\Command;
use Plugs\Container\Container;

class QueueWorkCommand extends Command
{
    protected string $name = 'queue:work';
    protected string $description = 'Start processing jobs on the queue as a background worker';

    public function handle(): int
    {
        $this->info('Queue worker started. Press Ctrl+C to stop.');

        $queue = $this->option('queue') ?: 'default';
        $once = $this->hasOption('once');
        $sleep = (int) ($this->option('sleep') ?: 3);

        $queueManager = Container::getInstance()->make('queue');

        while (true) {
            $job = $queueManager->pop($queue);

            if ($job) {
                $this->processJob($job);
            } else {
                if ($once) {
                    break;
                }
                sleep($sleep);
            }
        }

        return 0;
    }

    protected function processJob($jobData): void
    {
        $payload = unserialize($jobData->payload);
        $this->info("Processing job: {$payload['job']} (ID: {$jobData->id})");

        try {
            if ($payload['instance']) {
                $job = unserialize($payload['instance']);
                $job->handle($payload['data']);
            } else {
                $className = $payload['job'];
                $job = new $className();
                $job->handle($payload['data']);
            }

            // Delete job from queue after successful processing
            $queueManager = Container::getInstance()->make('queue');
            $driver = $queueManager->driver();
            if (method_exists($driver, 'delete')) {
                $driver->delete((int) $jobData->id);
            }

            $this->success("Successfully processed job: (ID: {$jobData->id})");
        } catch (\Throwable $e) {
            $this->error("Failed to process job (ID: {$jobData->id}): " . $e->getMessage());
            // In a real system, you'd handle retries or move to failed_jobs table here
        }
    }
}
