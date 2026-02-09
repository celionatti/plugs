<?php

declare(strict_types=1);

namespace Plugs\Http\Controllers;

use Plugs\Container\Container;
use Plugs\Http\ResponseFactory;
use Plugs\Router\Router;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use ReflectionClass;

/**
 * OpenAPI Documentation Controller.
 * Serves automatic API documentation.
 */
class OpenApiController
{
    private string $specPath;

    public function __construct()
    {
        $this->specPath = base_path('openapi.json');
    }

    /**
     * Serve OpenAPI JSON spec.
     */
    public function spec(ServerRequestInterface $request): ResponseInterface
    {
        // Auto-regenerate if stale
        if ($this->isStale()) {
            $this->generateSpec();
        }

        if (!file_exists($this->specPath)) {
            $this->generateSpec();
        }

        $spec = file_get_contents($this->specPath);

        return ResponseFactory::create($spec, 200, [
            'Content-Type' => 'application/json',
        ]);
    }

    /**
     * Serve Swagger UI.
     */
    public function ui(ServerRequestInterface $request): ResponseInterface
    {
        $html = $this->getSwaggerUI();

        return ResponseFactory::create($html, 200, [
            'Content-Type' => 'text/html',
        ]);
    }

    /**
     * Check if spec needs regeneration.
     */
    private function isStale(): bool
    {
        if (!file_exists($this->specPath)) {
            return true;
        }

        // Check if routes file is newer than spec
        $routesPath = base_path('routes/api.php');
        if (file_exists($routesPath)) {
            return filemtime($routesPath) > filemtime($this->specPath);
        }

        return false;
    }

    /**
     * Generate OpenAPI spec.
     */
    private function generateSpec(): void
    {
        $router = Container::getInstance()->make(Router::class);

        $reflection = new ReflectionClass($router);
        $property = $reflection->getProperty('routes');
        $property->setAccessible(true);
        $routes = $property->getValue($router);

        $paths = [];

        foreach ($routes as $method => $methodRoutes) {
            foreach ($methodRoutes as $route) {
                $uri = '/' . ltrim($route->maxUri ?? $route->getPath(), '/');
                $uri = preg_replace('/\{(\w+)\}/', '{$1}', $uri);

                if (!isset($paths[$uri])) {
                    $paths[$uri] = [];
                }

                $paths[$uri][strtolower($method)] = [
                    'summary' => $route->getName() ?? 'API Endpoint',
                    'tags' => $this->inferTags($uri),
                    'responses' => [
                        '200' => ['description' => 'Success'],
                        '400' => ['description' => 'Bad Request'],
                        '401' => ['description' => 'Unauthorized'],
                        '500' => ['description' => 'Server Error'],
                    ],
                ];
            }
        }

        $spec = [
            'openapi' => '3.0.0',
            'info' => [
                'title' => config('app.name', 'Plugs') . ' API',
                'version' => config('app.version', '1.0.0'),
                'description' => 'Auto-generated API documentation',
            ],
            'servers' => [
                ['url' => config('app.url', '/'), 'description' => 'Current server'],
            ],
            'paths' => $paths,
        ];

        file_put_contents($this->specPath, json_encode($spec, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
    }

    /**
     * Infer tags from URI.
     */
    private function inferTags(string $uri): array
    {
        $parts = explode('/', trim($uri, '/'));

        // Skip version prefixes
        if (preg_match('/^v\d+$/', $parts[0] ?? '')) {
            array_shift($parts);
        }

        return [ucfirst($parts[0] ?? 'General')];
    }

    /**
     * Get Swagger UI HTML.
     */
    private function getSwaggerUI(): string
    {
        return <<<HTML
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>API Documentation</title>
    <link rel="stylesheet" href="https://unpkg.com/swagger-ui-dist@5/swagger-ui.css">
    <style>
        body { margin: 0; padding: 0; }
        .swagger-ui .topbar { display: none; }
    </style>
</head>
<body>
    <div id="swagger-ui"></div>
    <script src="https://unpkg.com/swagger-ui-dist@5/swagger-ui-bundle.js"></script>
    <script>
        SwaggerUIBundle({
            url: '/_plugs/docs/spec',
            dom_id: '#swagger-ui',
            presets: [SwaggerUIBundle.presets.apis],
            layout: 'BaseLayout'
        });
    </script>
</body>
</html>
HTML;
    }
}
