<?php

declare(strict_types=1);

namespace Plugs\Console\Commands;

use Plugs\Console\Command;
use Plugs\Console\Scheduling\Schedule;

/**
 * Lists all scheduled tasks.
 */
class ScheduleListCommand extends Command
{
    protected string $signature = 'schedule:list';
    protected string $description = 'List all scheduled tasks';

    public function handle(): int
    {
        $schedule = $this->resolveSchedule();
        $events = $schedule->events();

        if (empty($events)) {
            $this->warning('No scheduled tasks defined.');

            return 0;
        }

        $this->info('Scheduled Tasks:');
        $this->line('');

        $headers = ['Command', 'Description', 'Next Due'];
        $rows = [];

        foreach ($events as $event) {
            $command = $event->getCommand();
            $description = $event->getDescription() ?? '-';
            $isDue = $event->isDue() ? '✓ Now' : 'Not due';

            $rows[] = [$command, $description, $isDue];
        }

        $this->table($headers, $rows);

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
                if (method_exists($kernel, 'schedule')) {
                    $kernel->schedule($schedule);
                }
            }
        }

        return $schedule;
    }
}
