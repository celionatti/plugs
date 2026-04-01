<?php

declare(strict_types=1);

namespace Plugs\Kernel;

use Plugs\Console\ConsoleKernel as ConsoleCommandKernel;
use Plugs\Console\ConsolePlugs;

/**
 * CLI Kernel — console command pipeline.
 *
 * No HTTP middleware, no session, no CSRF, no routing.
 * Only boots database and console services needed for commands.
 */
class CliKernel extends AbstractKernel
{
    /**
     * CLI has no HTTP middleware layers.
     */
    protected array $middlewareLayers = [
        'security' => [],
        'performance' => [],
        'business' => [],
    ];

    private ?ConsoleCommandKernel $consoleKernel = null;
    private ?ConsolePlugs $consolePlug = null;

    protected function bootServices(): void
    {
        // Initialize the console command system
        $this->consoleKernel = new ConsoleCommandKernel();
        $this->consolePlug = new ConsolePlugs($this->consoleKernel);

        // Register in container for access
        $this->container->singleton(ConsoleCommandKernel::class, fn() => $this->consoleKernel);
        $this->container->singleton(ConsolePlugs::class, fn() => $this->consolePlug);

        // Load routes (for route:list and other route-aware commands)
        $this->loadRoutes();
    }

    /**
     * Load application routes for console awareness.
     */
    private function loadRoutes(): void
    {
        /** @var \Plugs\Router\Router $router */
        $router = $this->container->make('router');
        $basePath = defined('BASE_PATH') ? BASE_PATH : '';

        // Load internal framework routes
        $router->loadInternalRoutes();

        // Load main application routes
        if (file_exists($basePath . 'routes/web.php')) {
            require $basePath . 'routes/web.php';
        }
        if (file_exists($basePath . 'routes/api.php')) {
            require $basePath . 'routes/api.php';
        }

        // Load Feature Module routes
        $this->loadFeatureModuleRoutes($router);
    }

    /**
     * Load web and api routes from all feature modules.
     */
    private function loadFeatureModuleRoutes(\Plugs\Router\Router $router): void
    {
        $manager = \Plugs\FeatureModule\FeatureModuleManager::getInstance();

        // Web Routes
        foreach ($manager->getWebRouteEntries() as $entry) {
            $this->registerModuleRouteEntry($router, $entry);
        }

        // API Routes
        foreach ($manager->getApiRouteEntries() as $entry) {
            $this->registerModuleRouteEntry($router, $entry);
        }
    }

    private function registerModuleRouteEntry(\Plugs\Router\Router $router, array $entry): void
    {
        /** @var \Plugs\FeatureModule\FeatureModuleInterface $module */
        $module = $entry['module'];
        $file = $entry['file'];

        $attributes = [
            'namespace' => $module->getControllerNamespace(),
            'as' => $module->getRouteNamePrefix(),
        ];

        $prefix = $module->getRoutePrefix();
        if ($prefix !== '') {
            $attributes['prefix'] = $prefix;
        }

        $middleware = $module->getMiddleware();
        if (!empty($middleware)) {
            $attributes['middleware'] = $middleware;
        }

        $router->group($attributes, function () use ($file) {
            require $file;
        });
    }

    /**
     * Run the console application.
     *
     * @param array $argv Command-line arguments
     * @return int Exit code
     */
    public function handle(array $argv): int
    {
        if (!$this->isBooted()) {
            $this->boot();
        }

        return $this->consolePlug->run($argv);
    }

    /**
     * Get the underlying console kernel for command registration.
     */
    public function getConsoleKernel(): ConsoleCommandKernel
    {
        return $this->consoleKernel;
    }
}
