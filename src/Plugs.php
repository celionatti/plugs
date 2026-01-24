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

use Plugs\Http\Message\Response;
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

    public function __construct()
    {
        $this->loadFunctions();
        $this->bootstrapLogger();
        $this->bootstrapCache();
        $this->bootstrapAuth();
        $this->bootstrapQueue();
        $this->bootstrapStorage();
        $this->bootstrapView();
        $this->bootstrapDatabase();
        $this->bootstrapSocialite();

        $this->registerConfiguredProviders();
        $this->bootConfiguredProviders();

        $this->dispatcher = new MiddlewareDispatcher();
        $this->dispatcher->add(new \Plugs\Http\Middleware\PreventRequestsDuringMaintenance()); // Global middleware

        $this->fallbackHandler = function (ServerRequestInterface $request) {
            $body = new Stream(fopen('php://temp', 'w+'));
            $body->write('Not Found');
            $body->rewind();

            return new Response(404, $body, ['Content-Type' => 'text/plain']);
        };
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
        return new $provider(\Plugs\Container\Container::getInstance());
    }

    private function bootstrapLogger(): void
    {
        $container = \Plugs\Container\Container::getInstance();

        $config = (include base_path('config/logging.php'));
        $channel = $config['default'];
        $path = $config['channels'][$channel]['path'] ?? storage_path('logs/plugs.log');

        $logger = new \Plugs\Log\Logger($path);
        $container->instance('log', $logger);
        $container->alias('log', \Psr\Log\LoggerInterface::class);
    }

    private function bootstrapCache(): void
    {
        $container = \Plugs\Container\Container::getInstance();

        $cache = new \Plugs\Cache\CacheManager();
        $container->instance('cache', $cache);
    }

    private function bootstrapAuth(): void
    {
        $container = \Plugs\Container\Container::getInstance();

        $auth = new \Plugs\Security\Auth\AuthManager();
        $container->instance('auth', $auth);
    }

    private function bootstrapQueue(): void
    {
        $container = \Plugs\Container\Container::getInstance();

        $queue = new \Plugs\Queue\QueueManager();
        $queue->setDefaultDriver(config('queue.default', 'sync'));
        $container->instance('queue', $queue);
    }

    private function bootstrapStorage(): void
    {
        $container = \Plugs\Container\Container::getInstance();

        $config = (include base_path('config/filesystems.php'));
        $storage = new \Plugs\Filesystem\StorageManager($config);
        $container->instance('storage', $storage);
    }

    private function bootstrapView(): void
    {
        $container = \Plugs\Container\Container::getInstance();

        $config = config('app.paths');

        $engine = new \Plugs\View\ViewEngine(
            $config['views'],
            $config['cache'],
            !self::isProduction()
        );

        $container->instance(\Plugs\View\ViewEngine::class, $engine);

        // Also bind the View class alias if it exists or simply the engine as 'view'
        // Assuming View class uses the engine or is just an alias?
        // Let's bind 'view' to the engine for now as typical in this framework structure
        $container->instance('view', $engine);
    }

    private function bootstrapDatabase(): void
    {
        $container = \Plugs\Container\Container::getInstance();

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
        $container = \Plugs\Container\Container::getInstance();

        $socialite = new \Plugs\Security\OAuth\SocialiteManager($container);
        $container->instance('socialite', $socialite);
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
        $request = $request ?? $this->createServerRequest();

        // Set the fallback handler before handling the request
        $this->dispatcher->setFallbackHandler($this->fallbackHandler);

        $response = $this->dispatcher->handle($request);
        $this->emitResponse($response);
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
        $functionsDir = __DIR__ . '/functions/';

        if (!is_dir($functionsDir)) {
            return;
        }

        // Use a more efficient way to load functions
        $files = glob($functionsDir . '*.php', GLOB_NOSORT);

        foreach ($files as $file) {
            // Check if it's a file and not an index or something else
            if (is_file($file)) {
                require_once $file;
            }
        }
    }

    /**
     * Check if the application is in production mode.
     */
    public static function isProduction(): bool
    {
        return strtolower(getenv('APP_ENV') ?: 'production') === 'production';
    }
}
