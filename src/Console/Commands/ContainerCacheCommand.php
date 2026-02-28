<?php

declare(strict_types=1);

namespace Plugs\Console\Commands;

use Plugs\Console\Command;
use Plugs\Container\Container;

class ContainerCacheCommand extends Command
{
    protected string $description = 'Cache the container reflection metadata';

    public function handle(): int
    {
        $this->title('Container Cache Generator');

        $container = Container::getInstance();
        $cachePath = STORAGE_PATH . 'framework/container.php';

        $this->task('Caching container metadata', function () use ($container, $cachePath) {
            try {
                return $container->cache($cachePath);
            } catch (\Exception $e) {
                $this->error($e->getMessage());
                return false;
            }
        });

        $this->box(
            "Container cache created successfully!\n\n" .
            "Reflection overhead is now minimized for production.",
            "✅ Success",
            "success"
        );

        return 0;
    }
}
