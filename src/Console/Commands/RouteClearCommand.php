<?php

declare(strict_types=1);

namespace Plugs\Console\Commands;

/*
|--------------------------------------------------------------------------
| RouteClearCommand Class
|--------------------------------------------------------------------------
*/

use Plugs\Console\Command;
use Plugs\Console\Support\Filesystem;

class RouteClearCommand extends Command
{
    protected string $description = 'Remove the route cache file';

    public function handle(): int
    {
        $this->title('Clear Route Cache');
        
        $cachePath = getcwd() . '/storage/cache/routes.cache';
        
        if (!Filesystem::exists($cachePath)) {
            $this->info('Route cache does not exist.');
            return 0;
        }
        
        $this->task('Removing route cache', function() use ($cachePath) {
            Filesystem::delete($cachePath);
            usleep(200000);
        });
        
        $this->success('Route cache cleared successfully!');
        
        return 0;
    }
}