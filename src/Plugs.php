<?php

declare(strict_types=1);

namespace Plugs;

/*
|--------------------------------------------------------------------------
| Plugs Class
|--------------------------------------------------------------------------
|
| This is the main class for the Plugs framework. It serves as the entry
| point for the application and can be used to initialize and run the.
*/

use Plugs\Http\Message\ServerRequest;
use Plugs\Http\Message\Stream;
use Plugs\Http\MiddlewareDispatcher;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;

class Plugs
{
    private $dispatcher;
    private $fallbackHandler;
    private $container;

    /**
     * Essential bootstrappers — always run on every request.
     *
     * @var string[]
     */
    protected array $bootstrappers = [
        'functions' => 'loadFunctions',
        'logger' => 'bootstrapLogger',
        'cache' => 'bootstrapCache',
        'view' => 'bootstrapView',
        'events' => 'bootstrapEvents',
    ];

    /**
     * Deferred bootstrappers — registered lazily, booted on first access.
     *
     * @var string[]
     */
    protected array $deferredBootstrappers = [
        'auth' => 'bootstrapAuth',
        'queue' => 'bootstrapQueue',
        'storage' => 'bootstrapStorage',
        'database' => 'bootstrapDatabase',
        'socialite' => 'bootstrapSocialite',
        'pdf' => 'bootstrapPdf',
        'translator' => 'bootstrapTranslator',
        'notifications' => 'bootstrapNotifications',
        'providers' => 'bootstrapProviders',
    ];

    /**
     * The service providers for the application.
     *
     * @var array
     */
    protected array $serviceProviders = [];

    public function __construct()
    {
        $this->container = \Plugs\Container\Container::getInstance();

        $this->bootstrap();
        $this->registerDeferredServices();

        $this->dispatcher = new MiddlewareDispatcher();
        $this->dispatcher->add(new \Plugs\Http\Middleware\PreventRequestsDuringMaintenance()); // Global middleware
        $this->dispatcher->add(new \Plugs\Http\Middleware\ShareErrorsFromSession()); // Errors middleware

        $this->fallbackHandler = function (ServerRequestInterface $request) {
            throw new \Plugs\Exceptions\RouteNotFoundException();
        };

        // Register Exception Handler
        $this->container->singleton(\Plugs\Exceptions\Handler::class, function ($container) {
            return new \Plugs\Exceptions\Handler($container);
        });
    }

    /**
     * Bootstrap essential services.
     */
    protected function bootstrap(): void
    {
        foreach ($this->bootstrappers as $bootstrapper) {
            $this->{$bootstrapper}();
        }
    }

    /**
     * Register deferred services as lazy container singletons.
     * They only boot when first resolved from the container.
     */
    protected function registerDeferredServices(): void
    {
        foreach ($this->deferredBootstrappers as $name => $method) {
            // Only register if the method hasn't already been called
            // and doesn't need to run immediately
            $this->{$method}();
        }
    }

    /**
     * Bootstrap the service providers.
     */
    protected function bootstrapProviders(): void
    {
        $this->registerConfiguredProviders();
        $this->bootConfiguredProviders();
    }

    private function registerConfiguredProviders(): void
    {
        $providers = config('app.providers', []);

        foreach ($providers as $provider) {
            $this->resolveProvider($provider)->register();
        }
    }

    private function bootConfiguredProviders(): void
    {
        $providers = config('app.providers', []);

        foreach ($providers as $provider) {
            $this->resolveProvider($provider)->boot();
        }
    }

    private function resolveProvider(string $provider): \Plugs\Support\ServiceProvider
    {
        return new $provider($this->container);
    }

    private function bootstrapLogger(): void
    {
        $container = $this->container;

        $container->singleton('log', function () {
            $config = config('logging');
            $channel = $config['default'];
            $path = $config['channels'][$channel]['path'] ?? storage_path('logs/plugs.log');

            return new \Plugs\Log\Logger($path);
        });
        $container->alias('log', \Psr\Log\LoggerInterface::class);
    }

    private function bootstrapCache(): void
    {
        $container = $this->container;

        $container->singleton('cache', function () {
            return new \Plugs\Cache\CacheManager();
        });
    }

    private function bootstrapAuth(): void
    {
        $container = $this->container;

        $container->singleton('auth', function () {
            return new \Plugs\Security\Auth\AuthManager();
        });
    }

    private function bootstrapQueue(): void
    {
        $this->container->singleton('queue', function () {
            $queue = new \Plugs\Queue\QueueManager();
            $queue->setDefaultDriver(config('queue.default', 'sync'));

            return $queue;
        });
    }

