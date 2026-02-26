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
        $maxMemory = (int) ($this->option('memory') ?: 128);

        $queueManager = Container::getInstance()->make('queue');

        while (true) {
            $job = $queueManager->pop($queue);

            if ($job) {
                $this->processJob($job, $queue);
            } else {
                if ($once) {
                    break;
                }
                sleep($sleep);
            }

            if ($this->memoryExceeded($maxMemory)) {
                $this->warning("Memory limit of {$maxMemory}MB exceeded. Gracefully restarting.");
                break;
            }
        }

        return 0;
    }

    protected function processJob($jobData, $queueName): void
    {
        $payload = unserialize($jobData->payload);
        $this->info("Processing job: {$payload['job']} (ID: {$jobData->id})");

        $maxTries = (int) ($this->option('tries') ?? 3);
        $backoff = (int) ($this->option('backoff') ?? 0);

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

            if ($jobData->attempts >= $maxTries) {
                $this->failJob($jobData, $e);
            } else {
                $this->handleRetry($jobData, $queueName, $backoff);
            }
        }
    }

    protected function handleRetry($jobData, $queueName, int $backoff): void
    {
        $this->warning("Job (ID: {$jobData->id}) failed, will retry (Attempt {$jobData->attempts})");

        $queueManager = Container::getInstance()->make('queue');
        $driver = $queueManager->driver();

        // If backoff is enabled, calculate delay (retry_count * backoff)
        $delay = $jobData->attempts * $backoff;

        // Re-push to queue with delay
        $payload = unserialize($jobData->payload);

        // Use 'later' for delayed retry
        $queueManager->later($delay, $payload['job'], $payload['data'], $queueName);

        // Delete current record from queue (it's been re-pushed)
        if (method_exists($driver, 'delete')) {
            $driver->delete((int) $jobData->id);
        }
    }

    protected function failJob($jobData, \Throwable $e): void
    {
        $this->error("Job (ID: {$jobData->id}) has failed after max attempts.");

        try {
            $container = Container::getInstance();
            $connection = $container->make(\Plugs\Database\Connection::class);

            $failedJobProvider = new \Plugs\Queue\DatabaseFailedJobProvider($connection);

            $failedJobProvider->log(
                config('queue.default', 'database'),
                $jobData->queue,
                $jobData->payload,
                $e
            );

            // Delete from queue
            $queueManager = $container->make('queue');
            $driver = $queueManager->driver();
            if (method_exists($driver, 'delete')) {
                $driver->delete((int) $jobData->id);
            }

            $this->error("Job (ID: {$jobData->id}) has been moved to the failed_jobs table.");
        } catch (\Throwable $failError) {
            $this->error("Crucial failure: Could not log failed job! " . $failError->getMessage());
        }
    }

    protected function memoryExceeded(int $memoryLimit): bool
    {
        return (memory_get_usage(true) / 1024 / 1024) >= $memoryLimit;
    }
}
