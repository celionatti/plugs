<?php

declare(strict_types=1);

namespace Plugs\Console\Commands;

/*
|--------------------------------------------------------------------------
| Make: CacheClear Command
|--------------------------------------------------------------------------
*/

use Plugs\Console\Command;
use Plugs\Console\Support\Filesystem;

class CacheClearCommand extends Command
{
    protected string $description = 'Clear the application cache';

    public function handle(): int
    {
        $this->checkpoint('start');
        $this->title('Cache Management');

        $cacheDir = storage_path('cache');

        $this->section('Configuration');
        $this->keyValue('Cache Path', str_replace(getcwd() . '/', '', $cacheDir));
        $this->newLine();

        if (!is_dir($cacheDir)) {
            $this->warning('Cache directory does not exist or is already empty.');
            $this->checkpoint('finished');

            return 0;
        }

        $this->checkpoint('clearing');
        $count = 0;
        $files = Filesystem::files($cacheDir, true);

        if (empty($files)) {
            $this->info('No cache files found to clear.');
            $this->checkpoint('finished');

            return 0;
        }

        $this->withProgressBar(count($files), function ($step) use ($files, &$count) {
            $file = $files[$step - 1];
            if (Filesystem::delete($file)) {
                $count++;
            }
            usleep(10000); // Tiny delay for visual feedback
        }, 'Purging cache entries');

        $this->checkpoint('finished');

        $this->newLine(2);
        $this->box(
            "Cache cleared successfully!\n\n" .
            "Files Purged: {$count}\n" .
            "Time: {$this->formatTime($this->elapsed())}",
            "✅ Cache Cleaned",
            "success"
        );

        if ($this->isVerbose()) {
            $this->displayTimings();
        }

        return 0;
    }
}
