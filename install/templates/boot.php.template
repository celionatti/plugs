<?php

declare(strict_types=1);

/*
|--------------------------------------------------------------------------
| The Bootstrap File
|--------------------------------------------------------------------------
|
| This file is responsible for bootstrapping your application. You can use
| this file to set up any necessary configurations or load essential.
*/

use Plugs\Base\Model\PlugModel;
use Plugs\Http\Middleware\ProfilerMiddleware;
use Plugs\Http\ResponseFactory;
use Plugs\Http\Middleware\CorsMiddleware;
use Plugs\Http\Middleware\CsrfMiddleware;
use Plugs\Http\Middleware\RoutingMiddleware;
use Plugs\Http\Middleware\RateLimitMiddleware;
use Plugs\Http\Middleware\SecurityHeadersMiddleware;
use Plugs\Middlewares\SecurityShieldMiddleware;
use Plugs\Http\Message\ServerRequest;

/*
 |----------------------------------------------------------------------
 | Define Constants
 |----------------------------------------------------------------------
 */

define('BASE_PATH', dirname(__DIR__) . '/');
define('APP_PATH', BASE_PATH . 'app/');
define('CONFIG_PATH', BASE_PATH . 'config/');
define('PUBLIC_PATH', BASE_PATH . 'public/');
define('STORAGE_PATH', BASE_PATH . 'storage/');
define('VENDOR_PATH', BASE_PATH . 'vendor/');

/*
 |----------------------------------------------------------------------
 | Autoload Dependencies
 |----------------------------------------------------------------------
 |
 | Here we are loading the Composer autoloader to manage our dependencies.
 */
require VENDOR_PATH . 'autoload.php';

/*
 |----------------------------------------------------------------------
 | Load Environment Variables
 |----------------------------------------------------------------------
 |
 | Load environment variables from the .env file if it exists.
 */
$dotenv = Dotenv\Dotenv::createImmutable(BASE_PATH);
$dotenv->load();

/*
 |----------------------------------------------------------------------
 | Initialize Application
 |----------------------------------------------------------------------
 |
 | Here we can initialize the application, set up configurations, and
 | prepare any services needed for the application to run.
 */
$app = new Plugs\Plugs();

/*
 |----------------------------------------------------------------------
 | Container Bindings
 |----------------------------------------------------------------------
 |
 | Here we bind interfaces to their implementations in the service container.
 */
$container = \Plugs\Container\Container::getInstance();

/*
 |----------------------------------------------------------------------
 | Load Service Providers
 |----------------------------------------------------------------------
 |
 | Load and register service providers defined in the configuration.
 */
$serviceLoader = config('services');
$serviceLoader($container);

$databaseConfig = config('database');
PlugModel::setConnection($databaseConfig['connections'][$databaseConfig['default']]);

/*
 |----------------------------------------------------------------------
 | Load Session Configuration
 |----------------------------------------------------------------------
 |
 | Load session settings from the configuration and start the session.
 */
$sessionConfig = config('security.session');
$sessionLoader = new \Plugs\Session\SessionManager($sessionConfig);
$sessionLoader->start();

/*
 |----------------------------------------------------------------------
 | Load Security Configuration
 |----------------------------------------------------------------------
 |
 | Load security settings from the configuration.
 */
$securityConfig = config('security');

// Add SPA detection middleware
$app->pipe(new \Plugs\Http\Middleware\SPAMiddleware());

// Add security headers middleware
if (!empty($securityConfig['headers'])) {
    $app->pipe(new SecurityHeadersMiddleware($securityConfig['headers']));
}

// Add CORS middleware (if enabled)
if ($securityConfig['cors']['enabled'] ?? false) {
    $app->pipe(new CorsMiddleware(
        $securityConfig['cors']['allowed_origins'],
        $securityConfig['cors']['allowed_methods'],
        $securityConfig['cors']['allowed_headers'],
        $securityConfig['cors']['max_age']
    ));
}

// Add SecurityShield Middleware (Advanced Protection)
if ($securityConfig['security_shield']['enabled'] ?? false) {
    $shieldConfig = $securityConfig['security_shield'];

    // Create SecurityShield instance with configuration
    $securityShield = new SecurityShieldMiddleware($shieldConfig['config'] ?? []);

    // Configure rules (enable/disable specific checks)
    if (!empty($shieldConfig['rules'])) {
        foreach ($shieldConfig['rules'] as $rule => $enabled) {
            if ($enabled) {
                $securityShield->enableRule($rule);
            } else {
                $securityShield->disableRule($rule);
            }
        }
    }

    // Add whitelisted IPs
    if (!empty($shieldConfig['whitelist'])) {
        foreach ($shieldConfig['whitelist'] as $ip) {
            $securityShield->addToWhitelist($ip);
        }
    }

    // Add to middleware pipeline
    $app->pipe($securityShield);
}

