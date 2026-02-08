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
    protected string $description = 'Create a route cache file for faster route registration';

    protected function defineOptions(): array
    {
        return [
            '--path=PATH' => 'Custom cache file path',
        ];
    }

    public function handle(): int
    {
        $this->title('Route Cache Generator');

        // Get router instance
        $router = $this->getRouter();

        if (!$router) {
            $this->error('Router not found. Make sure the application is bootstrapped.');

            return 1;
        }

        $this->task('Caching application routes', function () use ($router) {
            try {
                $router->cacheRoutes();

                return true;
            } catch (\Exception $e) {
                $this->error($e->getMessage());

                return false;
            }
        });

        $this->box(
            "Route cache created successfully!\n\n" .
            "Your routes are now optimized for production performances.",
            "✅ Success",
            "success"
        );

        return 0;
    }

    private function getRouter(): ?Router
    {
        try {
            return app(Router::class);
        } catch (\Throwable $e) {
            return null;
        }
    }
}
