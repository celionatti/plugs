<?php

declare(strict_types=1);

namespace Plugs\Console\Commands;

/*
|--------------------------------------------------------------------------
| RouteListCommand Class
|--------------------------------------------------------------------------
*/

use Plugs\Console\Command;
use Plugs\Router\Router;

class RouteListCommand extends Command
{
    protected string $description = 'Display all registered routes in a beautiful table';

    protected function defineOptions(): array
    {
        return [
            '--method=METHOD' => 'Filter routes by HTTP method (GET, POST, etc.)',
            '--name=NAME' => 'Filter routes by name',
            '--path=PATH' => 'Filter routes by path pattern',
            '--sort=COLUMN' => 'Sort by column (method, path, name, middleware)',
            '--reverse, -r' => 'Reverse the sort order',
            '--compact, -c' => 'Compact display without middleware column',
            '--json' => 'Output as JSON',
        ];
    }

    public function handle(): int
    {
        $this->checkpoint('start');

        $this->title('Application Routes');

        // Get router instance
        $router = $this->getRouter();

        if (!$router) {
            $this->error('Router not found. Make sure the application is bootstrapped.');

            return 1;
        }

        $routes = $router->getRoutes();

        // Flatten nested routes array if necessary (method => [routes])
        if (!empty($routes) && is_array(reset($routes))) {
            $routes = array_merge(...array_values($routes));
        }

        if (empty($routes)) {
            $this->warning('No routes registered.');

            return 0;
        }

        $this->checkpoint('routes_loaded');

        // Filter routes
        $routes = $this->filterRoutes($routes);

        if (empty($routes)) {
            $this->warning('No routes match the specified filters.');

            return 0;
        }

        // Sort routes
        $routes = $this->sortRoutes($routes);

        $this->checkpoint('routes_processed');

        // Display routes
        if ($this->hasOption('json')) {
            $this->displayJson($routes);
        } else {
            $this->displayTable($routes);
        }

        $this->checkpoint('routes_displayed');

        // Display summary
        $this->displaySummary($routes);

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

    private function filterRoutes(array $routes): array
    {
        $method = $this->option('method');
        $name = $this->option('name');
        $path = $this->option('path');

        if (!$method && !$name && !$path) {
            return $routes;
        }

        return array_filter($routes, function ($route) use ($method, $name, $path) {
            // Filter by method
            if ($method && strcasecmp($route->getMethod(), $method) !== 0) {
                return false;
            }

            // Filter by name
            if ($name && !str_contains($route->getName() ?? '', $name)) {
                return false;
            }

            // Filter by path
            if ($path && !str_contains($route->getPath(), $path)) {
                return false;
            }

            return true;
        });
    }

    private function sortRoutes(array $routes): array
    {
        $sortBy = $this->option('sort') ?? 'path';
        $reverse = $this->hasOption('reverse') || $this->hasOption('r');

        usort($routes, function ($a, $b) use ($sortBy) {
            return match ($sortBy) {
                'method' => strcmp($a->getMethod(), $b->getMethod()),
                'name' => strcmp($a->getName() ?? '', $b->getName() ?? ''),
                'middleware' => count($a->getMiddleware()) <=> count($b->getMiddleware()),
                default => strcmp($a->getPath(), $b->getPath()),
            };
        });

        if ($reverse) {
            $routes = array_reverse($routes);
        }

        return $routes;
    }

    private function displayTable(array $routes): void
    {
        $compact = $this->hasOption('compact') || $this->hasOption('c');

        if ($compact) {
            $headers = ['Method', 'Path', 'Handler', 'Name'];
        } else {
            $headers = ['Method', 'Path', 'Handler', 'Middleware', 'Name'];
        }

        $rows = [];

        foreach ($routes as $route) {
            $method = $this->colorizeMethod($route->getMethod());
            $path = $route->getPath();
            $handler = $this->formatHandler($route->getHandler());
            $name = $route->getName() ?? '-';

            if ($compact) {
                $rows[] = [$method, $path, $handler, $name];
            } else {
                $middleware = $this->formatMiddleware($route->getMiddleware());
                $rows[] = [$method, $path, $handler, $middleware, $name];
            }
        }

        $this->table($headers, $rows);
    }

    private function displayJson(array $routes): void
    {
        $data = [];

        foreach ($routes as $route) {
            $data[] = [
                'method' => $route->getMethod(),
                'path' => $route->getPath(),
                'handler' => $this->formatHandler($route->getHandler()),
                'middleware' => $route->getMiddleware(),
                'name' => $route->getName(),
            ];
        }

        $this->line(json_encode($data, JSON_PRETTY_PRINT));
    }

    private function displaySummary(array $routes): void
    {
        $this->section('Statistics');

        $methods = [];
        $withMiddleware = 0;
        $named = 0;

        foreach ($routes as $route) {
            $method = $route->getMethod();
            $methods[$method] = ($methods[$method] ?? 0) + 1;

            if (!empty($route->getMiddleware())) {
                $withMiddleware++;
            }

            if ($route->getName()) {
                $named++;
            }
        }

        $this->keyValue('Total Routes', (string) count($routes));
        $this->keyValue('Named Routes', (string) $named);
        $this->keyValue('Protected Routes', (string) $withMiddleware);

        $this->newLine();
        $this->info('Routes by Method:');

        foreach ($methods as $method => $count) {
            $this->keyValue("  {$method}", (string) $count);
        }

        $this->newLine();
    }

    private function colorizeMethod(string $method): string
    {
        return match ($method) {
            'GET' => "\033[32m{$method}\033[0m",      // Green
            'POST' => "\033[33m{$method}\033[0m",     // Yellow
            'PUT' => "\033[34m{$method}\033[0m",      // Blue
            'PATCH' => "\033[35m{$method}\033[0m",    // Magenta
            'DELETE' => "\033[31m{$method}\033[0m",   // Red
            default => $method,
        };
    }

    private function formatHandler($handler): string
    {
        if (is_string($handler)) {
            // Shorten namespace for display
            $handler = str_replace('App\\Controllers\\', '', $handler);

            return $handler;
        }

        if (is_callable($handler)) {
            return 'Closure';
        }

        return 'Unknown';
    }

    private function formatMiddleware(array $middleware): string
    {
        if (empty($middleware)) {
            return '-';
        }

        $formatted = array_map(function ($mw) {
            if (is_string($mw)) {
                return basename(str_replace('\\', '/', $mw));
            }

            return 'Closure';
        }, $middleware);

        return implode(', ', $formatted);
    }
}