// Add rate limiting middleware (if enabled)
// Note: You might want to disable this if SecurityShield is enabled
// since SecurityShield has its own rate limiting
if (($securityConfig['rate_limit']['enabled'] ?? false) && !($securityConfig['security_shield']['enabled'] ?? false)) {
    $app->pipe(new RateLimitMiddleware(
        $securityConfig['rate_limit']['max_requests'],
        $securityConfig['rate_limit']['per_minutes']
    ));
}

// Add CSRF protection middleware (if enabled)
if ($securityConfig['csrf']['enabled'] ?? false) {
    $app->pipe(new CsrfMiddleware($securityConfig['csrf']));
}

// Add Profiler Middleware
if ($securityConfig['profiler']['enabled'] ?? false) {
    $app->pipe(new ProfilerMiddleware());
}

/*
 |----------------------------------------------------------------------
 | Register Router in Container & Set Up Route Facade
 |----------------------------------------------------------------------
 |
 | Create the router instance and register it in the container as a singleton.
 | This makes it accessible via both the container and the Route facade.
 */
$router = new \Plugs\Router\Router();

// Register as singleton in container
$container->singleton('router', function () use ($router) {
    return $router;
});

// Also register Router class binding
$container->singleton(\Plugs\Router\Router::class, function () use ($router) {
    return $router;
});

// Also set directly in facade for immediate use (optional but faster)
\Plugs\Facades\Route::setFacadeInstance('router', $router);

/*
 |----------------------------------------------------------------------
 | Create and Register Request in Container
 |----------------------------------------------------------------------
 |
 | Create the PSR-7 ServerRequest from PHP globals and register it in
 | the container so routing helpers can access it.
 */
$request = ServerRequest::fromGlobals();

// Configure trusted proxies (if behind load balancer/CDN)
ServerRequest::setTrustedProxies([
    '10.0.0.1',           // Load balancer
    '172.16.0.0/12',      // Private network (if you support CIDR)
]);

// Configure trusted hosts
ServerRequest::setTrustedHosts([
    'yourdomain.com',
    'www.yourdomain.com',
    '.yourdomain.com',    // Wildcard for subdomains
]);

// Register request as singleton in container
$container->singleton(\Psr\Http\Message\ServerRequestInterface::class, function () use ($request) {
    return $request;
});

/*
 |----------------------------------------------------------------------
 | Load Routes
 |----------------------------------------------------------------------
 |
 | Load the application routes. You can now use Route::get() statically!
 */
require BASE_PATH . 'routes/web.php';

$router->enablePagesRouting(base_path('resources/pages'), [
    'namespace' => 'App\\Pages',
    // 'cache' => true, // Enable in production
]);
$router->loadPagesRoutes();

// Add routing middleware
$app->pipe(new RoutingMiddleware($router, $container));

