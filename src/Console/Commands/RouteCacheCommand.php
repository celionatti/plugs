<?php

declare(strict_types=1);

namespace Plugs\Console\Commands;

/*
|--------------------------------------------------------------------------
| RouteCacheCommand Class
|--------------------------------------------------------------------------
*/

use Plugs\Router\Router;
use Plugs\Console\Command;
use Plugs\Console\Support\Filesystem;

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
        $this->checkpoint('start');
        
        $this->title('Route Cache Generator');
        
        // Get router instance
        $router = $this->getRouter();
        
        if (!$router) {
            $this->error('Router not found. Make sure the application is bootstrapped.');
            return 1;
        }
        
        $routes = $router->getRoutes();
        
        if (empty($routes)) {
            $this->warning('No routes to cache.');
            return 0;
        }
        
        $this->checkpoint('routes_loaded');
        
        $this->section('Caching Routes');
        
        $cachePath = $this->option('path') ?? $this->getDefaultCachePath();
        
        $this->task('Serializing routes', function() use ($routes, $cachePath) {
            $serialized = serialize($routes);
            Filesystem::put($cachePath, $serialized);
            usleep(300000);
        });
        
        $this->checkpoint('cache_created');
        
        $fileSize = filesize($cachePath);
        $formattedSize = $this->formatBytes($fileSize);
        
        $this->box(
            "Route cache created successfully!\n\n" .
            "Routes cached: " . count($routes) . "\n" .
            "Cache file: {$cachePath}\n" .
            "File size: {$formattedSize}",
            "✅ Success",
            "success"
        );
        
        $this->newLine();
        $this->note('Run "php theplugs route:clear" to remove the cache.');
        
        if ($this->isVerbose()) {
            $this->displayTimings();
        }
        
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

    private function getDefaultCachePath(): string
    {
        return getcwd() . '/storage/cache/routes.cache';
    }

    private function formatBytes(int $bytes): string
    {
        $units = ['B', 'KB', 'MB'];
        $bytes = max($bytes, 0);
        $pow = floor(($bytes ? log($bytes) : 0) / log(1024));
        $pow = min($pow, count($units) - 1);
        $bytes /= (1 << (10 * $pow));
        
        return round($bytes, 2) . ' ' . $units[$pow];
    }
}