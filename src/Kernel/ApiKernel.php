<?php

declare(strict_types = 1)
;

namespace Plugs\Kernel;

use Plugs\Http\Middleware\CorsMiddleware;
use Plugs\Http\Middleware\ForceJsonMiddleware;
use Plugs\Http\Middleware\HandleValidationExceptions;
use Plugs\Http\Middleware\PreventRequestsDuringMaintenance;
use Plugs\Http\Middleware\ProfilerMiddleware;
use Plugs\Http\Middleware\RateLimitMiddleware;
use Plugs\Http\Middleware\RoutingMiddleware;
use Plugs\Http\Middleware\SecurityHeadersMiddleware;
use Plugs\Http\ResponseFactory;
use Plugs\Plugs;
use Plugs\Router\Router;

/**
 * API Kernel — lean JSON API pipeline.
 *
 * No session, no CSRF, no view engine, no flash messages.
 * Optimized for stateless API requests with CORS, rate limiting,
 * and forced JSON responses.
 */
class ApiKernel extends AbstractKernel
{
    protected array $middlewareLayers = [
        'security' => [
            \Plugs\Http\Middleware\SecurityEngineMiddleware::class ,
            PreventRequestsDuringMaintenance::class ,
            SecurityHeadersMiddleware::class ,
            \Plugs\Http\Middleware\SecurityShieldMiddleware::class ,
            CorsMiddleware::class ,
            RateLimitMiddleware::class ,
        ],
        'performance' => [],
        'business' => [
            ForceJsonMiddleware::class ,
            HandleValidationExceptions::class ,
        ],
    ];

    protected function bootServices(): void
    {
        $this->loadProfilerMiddleware();
        $this->loadRoutes();
    }

    /**
     * Add profiler middleware if enabled.
     */
    private function loadProfilerMiddleware(): void
    {
        $securityConfig = config('security');
        if ($securityConfig['profiler']['enabled'] ?? false) {
            $this->middlewareLayers['performance'][] = ProfilerMiddleware::class;
        }
    }

    /**
     * Load API routes only.
     */
    private function loadRoutes(): void
    {
        /** @var Router $router */
        $router = $this->container->make('router');
        $basePath = defined('BASE_PATH') ? BASE_PATH : '';

        if (Plugs::isProduction() && $router->loadFromCache()) {
        // Loaded from cache
        }
        else {
            $router->loadInternalRoutes();

            // API routes with prefix
            $router->group([
                'prefix' => 'api',
                'middleware' => [ForceJsonMiddleware::class],
            ], function () use ($basePath, $router) {
                if (file_exists($basePath . 'routes/api.php')) {
                    require $basePath . 'routes/api.php';
                }

                // Load Feature Module API routes
                $this->loadFeatureModuleApiRoutes($router);
            });

            // Also load web routes for mixed-context apps
            if (file_exists($basePath . 'routes/web.php')) {
                require $basePath . 'routes/web.php';
            }

            // Load Feature Module web routes
            $this->loadFeatureModuleWebRoutes($router);
        }

        // Add routing middleware to business layer end
        $this->middlewareLayers['business'][] = RoutingMiddleware::class;
    }

    /**
     * Setup the fallback handler for the API kernel.
     */
    public function setupFallback(Plugs $app): void
    {
        $app->setFallback(function ($request) {
            return ResponseFactory::json([
            'error' => 'Not Found',
            'message' => 'The requested resource was not found',
            'path' => $request->getUri()->getPath(),
            ], 404);
        });
    }

    /**
     * Load web route files from all feature modules.
     */
    private function loadFeatureModuleWebRoutes(Router $router): void
    {
        $manager = \Plugs\FeatureModule\FeatureModuleManager::getInstance();

        foreach ($manager->getWebRouteEntries() as $entry) {
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
    }

    /**
     * Load API route files from all feature modules.
     */
    private function loadFeatureModuleApiRoutes(Router $router): void
    {
        $manager = \Plugs\FeatureModule\FeatureModuleManager::getInstance();

        foreach ($manager->getApiRouteEntries() as $entry) {
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
    }
}
