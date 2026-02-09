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
        // Accessing private routes property via reflection for inspection
        $reflection = new ReflectionClass($router);
        $property = $reflection->getProperty('routes');
        $property->setAccessible(true);
        $routes = $property->getValue($router);

        $paths = [];

        foreach ($routes as $method => $methodRoutes) {
            foreach ($methodRoutes as $route) {
                $uri = '/' . ltrim($route->maxUri, '/');
                $uri = preg_replace('/\{(\w+)\}/', '{$1}', $uri); // Normalize params

                if (!isset($paths[$uri])) {
                    $paths[$uri] = [];
                }

                $operation = [
                    'summary' => 'Generated Route',
                    'responses' => [
                        '200' => [
                            'description' => 'Success'
                        ]
                    ]
                ];

                // If we had route metadata, we'd add it here.
                // For now, we infer from basic structure.

                $paths[$uri][strtolower($method)] = $operation;
            }
        }

        $spec = [
            'openapi' => '3.0.0',
            'info' => [
                'title' => 'Plugs API',
                'version' => '1.0.0'
            ],
            'paths' => $paths
        ];

        $json = json_encode($spec, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);

        $outputPath = 'openapi.json';
        file_put_contents($outputPath, $json);

        $this->success("OpenAPI spec generated at: {$outputPath}");

        return 0;
    }
}
