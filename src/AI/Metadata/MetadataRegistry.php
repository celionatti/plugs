<?php

declare(strict_types=1);

namespace Plugs\AI\Metadata;

use Plugs\Container\Container;
use Plugs\Router\Router;

class MetadataRegistry
{
    protected Container $container;

    public function __construct(Container $container)
    {
        $this->container = $container;
    }

    /**
     * Get a full snapshot of the application metadata for AI context.
     */
    public function getSnapshot(): array
    {
        return [
            'routes' => $this->getRoutes(),
            'container' => $this->getContainerGraph(),
            'database' => $this->getSchema(),
            'environment' => $this->getEnvironment(),
            'framework' => [
                'name' => 'Plugs',
                'version' => '2.0.0',
            ],
        ];
    }

    protected function getRoutes(): array
    {
        if ($this->container->has(Router::class)) {
            return $this->container->make(Router::class)->getRouteMap();
        }

        return [];
    }

    protected function getContainerGraph(): array
    {
        return $this->container->getGraph();
    }

    protected function getSchema(): array
    {
        if ($this->container->has('db')) {
            return $this->container->make('db')->getSchemaMetadata();
        }

        return [];
    }

    protected function getEnvironment(): array
    {
        return [
            'php_version' => PHP_VERSION,
            'os' => PHP_OS,
            'sapi' => PHP_SAPI,
            'debug' => config('app.debug', false),
            'env' => config('app.env', 'production'),
        ];
    }
}
