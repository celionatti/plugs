<?php

declare(strict_types=1);

namespace Plugs\Console\Commands;

use Plugs\Console\Command;

/**
 * CLI orchestrator for distributed task management.
 * Monitors workers, provides scaling recommendations, and manages lifecycle.
 * Usage: php plg orchestrator:run
 */
class OrchestratorCommand extends Command
{
    protected string $signature = 'orchestrator:run
        {--workers=1 : Number of worker processes to spawn}
        {--queue=default : Queue/channel to monitor}
        {--auto-scale : Enable automatic scaling based on load}
        {--max-workers=10 : Maximum workers when auto-scaling}
        {--min-workers=1 : Minimum workers when auto-scaling}';

    protected string $description = 'Run the orchestrator to manage distributed workers';

    private array $workerProcesses = [];
    private bool $running = true;

    public function handle(): int
    {
        $numWorkers = (int) ($this->option('workers') ?? 1);
        $queue = $this->option('queue') ?? 'default';
        $autoScale = $this->hasOption('auto-scale');
        $maxWorkers = (int) ($this->option('max-workers') ?? 10);
        $minWorkers = (int) ($this->option('min-workers') ?? 1);

        $this->info("Orchestrator starting...");
        $this->info("Managing {$numWorkers} workers on queue: {$queue}");

        // Register signal handlers for graceful shutdown
        $this->registerSignalHandlers();

        // Spawn initial workers
        for ($i = 0; $i < $numWorkers; $i++) {
            $this->spawnWorker($queue, $i);
        }

        $this->info("Spawned {$numWorkers} workers. Monitoring...\n");

        // Main orchestration loop
        while ($this->running) {
            $this->monitorWorkers();

            if ($autoScale) {
                $this->autoScale($queue, $minWorkers, $maxWorkers);
            }

            sleep(5); // Check every 5 seconds
        }

        // Graceful shutdown
        $this->shutdownWorkers();

        return self::SUCCESS;
    }

    private function spawnWorker(string $queue, int $index): void
    {
        $workerName = "worker-{$index}";

        // Build command
        $phpBinary = PHP_BINARY;
        $script = $_SERVER['SCRIPT_FILENAME'] ?? 'plg';
        $cmd = "\"{$phpBinary}\" \"{$script}\" worker:run --queue={$queue} --name={$workerName}";

        // Start process (platform-specific)
        if (strtoupper(substr(PHP_OS, 0, 3)) === 'WIN') {
            $process = popen("start /B {$cmd}", 'r');
        } else {
            $process = popen("{$cmd} &", 'r');
        }

        if ($process) {
            $this->workerProcesses[$workerName] = [
                'process' => $process,
                'started_at' => time(),
                'queue' => $queue,
            ];
            $this->info("  Spawned: {$workerName}");
        }
    }

    private function monitorWorkers(): void
    {
        foreach ($this->workerProcesses as $name => $info) {
            // Check if process is still running
            $status = proc_get_status($info['process']);

            if ($status && !$status['running']) {
                $this->warn("Worker {$name} exited. Restarting...");
                unset($this->workerProcesses[$name]);
                $this->spawnWorker($info['queue'], count($this->workerProcesses));
            }
        }
    }

    private function autoScale(string $queue, int $min, int $max): void
    {
        $currentWorkers = count($this->workerProcesses);
        $pending = $this->getPendingJobs($queue);

        // Scale up if high backlog
        if ($pending > 100 && $currentWorkers < $max) {
            $this->info("Scaling up: {$pending} pending jobs");
            $this->spawnWorker($queue, $currentWorkers);
        }

        // Scale down if low load
        if ($pending < 10 && $currentWorkers > $min) {
            $this->info("Scaling down: low job volume");
            $this->terminateOneWorker();
        }
    }

    private function getPendingJobs(string $queue): int
    {
        // Query event bus for pending count
        try {
            return \Plugs\EventBus\EventBusManager::bus()->pending($queue);
        } catch (\Throwable $e) {
            return 0;
        }
    }

    private function terminateOneWorker(): void
    {
        if (empty($this->workerProcesses)) {
            return;
        }

        $name = array_key_last($this->workerProcesses);
        $info = $this->workerProcesses[$name];

        pclose($info['process']);
        unset($this->workerProcesses[$name]);

        $this->warn("Terminated: {$name}");
    }

    private function shutdownWorkers(): void
    {
        $this->info("\nShutting down workers...");

        foreach ($this->workerProcesses as $name => $info) {
            pclose($info['process']);
            $this->info("  Stopped: {$name}");
        }

        $this->workerProcesses = [];
        $this->info("All workers stopped.");
    }

    private function registerSignalHandlers(): void
    {
        if (function_exists('pcntl_signal')) {
            pcntl_signal(SIGTERM, fn() => $this->running = false);
            pcntl_signal(SIGINT, fn() => $this->running = false);
        }
    }
}
