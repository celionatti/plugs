<?php

declare(strict_types=1);

namespace Plugs\Bootstrap;

use Plugs\Base\Model\PlugModel;
use Plugs\Http\Message\ServerRequest;
use Plugs\Http\Middleware\CorsMiddleware;
use Plugs\Http\Middleware\CsrfMiddleware;
use Plugs\Http\Middleware\ProfilerMiddleware;
use Plugs\Http\Middleware\RateLimitMiddleware;
use Plugs\Http\Middleware\RoutingMiddleware;
use Plugs\Http\Middleware\SecurityHeadersMiddleware;
use Plugs\Http\ResponseFactory;
use Plugs\Middlewares\SecurityShieldMiddleware;
use Plugs\Plugs;
use Plugs\Router\Router;
use Plugs\Container\Container;
use Plugs\Session\SessionManager;
use Psr\Http\Message\ServerRequestInterface;

class Bootstrapper
{
    protected string $basePath;
    protected Plugs $app;
    protected Container $container;

    public function __construct(string $basePath)
    {
        $this->basePath = rtrim($basePath, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR;
        $this->container = Container::getInstance();
    }

    public function boot(): Plugs
    {
        $this->defineConstants();
        $this->loadEnvironment();
        $this->initializeApplication();
        $this->configureDatabase();
        $this->configureSession();
        $this->registerMiddlewares();
        $this->setupRouter();
        $this->setupRequest();
        $this->loadRoutes();
        $this->setupFallback();

        return $this->app;
    }

    protected function defineConstants(): void
    {
        if (!defined('BASE_PATH'))
            define('BASE_PATH', $this->basePath);
        if (!defined('APP_PATH'))
            define('APP_PATH', BASE_PATH . 'app/');
        if (!defined('CONFIG_PATH'))
            define('CONFIG_PATH', BASE_PATH . 'config/');
        if (!defined('PUBLIC_PATH'))
            define('PUBLIC_PATH', BASE_PATH . 'public/');
        if (!defined('STORAGE_PATH'))
            define('STORAGE_PATH', BASE_PATH . 'storage/');
        if (!defined('VENDOR_PATH'))
            define('VENDOR_PATH', BASE_PATH . 'vendor/');
    }

    protected function loadEnvironment(): void
    {
        if (!\Plugs\Config::loadFromCache()) {
            if (file_exists($this->basePath . '.env')) {
                $dotenv = \Dotenv\Dotenv::createImmutable($this->basePath);
                $dotenv->load();
            }
        }
    }

    protected function initializeApplication(): void
    {
        $this->app = new Plugs();
    }

    protected function configureDatabase(): void
    {
        $databaseConfig = config('database');
        if ($databaseConfig) {
            PlugModel::setConnection($databaseConfig['connections'][$databaseConfig['default']]);
        }
    }

    protected function configureSession(): void
    {
        $sessionConfig = config('security.session');
        if ($sessionConfig) {
            $sessionLoader = new SessionManager($sessionConfig);
            $sessionLoader->start();
        }
    }

    protected function registerMiddlewares(): void
    {
        $securityConfig = config('security');

        // Core Middlewares
        $this->app->pipe(new \Plugs\Http\Middleware\SPAMiddleware());
        $this->app->pipe(new \Plugs\Http\Middleware\FlashMiddleware());
        $this->app->pipe(new \Plugs\Http\Middleware\HandleValidationExceptions());

        // Security Headers
        if (!empty($securityConfig['headers'])) {
            $this->app->pipe(new SecurityHeadersMiddleware($securityConfig['headers']));
        }

        // CORS
        if ($securityConfig['cors']['enabled'] ?? false) {
            $this->app->pipe(new CorsMiddleware(
                $securityConfig['cors']['allowed_origins'],
                $securityConfig['cors']['allowed_methods'],
                $securityConfig['cors']['allowed_headers'],
                $securityConfig['cors']['max_age']
            ));
        }

        // Security Shield
        if ($securityConfig['security_shield']['enabled'] ?? false) {
            $shieldConfig = $securityConfig['security_shield'];
            $securityShield = new SecurityShieldMiddleware($shieldConfig['config'] ?? []);

            if (!empty($shieldConfig['rules'])) {
                foreach ($shieldConfig['rules'] as $rule => $enabled) {
                    $enabled ? $securityShield->enableRule($rule) : $securityShield->disableRule($rule);
                }
            }

            if (!empty($shieldConfig['whitelist'])) {
                foreach ($shieldConfig['whitelist'] as $ip) {
                    $securityShield->addToWhitelist($ip);
                }
            }

            $this->app->pipe($securityShield);
        }

        // Rate Limiting
        if (($securityConfig['rate_limit']['enabled'] ?? false) && !($securityConfig['security_shield']['enabled'] ?? false)) {
            $this->app->pipe(new RateLimitMiddleware(
                $securityConfig['rate_limit']['max_requests'],
                $securityConfig['rate_limit']['per_minutes']
            ));
        }

        // CSRF
        if ($securityConfig['csrf']['enabled'] ?? false) {
            $this->app->pipe(new CsrfMiddleware($securityConfig['csrf']));
        }

        // Profiler
        if ($securityConfig['profiler']['enabled'] ?? false) {
            $this->app->pipe(new ProfilerMiddleware());
        }
    }

    protected function setupRouter(): void
    {
        $router = new Router();

        $this->container->singleton('router', fn() => $router);
        $this->container->singleton(Router::class, fn() => $router);
        \Plugs\Facades\Route::setFacadeInstance('router', $router);

        $this->app->pipe(new RoutingMiddleware($router));
    }

    protected function setupRequest(): void
    {
        $request = ServerRequest::fromGlobals();

        // Register request as singleton
        $this->container->singleton(ServerRequestInterface::class, fn() => $request);
    }

    protected function loadRoutes(): void
    {
        /** @var Router $router */
        $router = $this->container->make('router');

        if (Plugs::isProduction() && $router->loadFromPersistentCache()) {
            // Loaded from cache
        } else {
            $router->loadInternalRoutes();
            if (file_exists($this->basePath . 'routes/web.php')) {
                require $this->basePath . 'routes/web.php';
            }

            $router->group([
                'prefix' => 'api',
                'middleware' => [\Plugs\Http\Middleware\ForceJsonMiddleware::class],
            ], function () {
                if (file_exists($this->basePath . 'routes/api.php')) {
                    require $this->basePath . 'routes/api.php';
                }
            });
        }

        $router->enablePagesRouting($this->basePath . 'resources/pages', [
            'namespace' => 'App\\Pages',
            'cache' => Plugs::isProduction(),
            /** @phpstan-ignore constant.notFound */
            'cache_file' => STORAGE_PATH . 'framework/pages_routes.php',
        ]);
        $router->loadPagesRoutes();
    }

    protected function setupFallback(): void
    {
        $this->app->setFallback(function ($request) {
            $acceptHeader = $request->getHeaderLine('Accept');

            if (strpos($acceptHeader, 'application/json') !== false) {
                return ResponseFactory::json([
                    'error' => 'Not Found',
                    'message' => 'The requested resource was not found',
                    'path' => $request->getUri()->getPath(),
                ], 404);
            }

            return ResponseFactory::html(
                getProductionErrorHtml(404, 'Page Not Found', 'The requested page has vanished into the deep space of our server.'),
                404
            );
        });
    }
}