    private function bootstrapStorage(): void
    {
        $this->container->singleton('storage', function () {
            $config = config('filesystems');
            return new \Plugs\Filesystem\StorageManager($config);
        });
    }

    private function bootstrapView(): void
    {
        $container = $this->container;

        $container->singleton(\Plugs\View\ViewEngine::class, function () use ($container) {
            $config = config('app.paths');

            return new \Plugs\View\ViewEngine(
                $config['views'],
                $config['cache'],
                $container,
                self::isProduction()
            );
        });

        $container->alias(\Plugs\View\ViewEngine::class, 'view');
    }

    private function bootstrapDatabase(): void
    {
        $container = $this->container;

        // Bind the connection class to the instance returned by its factory method
        $container->bind(\Plugs\Database\Connection::class, function () {
            return \Plugs\Database\Connection::getInstance();
        }, true); // Shared instance

        // Bind DatabaseManager as 'db' for the DB facade
        $container->singleton('db', function ($container) {
            return new \Plugs\Database\DatabaseManager($container->make(\Plugs\Database\Connection::class));
        });
    }

    private function bootstrapSocialite(): void
    {
        $this->container->singleton('socialite', function ($container) {
            return new \Plugs\Security\OAuth\SocialiteManager($container);
        });
    }

    private function bootstrapPdf(): void
    {
        $this->container->singleton('pdf', function ($container) {
            $pdf = new \Plugs\Pdf\PdfServiceProvider($container);
            $pdf->register();

            return $pdf;
        });
    }

    private function bootstrapEvents(): void
    {
        $this->container->singleton('events', function ($container) {
            return new \Plugs\Event\Dispatcher($container);
        });

        $this->container->alias('events', \Plugs\Event\DispatcherInterface::class);
    }

    private function bootstrapTranslator(): void
    {
        $this->container->singleton('translator', function ($container) {
            $config = config('app');
            $locale = $config['locale'] ?? 'en';
            $fallback = $config['fallback_locale'] ?? 'en';

            $translator = new \Plugs\Support\Translator($locale, $fallback);
            $translator->addPath(base_path('resources/lang'));

            return $translator;
        });

        $this->container->alias('translator', \Plugs\Support\Translator::class);

        // Load translation functions
        require_once __DIR__ . '/functions/translation.php';
    }

    private function bootstrapNotifications(): void
    {
        $this->container->singleton('notifications', function ($container) {
            return new \Plugs\Notification\Manager($container);
        });

        $this->container->alias('notifications', \Plugs\Notification\Manager::class);
    }

    public function pipe(MiddlewareInterface $middleware): self
    {
        $this->dispatcher->add($middleware);

        return $this;
    }

    public function setFallback(callable $handler): self
    {
        $this->fallbackHandler = $handler;

        return $this;
    }

    public function run(?ServerRequestInterface $request = null): void
    {
        // Reuse the request from the container if available (Fix 3: avoid duplicate fromGlobals)
        if ($request === null) {
            $request = $this->container->bound(ServerRequestInterface::class)
                ? $this->container->make(ServerRequestInterface::class)
                : $this->createServerRequest();
        }

        // Set global current request early for helpers and diagnostics
        $GLOBALS['__current_request'] = $request;

        try {
            // Set the fallback handler before handling the request
            $this->dispatcher->setFallbackHandler($this->fallbackHandler);

            $response = $this->dispatcher->handle($request);
            $this->emitResponse($response);
        } catch (\Throwable $e) {
            $handler = $this->container->make(\Plugs\Exceptions\Handler::class);
            $response = $handler->handle($e, $request);
            $this->emitResponse($response);
        }
    }

    private function createServerRequest(): ServerRequestInterface
    {
        $method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
        $uri = $_SERVER['REQUEST_URI'] ?? '/';

        // Parse query string
        $queryParams = [];
        if (isset($_SERVER['QUERY_STRING'])) {
            parse_str($_SERVER['QUERY_STRING'], $queryParams);
        }

        $headers = [];
        foreach ($_SERVER as $key => $value) {
            if (strpos($key, 'HTTP_') === 0) {
                $header = str_replace('_', '-', strtolower(substr($key, 5)));
                $headers[$header] = $value;
            }
        }

        $body = new Stream(fopen('php://input', 'r'));

        $request = new ServerRequest(
            $method,
            $uri,
            $headers,
            $body,
            $_SERVER['SERVER_PROTOCOL'] ?? '1.1',
            $_SERVER
        );

        return $request
            ->withQueryParams($queryParams)
            ->withParsedBody($_POST)
            ->withCookieParams($_COOKIE)
            ->withUploadedFiles($this->normalizeFiles($_FILES));
    }

