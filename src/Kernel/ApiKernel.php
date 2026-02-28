<?php

declare(strict_types=1);

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
            \Plugs\Http\Middleware\SecurityEngineMiddleware::class,
            PreventRequestsDuringMaintenance::class,
            SecurityHeadersMiddleware::class,
            \Plugs\Http\Middleware\SecurityShieldMiddleware::class,
            CorsMiddleware::class,
            RateLimitMiddleware::class,
        ],
        'performance' => [],
        'business' => [
            ForceJsonMiddleware::class,
            HandleValidationExceptions::class,
        ],
    ];

    protected function bootServices(): void
    {
        $this->configureDatabase();
        $this->setupRouter();
        $this->setupRequest();
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

        if (Plugs::isProduction() && $router->loadFromPersistentCache()) {
            // Loaded from cache
        } else {
            $router->loadInternalRoutes();

            // API routes with prefix
            $router->group([
                'prefix' => 'api',
                'middleware' => [ForceJsonMiddleware::class],
            ], function () use ($basePath) {
                if (file_exists($basePath . 'routes/api.php')) {
                    require $basePath . 'routes/api.php';
                }
            });

            // Also load web routes for mixed-context apps
            if (file_exists($basePath . 'routes/web.php')) {
                require $basePath . 'routes/web.php';
            }
        }

        // Add routing middleware to business layer end
        $this->middlewareLayers['business'][] = RoutingMiddleware::class;
    }

    /**
     * Setup the fallback handler for the API kernel.
     */
    public function setupFallback(\Plugs\Plugs $app): void
    {
        $app->setFallback(function ($request) {
            return ResponseFactory::json([
                'error' => 'Not Found',
                'message' => 'The requested resource was not found',
                'path' => $request->getUri()->getPath(),
            ], 404);
        });
    }
}
