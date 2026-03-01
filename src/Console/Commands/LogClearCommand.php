<?php

declare(strict_types=1);

namespace Plugs\Console\Commands;

/*
|--------------------------------------------------------------------------
| Make: LogClear Command
|--------------------------------------------------------------------------
*/

use Plugs\Console\Command;
use Plugs\Console\Support\Filesystem;

class LogClearCommand extends Command
{
    protected string $description = 'Clear the application logs';

    public function handle(): int
    {
        $this->checkpoint('start');
        $this->advancedHeader('Log Management', 'Purge Application Log Files');

        $logPath = storage_path('logs');

        $this->section('Status');
        $this->keyValue('Log Directory', str_replace(getcwd() . DIRECTORY_SEPARATOR, '', $logPath));
        $this->newLine();

        if (!Filesystem::isDirectory($logPath)) {
            $this->warning('Log directory does not exist.');
            $this->checkpoint('finished');
            return 0;
        }

        $this->task('Clearing logs', function () use ($logPath) {
            $items = array_diff(scandir($logPath), ['.', '..', '.gitignore']);
            $count = 0;

            foreach ($items as $item) {
                $path = $logPath . DIRECTORY_SEPARATOR . $item;
                if (Filesystem::isFile($path) && (str_ends_with($item, '.log') || $item === 'plugs.log')) {
                    if (@unlink($path)) {
                        $count++;
                    }
                }
            }

            // Re-create the empty plugs.log to avoid filesystem errors
            $mainLog = $logPath . DIRECTORY_SEPARATOR . 'plugs.log';
            if (!file_exists($mainLog)) {
                touch($mainLog);
            }

            return "Purged {$count} log files.";
        });

        $this->checkpoint('finished');
        $this->newLine(2);

        $this->success("Application logs have been cleared successfully!");

        $this->metrics($this->elapsed(), memory_get_peak_usage());

        if ($this->isVerbose()) {
            $this->displayTimings();
        }

        return 0;
    }
}
