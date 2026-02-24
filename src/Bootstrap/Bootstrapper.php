<?php

declare(strict_types=1);

namespace Plugs\Bootstrap;

use Plugs\Base\Model\PlugModel;
use Plugs\Container\Container;
use Plugs\Http\Message\ServerRequest;
use Plugs\Http\Middleware\CorsMiddleware;
use Plugs\Http\Middleware\CsrfMiddleware;
use Plugs\Http\Middleware\ProfilerMiddleware;
use Plugs\Http\Middleware\RateLimitMiddleware;
use Plugs\Http\Middleware\RoutingMiddleware;
use Plugs\Http\Middleware\SecurityHeadersMiddleware;
use Plugs\Http\ResponseFactory;
use Plugs\Http\Middleware\SecurityShieldMiddleware;
use Plugs\Plugs;
use Plugs\Router\Router;
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
        $this->registerRegistry();
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
        if (!defined('BASE_PATH')) {
            define('BASE_PATH', $this->basePath);
        }
        if (!defined('APP_PATH')) {
            define('APP_PATH', BASE_PATH . 'app/');
        }
        if (!defined('CONFIG_PATH')) {
            define('CONFIG_PATH', BASE_PATH . 'config/');
        }
        if (!defined('PUBLIC_PATH')) {
            define('PUBLIC_PATH', BASE_PATH . 'public/');
        }
        if (!defined('STORAGE_PATH')) {
            define('STORAGE_PATH', BASE_PATH . 'storage/');
        }
        if (!defined('VENDOR_PATH')) {
            define('VENDOR_PATH', BASE_PATH . 'vendor/');
        }
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

    protected function registerRegistry(): void
    {
        $registry = new \Plugs\Http\Middleware\MiddlewareRegistry(config('middleware'));
        $this->container->singleton(\Plugs\Http\Middleware\MiddlewareRegistry::class, fn() => $registry);
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

        // With the new MiddlewareDispatcher, Kernel middlewares are automatically added.
        // We only need to pipe specific groups or dynamic middlewares here.

        // Initialize and pipe Security Shield if enabled
        if ($securityConfig['security_shield']['enabled'] ?? false) {
            $shieldConfig = $securityConfig['security_shield'];
            $securityShield = new SecurityShieldMiddleware($shieldConfig['config'] ?? []);

            // Rules and Whitelist are now better handled via config, 
            // but we keep backward compatibility here if needed.

            $this->app->pipe($securityShield);
        }

        // Add additional web middleware group (resolved by Registry)
        $this->app->pipe('web');

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

        // Register Default Middleware Groups
        $router->middlewareGroup('web', [
            // 'csrf', // We can use aliases in groups now that Router expands them
            // But for safety let's use what we know works
        ]);

        // Load groups from config if available
        $middlewareConfig = config('middleware');
        if (isset($middlewareConfig['groups'])) {
            foreach ($middlewareConfig['groups'] as $group => $middlewares) {
                $router->middlewareGroup($group, $middlewares);
            }
        }
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
