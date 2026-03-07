<?php

declare(strict_types=1);

namespace Plugs\Console\Commands;

use Plugs\Console\Command;

class ContainerClearCommand extends Command
{
    protected string $description = 'Clear the container cache file';

    public function handle(): int
    {
        $this->title('Container Cache Clear');

        $this->task('Clearing container cache', function () {
            try {
                $basePath = defined('STORAGE_PATH') ? STORAGE_PATH : '';
                $cachePath = $basePath . 'framework/container.php';

                if (file_exists($cachePath)) {
                    unlink($cachePath);
                }

                return true;
            } catch (\Exception $e) {
                $this->error($e->getMessage());
                return false;
            }
        });

        $this->box(
            "Container cache cleared successfully!",
            "✅ Success",
            "success"
        );

        return 0;
    }
}
