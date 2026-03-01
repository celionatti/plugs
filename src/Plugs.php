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

use Plugs\Bootstrap\ContextType;
use Plugs\Http\Message\ServerRequest;
use Plugs\Http\MiddlewareDispatcher;
use Plugs\Kernel\KernelInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;

class Plugs
{
    /**
     * The framework version.
     *
     * @var string
     */
    public const VERSION = '1.0.0-dev';

    /**
     * Get the version number of the application.
     */
    public static function version(): string
    {
        // 1. Check for VERSION file (CI/CD override)
        if (defined('BASE_PATH') && file_exists(BASE_PATH . '/VERSION')) {
            return trim(file_get_contents(BASE_PATH . '/VERSION'));
        }

        // 2. Check Composer InstalledVersions
        if (class_exists(\Composer\InstalledVersions::class)) {
            try {
                return \Composer\InstalledVersions::getVersion('plugs/plugs') ?? self::VERSION;
            } catch (\OutOfBoundsException $e) {
                // Package not found (e.g. running from source)
            }
        }

        // 3. Fallback to constant
        return self::VERSION;
    }

    private $dispatcher;
    private $fallbackHandler;
    private $container;
    private array $terminatingCallbacks = [];

    /**
     * The service providers for the application.
     *
     * @var array
     */
    protected array $serviceProviders = [];

    public function __construct()
    {
        $this->container = \Plugs\Container\Container::getInstance();
        $this->container->instance('app', $this->container);
        $this->container->instance(self::class, $this);

        $this->dispatcher = new MiddlewareDispatcher();

        $this->fallbackHandler = function (ServerRequestInterface $request) {
            throw new \Plugs\Exceptions\RouteNotFoundException();
        };

        // Register Exception Handler
        $this->container->singleton(\Plugs\Exceptions\Handler::class, function ($container) {
            return new \Plugs\Exceptions\Handler($container);
        });
    }

    /**
     * Get the service container.
     */
    public function getContainer(): \Plugs\Container\Container
    {
        return $this->container;
    }

    /**
     * Bootstrap essential services using the ModuleManager.
     */
    public function bootstrap(): void
    {
        $context = $this->getContext();
        if (!$context) {
            throw new \RuntimeException('Application context must be resolved before bootstrapping.');
        }

        \Plugs\Module\ModuleManager::getInstance()->bootModules($this, $context);
    }

    public function pipe(string|MiddlewareInterface $middleware): self
    {
        $this->dispatcher->add($middleware);

        return $this;
    }

    public function setFallback(callable $handler): self
    {
        $this->fallbackHandler = $handler;

        return $this;
    }

    /**
     * Register a callback to be executed after the response has been sent.
     */
    public function terminating(callable $callback): self
    {
        $this->terminatingCallbacks[] = $callback;

        return $this;
    }

    public function run(?ServerRequestInterface $request = null): void
    {
        // Reuse the request from the container if available
        if ($request === null) {
            $request = $this->container->bound(ServerRequestInterface::class)
                ? $this->container->make(ServerRequestInterface::class)
                : $this->createServerRequest();
        }

        // Set global current request early for helpers and diagnostics
        $GLOBALS['__current_request'] = $request;

        // Emit RequestReceived event
        if ($this->container->has('events')) {
            $this->container->make('events')->dispatch(
                new \Plugs\Event\Core\RequestReceived($request)
            );
        }

        try {
            $this->dispatcher->setFallbackHandler($this->fallbackHandler);

            $response = $this->dispatcher->handle($request);

            // Force session close before response is emitted to ensure cookies (like CSRF guest tokens) are sent
            if (session_status() === PHP_SESSION_ACTIVE) {
                session_write_close();
            }

            $this->emitResponse($response);

            // Execute post-response logic
            $this->terminate($request, $response);
        } catch (\Throwable $e) {
            // Always route through the exception handler
            try {
                $handler = $this->container->make(\Plugs\Exceptions\Handler::class);
                $response = $handler->handle($e, $request);

                if (session_status() === PHP_SESSION_ACTIVE) {
                    session_write_close();
                }

                $this->emitResponse($response);

                // Still terminate even on error
                $this->terminate($request, $response);
            } catch (\Throwable $fallback) {
                // Last resort: if the handler itself fails, render minimal safe output
                http_response_code(500);
                if (config('app.debug', false)) {
                    echo '<h1>Fatal Error</h1>';
                    echo '<pre>' . htmlspecialchars($e->getMessage() . "\n" . $e->getTraceAsString()) . '</pre>';
                    echo '<h2>Handler Error</h2>';
                    echo '<pre>' . htmlspecialchars($fallback->getMessage() . "\n" . $fallback->getTraceAsString()) . '</pre>';
                } else {
                    echo '<h1>500 — Internal Server Error</h1>';
                }
            }
        }
    }

