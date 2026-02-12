<?php

declare(strict_types=1);

namespace Plugs\Console\Commands;

use Plugs\Console\Command;
use Plugs\Router\Router;
use Plugs\Container\Container;
use ReflectionClass;
use ReflectionMethod;

class OpenApiGenerator extends Command
{
    protected string $signature = 'route:openapi';
    protected string $description = 'Generate OpenAPI JSON specification from routes.';

    public function handle(): int
    {
        $this->info("Scanning routes for OpenAPI generation...");

        $router = Container::getInstance()->make(Router::class);
        $reflection = new ReflectionClass($router);

        $routesProperty = $reflection->getProperty('routes');
        $routesProperty->setAccessible(true);
        $routes = $routesProperty->getValue($router);

        $staticRoutesProperty = $reflection->getProperty('staticRoutes');
        $staticRoutesProperty->setAccessible(true);
        $staticRoutes = $staticRoutesProperty->getValue($router);

        $allRoutes = [];
        foreach ($routes as $method => $methodRoutes) {
            foreach ($methodRoutes as $route) {
                $allRoutes[] = $route;
            }
        }
        foreach ($staticRoutes as $method => $methodRoutes) {
            foreach ($methodRoutes as $route) {
                $allRoutes[] = $route;
            }
        }

        $paths = [];

        foreach ($allRoutes as $route) {
            $method = strtolower($route->getMethod());
            $uri = '/' . ltrim($route->getPath(), '/');
            $uri = preg_replace('/\{(\w+)\}/', '{$1}', $uri);

            if (!isset($paths[$uri])) {
                $paths[$uri] = [];
            }

            $summary = 'Generated Route';
            $description = '';
            $handler = $route->getHandler();

            // Extract metadata from Controller
            if (is_string($handler) && strpos($handler, '@') !== false) {
                [$controller, $action] = explode('@', $handler);
                if (class_exists($controller)) {
                    $ref = new ReflectionMethod($controller, $action);
                    $doc = $ref->getDocComment();
                    if ($doc) {
                        $doc = preg_replace('/^\s*(\/\*\*|\*\/|\*)/m', '', $doc);
                        $lines = array_filter(array_map('trim', explode("\n", $doc)));
                        $summary = array_shift($lines) ?: 'Generated Route';
                        $description = implode("\n", $lines);
                    }
                }
            }

            $operation = [
                'summary' => $summary,
                'description' => $description,
                'responses' => [
                    '200' => [
                        'description' => 'Success'
                    ]
                ]
            ];

            $paths[$uri][$method] = $operation;
        }

        $spec = [
            'openapi' => '3.0.0',
            'info' => [
                'title' => 'Plugs API',
                'description' => 'Automatically generated API documentation for Plugs Framework.',
                'version' => '1.0.0'
            ],
            'paths' => $paths
        ];

        $json = json_encode($spec, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
        file_put_contents('openapi.json', $json);

        $this->success("OpenAPI spec generated at: openapi.json");

        return 0;
    }
}
