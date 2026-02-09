<?php

declare(strict_types=1);

namespace Plugs\Worker;

use Plugs\EventBus\EventBusManager;
use Plugs\EventBus\EventBusInterface;

/**
 * Lightweight worker for consuming messages from event bus.
 * Provides scaling hints and lifecycle management.
 */
class Worker
{
    private string $name;
    private array $channels = [];
    private int $concurrency;
    private int $timeout;
    private int $maxJobs;
    private bool $shouldStop = false;
    private int $processedJobs = 0;
    private float $startTime;

    // Scaling hints
    private array $metrics = [
        'jobs_processed' => 0,
        'jobs_failed' => 0,
        'avg_process_time' => 0,
        'memory_peak' => 0,
        'idle_cycles' => 0,
    ];

    public function __construct(
        string $name = 'default',
        int $concurrency = 1,
        int $timeout = 60,
        int $maxJobs = 0
    ) {
        $this->name = $name;
        $this->concurrency = $concurrency;
        $this->timeout = $timeout;
        $this->maxJobs = $maxJobs;
        $this->startTime = microtime(true);
    }

    /**
     * Subscribe to a channel with a handler.
     */
    public function on(string $channel, callable $handler): self
    {
        $this->channels[$channel] = $handler;
        return $this;
    }

    /**
     * Start the worker loop.
     */
    public function run(): void
    {
        $this->registerSignalHandlers();
        $bus = EventBusManager::bus();

        // Subscribe to all channels
        foreach ($this->channels as $channel => $handler) {
            $bus->subscribe($channel, function (array $payload, array $meta) use ($handler) {
                $this->processJob($handler, $payload, $meta);
            }, $this->name);
        }

        $this->log("Worker '{$this->name}' started. Listening on: " . implode(', ', array_keys($this->channels)));

        // Main loop
        while (!$this->shouldStop) {
            try {
                $bus->consume($this->timeout, $this->concurrency);

                $this->metrics['idle_cycles']++;
                $this->updateScalingHints();

            } catch (\Throwable $e) {
                $this->log("Error: " . $e->getMessage(), 'error');
                sleep(1); // Back off on error
            }

            // Check max jobs limit
            if ($this->maxJobs > 0 && $this->processedJobs >= $this->maxJobs) {
                $this->log("Max jobs reached ({$this->maxJobs}). Stopping.");
                break;
            }
        }

        $this->log("Worker '{$this->name}' stopped. Processed {$this->processedJobs} jobs.");
    }

    /**
     * Process a single job.
     */
    private function processJob(callable $handler, array $payload, array $meta): void
    {
        $jobStart = microtime(true);

        try {
            $handler($payload, $meta);
            $this->metrics['jobs_processed']++;
            $this->processedJobs++;
            $this->metrics['idle_cycles'] = 0;

        } catch (\Throwable $e) {
            $this->metrics['jobs_failed']++;
            $this->log("Job failed: " . $e->getMessage(), 'error');
        }

        // Update avg process time
        $jobTime = microtime(true) - $jobStart;
        $total = $this->metrics['jobs_processed'] + $this->metrics['jobs_failed'];
        $this->metrics['avg_process_time'] =
            (($this->metrics['avg_process_time'] * ($total - 1)) + $jobTime) / $total;

        $this->metrics['memory_peak'] = max($this->metrics['memory_peak'], memory_get_peak_usage(true));
    }

    /**
     * Get scaling hints for orchestrator.
     */
    public function getScalingHints(): array
    {
        $uptime = microtime(true) - $this->startTime;
        $jobsPerSecond = $uptime > 0 ? $this->metrics['jobs_processed'] / $uptime : 0;

        return [
            'worker_name' => $this->name,
            'uptime_seconds' => round($uptime, 2),
            'jobs_processed' => $this->metrics['jobs_processed'],
            'jobs_failed' => $this->metrics['jobs_failed'],
            'jobs_per_second' => round($jobsPerSecond, 4),
            'avg_process_time_ms' => round($this->metrics['avg_process_time'] * 1000, 2),
            'memory_peak_mb' => round($this->metrics['memory_peak'] / 1024 / 1024, 2),
            'idle_cycles' => $this->metrics['idle_cycles'],
            'should_scale_up' => $this->shouldScaleUp(),
            'should_scale_down' => $this->shouldScaleDown(),
        ];
    }

    /**
     * Check if should scale up workers.
     */
    private function shouldScaleUp(): bool
    {
        // Scale up if: high job rate, low idle cycles
        return $this->metrics['idle_cycles'] < 5 &&
            $this->metrics['jobs_processed'] > 100;
    }

    /**
     * Check if should scale down workers.
     */
    private function shouldScaleDown(): bool
    {
        // Scale down if: many idle cycles, low job rate
        return $this->metrics['idle_cycles'] > 50;
    }

    private function updateScalingHints(): void
    {
        // Could write to file/redis for orchestrator to read
        // For now, just update internal metrics
    }

    /**
     * Stop the worker gracefully.
     */
    public function stop(): void
    {
        $this->shouldStop = true;
    }

    private function registerSignalHandlers(): void
    {
        if (function_exists('pcntl_signal')) {
            pcntl_signal(SIGTERM, fn() => $this->stop());
            pcntl_signal(SIGINT, fn() => $this->stop());
        }
    }

    private function log(string $message, string $level = 'info'): void
    {
        $timestamp = date('Y-m-d H:i:s');
        echo "[{$timestamp}] [{$level}] {$message}\n";
    }
}