    /**
     * Terminate the request lifecycle.
     */
    public function terminate(ServerRequestInterface $request, ResponseInterface $response): void
    {
        // Close the connection if possible so the user doesn't wait for background tasks
        if (function_exists('fastcgi_finish_request')) {
            fastcgi_finish_request();
        }

        // Stop the profiler BEFORE running background tasks 
        if (class_exists(\Plugs\Debug\Profiler::class)) {
            \Plugs\Debug\Profiler::getInstance()->stop();
        }

        // Terminate the active kernel (context-specific cleanup)
        if ($this->container->has(KernelInterface::class)) {
            $this->container->make(KernelInterface::class)->terminate();
        }

        foreach ($this->terminatingCallbacks as $callback) {
            $callback($request, $response);
        }
    }

    /**
     * Get the active kernel instance, if booted.
     */
    public function getKernel(): ?KernelInterface
    {
        if ($this->container->has(KernelInterface::class)) {
            return $this->container->make(KernelInterface::class);
        }

        return null;
    }

    /**
     * Get the current execution context.
     */
    public function getContext(): ?ContextType
    {
        if ($this->container->has(ContextType::class)) {
            return $this->container->make(ContextType::class);
        }

        return null;
    }

    private function createServerRequest(): ServerRequestInterface
    {
        return ServerRequest::fromGlobals();
    }

    private function emitResponse(ResponseInterface $response): void
    {
        // If we are in async mode, we might not want to use PHP's native header/echo
        if (\Plugs\Bootstrap\DetectMode::isAsync()) {
            return;
        }

        if (headers_sent() && PHP_SAPI !== 'cli') {
            echo $response->getBody();

            return;
        }

        // Emit ResponseSending event
        if ($this->container->has('events')) {
            $this->container->make('events')->dispatch(
                new \Plugs\Event\Core\ResponseSending($response)
            );
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

        // Send headers
        foreach ($response->getHeaders() as $name => $values) {
            $first = true;
            foreach ($values as $value) {
                header(sprintf('%s: %s', $name, $value), $first);
                $first = false;
            }
        }

        // Send body
        if ($this->shouldCompress($response)) {
            $content = (string) $response->getBody();
            header('Content-Encoding: gzip');
            header('Vary: Accept-Encoding');
            echo gzencode($content, 6);
        } else {
            echo $response->getBody();
        }

        // Emit ResponseSent event
        if ($this->container->has('events')) {
            $this->container->make('events')->dispatch(
                new \Plugs\Event\Core\ResponseSent($response)
            );
        }
    }

    /**
     * Check if the response should be compressed.
     */
    private function shouldCompress(ResponseInterface $response): bool
    {
        if (connection_aborted()) {
            return false;
        }

        if (headers_sent() && PHP_SAPI !== 'cli') {
            return false;
        }

        if ($response->hasHeader('Content-Encoding')) {
            return false;
        }

        $contentType = $response->getHeaderLine('Content-Type') ?: 'text/html';

        $compressibleTypes = [
            'text/html',
            'text/css',
            'application/javascript',
            'application/json',
            'text/xml',
            'application/xml',
            'text/plain'
        ];

        $isCompressible = false;
        foreach ($compressibleTypes as $type) {
            if (stripos($contentType, $type) !== false) {
                $isCompressible = true;
                break;
            }
        }

        if (!$isCompressible) {
            return false;
        }

        $encoding = $_SERVER['HTTP_ACCEPT_ENCODING'] ?? '';

        return stripos($encoding, 'gzip') !== false;
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
