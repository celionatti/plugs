<?php

declare(strict_types=1);

namespace Plugs\Console\Commands;

use Plugs\Console\Command;
use Plugs\Worker\Worker;

/**
 * CLI command to run workers.
 * Usage: php plg worker:run [options]
 */
class WorkerCommand extends Command
{
    protected string $signature = 'worker:run 
        {--queue=default : Queue/channel name to listen on}
        {--concurrency=1 : Number of jobs to process concurrently}
        {--timeout=60 : Timeout in seconds for waiting on messages}
        {--max-jobs=0 : Maximum jobs to process before stopping (0 = unlimited)}
        {--name= : Worker name (defaults to hostname:pid)}';

    protected string $description = 'Start a worker to process messages from the event bus';

    public function handle(): int
    {
        $queue = $this->option('queue') ?? 'default';
        $concurrency = (int) ($this->option('concurrency') ?? 1);
        $timeout = (int) ($this->option('timeout') ?? 60);
        $maxJobs = (int) ($this->option('max-jobs') ?? 0);
        $name = $this->option('name') ?? gethostname() . ':' . getmypid();

        $this->info("Starting worker '{$name}'...");
        $this->info("Queue: {$queue}");
        $this->info("Concurrency: {$concurrency}");
        $this->info("Timeout: {$timeout}s");

        // Create worker
        $worker = new Worker($name, $concurrency, $timeout, $maxJobs);

        // Allow user-defined handlers via config
        $handlers = config('workers.handlers', []);

        if (empty($handlers)) {
            // Default: listen on specified queue
            $worker->on($queue, function (array $payload, array $meta) {
                $this->processDefaultJob($payload, $meta);
            });
        } else {
            foreach ($handlers as $channel => $handler) {
                $worker->on($channel, $handler);
            }
        }

        // Run the worker
        $worker->run();

        // Output scaling hints on exit
        $hints = $worker->getScalingHints();
        $this->info("\nScaling Hints:");
        $this->info("  Jobs processed: {$hints['jobs_processed']}");
        $this->info("  Jobs failed: {$hints['jobs_failed']}");
        $this->info("  Avg process time: {$hints['avg_process_time_ms']}ms");
        $this->info("  Peak memory: {$hints['memory_peak_mb']}MB");

        if ($hints['should_scale_up']) {
            $this->warn("  Recommendation: SCALE UP - High job volume");
        } elseif ($hints['should_scale_down']) {
            $this->warn("  Recommendation: SCALE DOWN - Low job volume");
        }

        return self::SUCCESS;
    }

    private function processDefaultJob(array $payload, array $meta): void
    {
        // Default job processing - dispatch to job class if specified
        if (isset($payload['job'])) {
            $jobClass = $payload['job'];
            $data = $payload['data'] ?? [];

            if (class_exists($jobClass)) {
                $job = new $jobClass();
                $job->handle($data);
            }
        }
    }
}