// Set fallback handler for 404 errors
$app->setFallback(function ($request) {
    $acceptHeader = $request->getHeaderLine('Accept');

    // Return JSON for API requests
    if (strpos($acceptHeader, 'application/json') !== false) {
        return ResponseFactory::json([
            'error' => 'Not Found',
            'message' => 'The requested resource was not found',
            'path' => $request->getUri()->getPath(),
        ], 404);
    }

    // Return HTML for browser requests
    return ResponseFactory::html(
        '<!DOCTYPE html>
        <html lang="en">
        <head>
            <meta charset="UTF-8">
            <meta name="viewport" content="width=device-width, initial-scale=1.0">
            <title>404 - Not Found</title>
            <link rel="preconnect" href="https://fonts.googleapis.com">
            <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
            <style>
                @import url("https://fonts.googleapis.com/css2?family=Outfit:wght@300;400;600;700&family=JetBrains+Mono:wght@700&family=Dancing+Script:wght@700&display=swap");
                
                :root {
                    --bg-body: #080b12;
                    --bg-card: rgba(30, 41, 59, 0.5);
                    --border-color: rgba(255, 255, 255, 0.08);
                    --text-primary: #f8fafc;
                    --text-secondary: #94a3b8;
                    --accent-primary: #8b5cf6;
                    --accent-secondary: #3b82f6;
                }

                * { margin: 0; padding: 0; box-sizing: border-box; }
                
                body {
                    font-family: "Outfit", sans-serif;
                    background-color: var(--bg-body);
                    background-image: 
                        radial-gradient(circle at 10% 10%, rgba(139, 92, 246, 0.05) 0%, transparent 40%),
                        radial-gradient(circle at 90% 90%, rgba(59, 130, 246, 0.05) 0%, transparent 40%);
                    color: var(--text-primary);
                    min-height: 100vh;
                    display: flex;
                    align-items: center;
                    justify-content: center;
                    flex-direction: column;
                    padding: 20px;
                    overflow: hidden;
                }

                .brand-container {
                    position: absolute;
                    top: 40px;
                    text-align: center;
                }

                .brand {
                    font-family: "Dancing Script", cursive;
                    font-size: 2.5rem;
                    font-weight: 700;
                    color: var(--text-primary);
                    text-decoration: none;
                    background: linear-gradient(135deg, var(--accent-primary), var(--accent-secondary));
                    -webkit-background-clip: text;
                    -webkit-text-fill-color: transparent;
                    background-clip: text;
                }

                .error-card {
                    background: var(--bg-card);
                    backdrop-filter: blur(20px);
                    border: 1px solid var(--border-color);
                    border-radius: 24px;
                    padding: 60px 40px;
                    max-width: 500px;
                    width: 100%;
                    text-align: center;
                    box-shadow: 0 25px 50px -12px rgba(0, 0, 0, 0.5);
                }

                .error-icon {
                    font-size: 4rem;
                    margin-bottom: 24px;
                    display: block;
                }

                .error-code {
                    font-family: "JetBrains Mono", monospace;
                    font-size: 6rem;
                    line-height: 1;
                    font-weight: 700;
                    margin-bottom: 16px;
                    opacity: 0.2;
                    letter-spacing: -2px;
                }

                h1 {
                    font-size: 2rem;
                    font-weight: 700;
                    margin-bottom: 16px;
                }

                .message {
                    color: var(--text-secondary);
                    font-size: 1.1rem;
                    line-height: 1.6;
                    margin-bottom: 40px;
                }

                .actions {
                    display: flex;
                    gap: 16px;
                    justify-content: center;
                }

                .btn {
                    padding: 12px 28px;
                    border-radius: 12px;
                    text-decoration: none;
                    font-weight: 600;
                    font-size: 0.95rem;
                    transition: all 0.3s ease;
                    display: inline-flex;
                    align-items: center;
                    gap: 8px;
                    cursor: pointer;
                }

                .btn-primary {
                    background: linear-gradient(135deg, var(--accent-primary), var(--accent-secondary));
                    color: white;
                    box-shadow: 0 10px 15px -3px rgba(139, 92, 246, 0.3);
                }

                .btn-primary:hover {
                    transform: translateY(-2px);
                    box-shadow: 0 20px 25px -5px rgba(139, 92, 246, 0.4);
                }

                .btn-secondary {
                    background: rgba(255, 255, 255, 0.05);
                    border: 1px solid var(--border-color);
                    color: var(--text-primary);
                }

                .btn-secondary:hover {
                    background: rgba(255, 255, 255, 0.1);
                    transform: translateY(-2px);
                }

                @media (max-width: 640px) {
                    .error-card { padding: 40px 20px; }
                    .error-code { font-size: 4.5rem; }
                    .actions { flex-direction: column; }
                    .btn { width: 100%; justify-content: center; }
                }
            </style>
        </head>
        <body>
            <div class="brand-container">
                <a href="/" class="brand">Plugs</a>
            </div>

            <div class="error-card">
                <span class="error-icon">🌌</span>
                <div class="error-code">404</div>
                <h1>Page Not Found</h1>
                <p class="message">The requested page has vanished into the deep space of our server. It might have been moved or deleted.</p>
                <div class="actions">
                    <a href="/" class="btn btn-primary">
                        <span>🏠</span> Return Home
                    </a>
                    <button onclick="window.location.reload()" class="btn btn-secondary">
                        <span>🔄</span> Try Again
                    </button>
                </div>
            </div>
        </body>
        </html>',
        404
    );
});

/*
 |----------------------------------------------------------------------
 | Return the Application Instance
 |----------------------------------------------------------------------
 |
 | Finally, we return the application instance to be used in the entry point.
 */
return $app;
