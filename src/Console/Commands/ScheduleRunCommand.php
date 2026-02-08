<?php

declare(strict_types=1);

namespace Plugs\Console\Commands;

use Plugs\Console\Command;
use Plugs\Console\Scheduling\CallbackEvent;
use Plugs\Console\Scheduling\Schedule;

/**
 * Runs all scheduled tasks that are due.
 */
class ScheduleRunCommand extends Command
{
    protected string $signature = 'schedule:run';
    protected string $description = 'Run the scheduled commands';

    public function handle(): int
    {
        $this->checkpoint('start');
        $this->title('Task Scheduler');

        $schedule = $this->resolveSchedule();
        $dueEvents = $schedule->dueEvents();

        $this->section('Status Check');
        $this->keyValue('Scheduled Tools', (string) count($schedule->events()));
        $this->keyValue('Due for Execution', (string) count($dueEvents));
        $this->newLine();

        if (count($dueEvents) === 0) {
            $this->info('No scheduled commands are due at this time.');
            $this->checkpoint('finished');
            return 0;
        }

        $this->checkpoint('running');
        $this->section('Task Execution');

        $eventsRan = 0;

        foreach ($dueEvents as $event) {
            $this->runEvent($event);
            $eventsRan++;
            $this->newLine();
        }

        $this->checkpoint('finished');

        $this->newLine();
        $this->box(
            "Scheduler execution completed!\n\n" .
            "Tasks Executed: {$eventsRan}\n" .
            "Time: {$this->formatTime($this->elapsed())}",
            "✅ Scheduler Finished",
            "success"
        );

        if ($this->isVerbose()) {
            $this->displayTimings();
        }

        return 0;
    }

    /**
     * Resolve the schedule from the ConsoleKernel.
     */
    protected function resolveSchedule(): Schedule
    {
        $schedule = new Schedule();

        // Check if there's an app-level console kernel
        $appKernel = base_path('app/Console/Kernel.php');

        if (file_exists($appKernel)) {
            require_once $appKernel;

            if (class_exists(\App\Console\Kernel::class)) {
                $kernel = new \App\Console\Kernel();
                /** @phpstan-ignore function.alreadyNarrowedType */
                if (method_exists($kernel, 'schedule')) {
                    $kernel->schedule($schedule);
                }
            }
        }

        return $schedule;
    }

    /**
     * Run a scheduled event.
     */
    protected function runEvent($event): void
    {
        $description = $event->getDescription() ?? $event->getCommand();

        $this->info("Running scheduled command: {$description}");

        if ($event instanceof CallbackEvent) {
            try {
                $event->run();
                $this->success("  ✓ Callback executed successfully.");
            } catch (\Throwable $e) {
                $this->error("  ✗ Callback failed: " . $e->getMessage());
            }
        } else {
            // Run via the console plugs
            $command = $event->getCommand();
            $parameters = $event->getParameters();

            // Build the command string
            $paramString = '';
            foreach ($parameters as $key => $value) {
                if (is_numeric($key)) {
                    $paramString .= " {$value}";
                } else {
                    $paramString .= " --{$key}={$value}";
                }
            }

            $fullCommand = "php theplugs {$command}{$paramString}";

            $this->line("  → Executing: {$fullCommand}");

            $output = [];
            $returnCode = 0;
            exec($fullCommand, $output, $returnCode);

            if ($returnCode === 0) {
                $this->success("  ✓ Command completed successfully.");
            } else {
                $this->error("  ✗ Command failed with exit code: {$returnCode}");
            }

            // Output the command's output
            foreach ($output as $line) {
                $this->line("    {$line}");
            }
        }
    }
}
