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
        $this->title('Route Cache Terminator');

        $this->task('Clearing route cache', function () {
            try {
                /** @var Router $router */
                $router = app('router');
                $router->clearCache(true); // true means physical deletion
                return true;
            } catch (\Exception $e) {
                $this->error($e->getMessage());
                return false;
            }
        });

        $this->success('Route cache cleared successfully!');

        return 0;
    }
}
