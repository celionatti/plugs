<?php

declare(strict_types=1);

namespace Plugs\Console\Commands;

/*
|--------------------------------------------------------------------------
| RouteCacheCommand Class
|--------------------------------------------------------------------------
*/

use Plugs\Console\Command;
use Plugs\Router\Router;

class RouteCacheCommand extends Command
{
    protected string $description = 'Create a route cache file for faster routing';

    public function handle(): int
    {
        $this->title('Route Cache Generator');

        $this->task('Caching application routes', function () {
            try {
                // We need to load web routes. Since we're in CLI, they aren't loaded.
                // We'll boot a WebKernel instance to trigger route loading.
                $kernel = new \Plugs\Kernel\WebKernel(\Plugs\Bootstrap\ContextType::Web);
                $kernel->boot();

                /** @var Router $router */
                $router = app('router');

                return $router->cacheRoutes();
            } catch (\Exception $e) {
                $this->error($e->getMessage());
                return false;
            }
        });

        $this->box(
            "Route cache created successfully!\n\n" .
            "Your application routes are now optimized for production performances.",
            "✅ Success",
            "success"
        );

        return 0;
    }
}
