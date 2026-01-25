<?php

declare(strict_types=1);

namespace Plugs\Console\Commands;

/*
|--------------------------------------------------------------------------
| RouteClearCommand Class
|--------------------------------------------------------------------------
*/

use Plugs\Console\Command;
use Plugs\Router\Router;

class RouteClearCommand extends Command
{
    protected string $description = 'Remove the route cache file';

    public function handle(): int
    {
        $this->title('Clear Route Cache');

        $router = app(Router::class);

        $this->task('Removing route cache', function () use ($router) {
            $router->clearCache(true);
            return true;
        });

        $this->success('Route cache cleared successfully!');

        return 0;
    }
}