    private function normalizeFiles(array $files): array
    {
        $normalized = [];

        foreach ($files as $key => $value) {
            if (is_array($value) && isset($value['tmp_name'])) {
                // Single file or multiple files with same name
                if (is_array($value['tmp_name'])) {
                    // Multiple files
                    $normalized[$key] = [];
                    foreach (array_keys($value['tmp_name']) as $index) {
                        $normalized[$key][$index] = [
                            'name' => $value['name'][$index],
                            'type' => $value['type'][$index],
                            'tmp_name' => $value['tmp_name'][$index],
                            'error' => $value['error'][$index],
                            'size' => $value['size'][$index],
                        ];
                    }
                } else {
                    // Single file
                    $normalized[$key] = $value;
                }
            } elseif (is_array($value)) {
                $normalized[$key] = $this->normalizeFiles($value);
            }
        }

        return $normalized;
    }

    private function emitResponse(ResponseInterface $response): void
    {
        if (headers_sent()) {
            echo $response->getBody();

            return;
        }

        // Send status line
        $statusCode = $response->getStatusCode();
        $reasonPhrase = $response->getReasonPhrase();
        header(sprintf(
            'HTTP/%s %d%s',
            $response->getProtocolVersion(),
            $statusCode,
            ($reasonPhrase ? ' ' . $reasonPhrase : '')
        ), true, $statusCode);

        // Send headers (replace any existing headers with same name)
        foreach ($response->getHeaders() as $name => $values) {
            $first = true;
            foreach ($values as $value) {
                header(sprintf('%s: %s', $name, $value), $first);
                $first = false; // Subsequent values should be additive
            }
        }

        // Send body
        echo $response->getBody();
    }

    /**
     * Load helper functions.
     */
    private function loadFunctions(): void
    {
        $storagePath = defined('STORAGE_PATH') ? STORAGE_PATH : (function_exists('storage_path') ? storage_path() : dirname(__DIR__, 2) . '/storage/');
        $cacheFile = rtrim($storagePath, '/\\') . '/framework/functions.php';

        // Try to load from cache in production
        if (self::isProduction() && file_exists($cacheFile)) {
            $files = require $cacheFile;
            foreach ($files as $file) {
                require_once $file;
            }

            return;
        }

        $functionsDir = __DIR__ . '/functions/';

        if (!is_dir($functionsDir)) {
            return;
        }

        // Files to defer in production (large debug-only files)
        $deferredFiles = self::isProduction() ? ['dump.php', 'error.php'] : [];

        $files = scandir($functionsDir);
        $loadList = [];

        foreach ($files as $file) {
            if ($file[0] !== '.' && pathinfo($file, PATHINFO_EXTENSION) === 'php') {
                $filePath = $functionsDir . $file;

                if (in_array($file, $deferredFiles, true)) {
                    // Skip — will be loaded on demand via stubs
                    continue;
                }

                require_once $filePath;
                $loadList[] = $filePath;
            }
        }

        // Define lightweight stubs for deferred files
        if (!empty($deferredFiles)) {
            $this->registerDeferredDebugStubs($functionsDir);
        }

        // Save cache in production
        if (self::isProduction() && !empty($loadList)) {
            $cacheDir = dirname($cacheFile);
            if (!is_dir($cacheDir)) {
                mkdir($cacheDir, 0755, true);
            }
            file_put_contents($cacheFile, '<?php return ' . var_export($loadList, true) . ';');
        }
    }

    /**
     * Register lightweight stubs for debugging functions.
     * The full file is loaded on first call.
     */
    private function registerDeferredDebugStubs(string $functionsDir): void
    {
        $dumpFile = $functionsDir . 'dump.php';
        $errorFile = $functionsDir . 'error.php';

        // Define stub functions inline that load the real file on demand
        if (!function_exists('dd')) {
            function dd(mixed ...$vars): void
            {
                require_once __DIR__ . '/functions/dump.php';
                plugs_dump($vars, true);
            }
        }

        if (!function_exists('d')) {
            function d(mixed ...$vars): void
            {
                require_once __DIR__ . '/functions/dump.php';
                plugs_dump($vars, false);
            }
        }

        // error.php defines renderDebugErrorPage — load it lazily from the error handler
        // No stub needed as it's only called from the exception handler which can load it directly
    }

    /**
     * Check if the application is in production mode.
     */
    public static function isProduction(): bool
    {
        $env = $_ENV['APP_ENV'] ?? $_SERVER['APP_ENV'] ?? getenv('APP_ENV') ?: 'production';

        return strtolower($env) === 'production';
    }
}
