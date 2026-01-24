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
        $this->title('Cache Clearing');

        $cacheDir = storage_path('cache');

        if (!is_dir($cacheDir)) {
            $this->warning('Cache directory does not exist');

            return 0;
        }

        $count = 0;
        $files = Filesystem::files($cacheDir, true);

        $this->withProgressBar(count($files), function ($step) use ($files, &$count) {
            $file = $files[$step - 1];
            if (Filesystem::delete($file)) {
                $count++;
            }
        }, 'Clearing cache files');

        $this->success("Cleared {$count} cache files");

        return 0;
    }
}
