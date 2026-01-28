<?php

declare(strict_types=1);

namespace Plugs\Console\Commands;

use Plugs\Console\Command;

class UpCommand extends Command
{
    protected string $signature = 'up';
    protected string $description = 'Bring the application out of maintenance mode';

    public function handle(): int
    {
        $this->checkpoint('start');
        $this->title('Maintenance Mode');

        $file = storage_path('framework/maintenance.json');

        if (file_exists($file)) {
            $this->checkpoint('executing');
            $this->task('Bringing application online', function () use ($file) {
                if (!unlink($file)) {
                    throw new \RuntimeException("Could not remove maintenance file: {$file}");
                }
                usleep(200000);
            });

            $this->checkpoint('finished');
            $this->newLine();
            $this->box(
                "Application is now live and accepting requests!\n\n" .
                "Status: Online\n" .
                "Time: {$this->formatTime($this->elapsed())}",
                "✅ System Up",
                "success"
            );
        } else {
            $this->info('Application is already live and functioning correctly.');
            $this->checkpoint('finished');
        }

        if ($this->isVerbose()) {
            $this->displayTimings();
        }

        return 0;
    }
}
