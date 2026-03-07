<?php

declare(strict_types=1);

namespace Plugs\Console\Commands;

/*
|--------------------------------------------------------------------------
| ContainerCacheCommand Class
|--------------------------------------------------------------------------
*/

use Plugs\Console\Command;

class ContainerCacheCommand extends Command
{
    protected string $description = 'Create a container cache file for faster dependency resolutions';

    protected function defineOptions(): array
    {
        return [
            '--path=PATH' => 'Custom cache file path',
        ];
    }

    public function handle(): int
    {
        $this->title('Container Cache Generator');

        // Check if container exists
        if (!function_exists('app')) {
            $this->error('Application not found. Make sure the application is bootstrapped.');
            return 1;
        }

        $this->task('Caching application container', function () {
            try {
                // Determine Cache path
                $basePath = defined('STORAGE_PATH') ? STORAGE_PATH : '';
                $cachePath = $this->option('path', $basePath . 'framework/container.php');

                $container = app(\Plugs\Container\Container::class);
                $container->cache($cachePath);

                return true;
            } catch (\Exception $e) {
                $this->error($e->getMessage());

                return false;
            }
        });

        $this->box(
            "Container cache created successfully!\n\n" .
            "Your service container is now optimized for production performances.",
            "✅ Success",
            "success"
        );

        return 0;
    }
}
