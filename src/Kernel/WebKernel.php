<?php

declare(strict_types=1);

namespace Plugs\Kernel;

use Plugs\Http\Middleware\CorsMiddleware;
use Plugs\Http\Middleware\CsrfMiddleware;
use Plugs\Http\Middleware\FlashMiddleware;
use Plugs\Http\Middleware\HandleValidationExceptions;
use Plugs\Http\Middleware\PreventRequestsDuringMaintenance;
use Plugs\Http\Middleware\ProfilerMiddleware;
use Plugs\Http\Middleware\RoutingMiddleware;
use Plugs\Http\Middleware\SecurityHeadersMiddleware;
use Plugs\Http\Middleware\SecurityShieldMiddleware;
use Plugs\Http\Middleware\ShareErrorsFromSession;
use Plugs\Http\Middleware\SPAMiddleware;
use Plugs\Http\ResponseFactory;
use Plugs\Plugs;
use Plugs\Router\Router;

/**
 * Web Kernel — full browser request pipeline.
 *
 * Boots session, CSRF, view engine, security headers, flash messages,
 * SPA middleware, and the complete routing layer with web routes.
 */
class WebKernel extends AbstractKernel
{
    protected array $middlewareLayers = [
        'security' => [
            \Plugs\Http\Middleware\SecurityEngineMiddleware::class,
            PreventRequestsDuringMaintenance::class,
            SecurityHeadersMiddleware::class,
        ],
        'performance' => [],
        'business' => [
            SPAMiddleware::class,
            FlashMiddleware::class,
            ShareErrorsFromSession::class,
            HandleValidationExceptions::class,
        ],
    ];

    protected function bootServices(): void
    {
        $this->configureDatabase();
        $this->configureSession();
        $this->setupRouter();
        $this->setupRequest();
        $this->loadSecurityMiddleware();
        $this->loadProfilerMiddleware();
        $this->loadRoutes();
    }

    /**
     * Add security shield if enabled in config.
     */
    private function loadSecurityMiddleware(): void
    {
        // SecurityShield is mandatory and enforced by default
        array_splice($this->middlewareLayers['security'], 2, 0, [
            SecurityShieldMiddleware::class,
        ]);

        // CSRF is always part of web security
        $this->middlewareLayers['security'][] = CsrfMiddleware::class;

        // CORS for web
        $this->middlewareLayers['security'][] = CorsMiddleware::class;
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
     * Load web routes and page routes.
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

            if (file_exists($basePath . 'routes/web.php')) {
                require $basePath . 'routes/web.php';
            }

            // Also load API routes in web kernel for unified routing
            $router->group([
                'prefix' => 'api',
                'middleware' => [\Plugs\Http\Middleware\ForceJsonMiddleware::class],
            ], function () use ($basePath) {
                if (file_exists($basePath . 'routes/api.php')) {
                    require $basePath . 'routes/api.php';
                }
            });
        }

        $router->enablePagesRouting($basePath . 'resources/pages', [
            'namespace' => 'App\\Pages',
            'cache' => Plugs::isProduction(),
            'cache_file' => (defined('STORAGE_PATH') ? STORAGE_PATH : $basePath . 'storage/') . 'framework/pages_routes.php',
        ]);
        $router->loadPagesRoutes();

        // Add routing middleware to business layer end
        $this->middlewareLayers['business'][] = RoutingMiddleware::class;
    }

    /**
     * Setup the fallback handler for the web kernel.
     */
    public function setupFallback(Plugs $app): void
    {
        $app->setFallback(function ($request) {
            $acceptHeader = $request->getHeaderLine('Accept');

            if (strpos($acceptHeader, 'application/json') !== false) {
                return ResponseFactory::json([
                    'error' => 'Not Found',
                    'message' => 'The requested resource was not found',
                    'path' => $request->getUri()->getPath(),
                ], 404);
            }

            $nonce = $request->getAttribute('csp_nonce');
            return ResponseFactory::html(
                getProductionErrorHtml(404, 'Page Not Found', 'The requested page has vanished into the deep space of our server.', $nonce),
                404
            );
        });
    }
}
