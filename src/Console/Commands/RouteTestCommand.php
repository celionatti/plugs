<?php

declare(strict_types=1);

namespace Plugs\Console\Commands;

/*
|--------------------------------------------------------------------------
| RouteClearCommand Class
|--------------------------------------------------------------------------
*/

use Plugs\Router\Router;
use Plugs\Console\Command;

class RouteTestCommand extends Command
{
    protected string $description = 'Test a route to see which handler it matches';

    protected function defineArguments(): array
    {
        return [
            'path' => 'The path to test (e.g., /users/123)',
        ];
    }

    protected function defineOptions(): array
    {
        return [
            '--method=METHOD' => 'HTTP method to test (default: GET)',
        ];
    }

    public function handle(): int
    {
        $this->title('Route Tester');
        
        $path = $this->argument('0');
        
        if (!$path) {
            $path = $this->ask('Enter path to test', '/');
        }
        
        $method = $this->option('method') ?? 'GET';
        $method = strtoupper($method);
        
        $this->section('Testing Route');
        
        $this->keyValue('Path', $path);
        $this->keyValue('Method', $method);
        
        $this->newLine();
        
        // Get router
        $router = $this->getRouter();
        
        if (!$router) {
            $this->error('Router not found.');
            return 1;
        }
        
        // Find matching route
        $matchedRoute = null;
        $params = [];
        
        foreach ($router->getRoutes() as $route) {
            if ($route->getMethod() !== $method) {
                continue;
            }
            
            if (preg_match($route->getPattern(), $path, $matches)) {
                $matchedRoute = $route;
                $params = array_filter($matches, 'is_string', ARRAY_FILTER_USE_KEY);
                break;
            }
        }
        
        if ($matchedRoute) {
            $this->displayMatchedRoute($matchedRoute, $params);
        } else {
            $this->displayNoMatch($path, $method);
        }
        
        return 0;
    }

    private function displayMatchedRoute($route, array $params): void
    {
        $this->box(
            "Route matched successfully!\n\n" .
            "Handler: " . $this->formatHandler($route->getHandler()) . "\n" .
            "Pattern: {$route->getPath()}\n" .
            ($route->getName() ? "Name: {$route->getName()}\n" : "") .
            (!empty($params) ? "Parameters: " . json_encode($params) : ""),
            "✅ Match Found",
            "success"
        );
        
        if (!empty($route->getMiddleware())) {
            $this->newLine();
            $this->section('Middleware Stack');
            
            foreach ($route->getMiddleware() as $middleware) {
                $name = is_string($middleware) ? basename(str_replace('\\', '/', $middleware)) : 'Closure';
                $this->success("  → {$name}");
            }
        }
        
        if (!empty($params)) {
            $this->newLine();
            $this->section('Extracted Parameters');
            
            foreach ($params as $key => $value) {
                $this->keyValue($key, $value);
            }
        }
    }

    private function displayNoMatch(string $path, string $method): void
    {
        $this->box(
            "No route matches the given path and method.\n\n" .
            "Path: {$path}\n" .
            "Method: {$method}",
            "❌ No Match",
            "error"
        );
        
        $this->newLine();
        $this->section('Similar Routes');
        
        $router = $this->getRouter();
        $similar = $this->findSimilarRoutes($router, $path, $method);
        
        if (!empty($similar)) {
            foreach ($similar as $route) {
                $this->info("  • {$route->getMethod()} {$route->getPath()}");
            }
        } else {
            $this->note('No similar routes found.');
        }
    }

    private function findSimilarRoutes($router, string $path, string $method): array
    {
        $similar = [];
        
        foreach ($router->getRoutes() as $route) {
            // Same method, similar path
            if ($route->getMethod() === $method) {
                $similarity = 0;
                similar_text($path, $route->getPath(), $similarity);
                
                if ($similarity > 60) {
                    $similar[] = $route;
                }
            }
        }
        
        return array_slice($similar, 0, 5);
    }

    private function getRouter(): ?Router
    {
        try {
            return app(Router::class);
        } catch (\Throwable $e) {
            return null;
        }
    }

    private function formatHandler($handler): string
    {
        if (is_string($handler)) {
            return str_replace('App\\Controllers\\', '', $handler);
        }
        
        if (is_callable($handler)) {
            return 'Closure';
        }
        
        return 'Unknown';
    }
}