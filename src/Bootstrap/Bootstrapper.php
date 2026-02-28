<?php

declare(strict_types=1);

namespace Plugs\Bootstrap;

use Plugs\Container\Container;
use Plugs\Http\Middleware\MiddlewareRegistry;
use Plugs\Http\Middleware\RoutingMiddleware;
use Plugs\Kernel\ApiKernel;
use Plugs\Kernel\CliKernel;
use Plugs\Kernel\KernelInterface;
use Plugs\Kernel\QueueKernel;
use Plugs\Kernel\RealtimeKernel;
use Plugs\Kernel\WebKernel;
use Plugs\Plugs;
use Plugs\Router\Router;
use Plugs\Bootstrap\ContextType;

/**
 * Application Bootstrapper — Context-Aware Bootstrap System.
 *
 * Instead of a flat pipeline that loads everything for every request,
 * this bootstrapper detects the execution context first and then boots
 * only the appropriate kernel with its optimized middleware and services.
 *
 * Flow: entry point → ContextResolver → specific Kernel → optimized pipeline
 */
class Bootstrapper
{
    protected string $basePath;
    protected Plugs $app;
    protected Container $container;
    protected KernelInterface $kernel;

    public function __construct(string $basePath)
    {
        $this->basePath = rtrim($basePath, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR;
        $this->container = Container::getInstance();
    }

    /**
     * Boot the application with context-aware kernel selection.
     *
     * @param ContextType|null $forceContext Force a specific context (useful for testing)
     * @return Plugs
     */
    public function boot(?ContextType $forceContext = null): Plugs
    {
        // Phase 1: Environment setup (shared by all contexts)
        $this->defineConstants();
        $this->loadEnvironment();
        $this->registerRegistry();

        // Phase 2: Context detection
        $context = $forceContext ?? ContextResolver::resolve();

        // Store context in container for global access
        $this->container->instance(ContextType::class, $context);

        // Phase 3: Initialize the application and Register Modules
        $this->initializeApplication();
        $this->registerCoreModules();

        // Phase 4: Bootstrap the application (Runs Module Manager)
        $this->app->bootstrap();

        // Phase 5: Create and boot the appropriate kernel
        $this->kernel = $this->createKernel($context);

        // Register kernel in container
        $this->container->instance(KernelInterface::class, $this->kernel);

        // Phase 6: Boot the kernel (context-specific services + middleware)
        $this->kernel->boot();

        // Phase 7: Wire kernel middleware into the app pipeline (HTTP kernels only)
        if ($context->isHttp()) {
            $this->wireMiddleware();
            $this->wireRouting();
            $this->wireFallback();
        }

        return $this->app;
    }

    /**
     * Register core framework modules.
     */
    protected function registerCoreModules(): void
    {
        $manager = \Plugs\Module\ModuleManager::getInstance();

        $manager->addModule([
            \Plugs\Module\Core\FunctionsModule::class,
            \Plugs\Module\Core\LogModule::class,
            \Plugs\Module\Core\CacheModule::class,
            \Plugs\Module\Core\EncryptionModule::class,
            \Plugs\Module\Core\EventsModule::class,
            \Plugs\Module\Core\DatabaseModule::class,
            \Plugs\Module\Core\ViewModule::class,
            \Plugs\Module\Core\AuthModule::class,
            \Plugs\Module\Core\SessionModule::class,
            \Plugs\Module\Core\QueueModule::class,
            \Plugs\Module\Core\StorageModule::class,
            \Plugs\Module\Core\MailModule::class,
            \Plugs\Module\Core\NotificationsModule::class,
            \Plugs\Module\Core\AiModule::class,
            \Plugs\Module\Core\SeoModule::class,
            \Plugs\Module\Core\TranslatorModule::class,
            \Plugs\Module\Core\ConcurrencyModule::class,
            \Plugs\Module\Core\ProvidersModule::class,
            \Plugs\Module\Core\OpCacheModule::class,
            \Plugs\Module\Core\TenancyModule::class,
            \Plugs\Module\Core\SocialiteModule::class,
            \Plugs\Module\Core\PdfModule::class,
        ]);
    }

    /**
     * Create the appropriate kernel for the detected context.
     */
    protected function createKernel(ContextType $context): KernelInterface
    {
        return match ($context) {
            ContextType::Web => new WebKernel($context),
            ContextType::Api => new ApiKernel($context),
            ContextType::Cli => new CliKernel($context),
            ContextType::Queue => new QueueKernel($context),
            ContextType::Cron => new CliKernel($context), // Cron uses CLI kernel
            ContextType::Realtime => new RealtimeKernel($context),
        };
    }

    /**
     * Wire the kernel's middleware into the Plugs app pipeline.
     */
    protected function wireMiddleware(): void
    {
        $middleware = $this->kernel->getMiddleware();

        // Pipe each middleware through the app's dispatcher
        foreach ($middleware as $mw) {
            // Skip RoutingMiddleware — it's handled separately in wireRouting()
            if ($mw === RoutingMiddleware::class) {
                continue;
            }
            $this->app->pipe($mw);
        }
    }

    /**
     * Wire routing middleware with the router instance.
     */
    protected function wireRouting(): void
    {
        if ($this->container->has('router')) {
            /** @var Router $router */
            $router = $this->container->make('router');
            $this->app->pipe(new RoutingMiddleware($router));
        }
    }

    /**
     * Wire the fallback handler from the kernel.
     */
    protected function wireFallback(): void
    {
        if ($this->kernel instanceof WebKernel) {
            $this->kernel->setupFallback($this->app);
        } elseif ($this->kernel instanceof ApiKernel) {
            $this->kernel->setupFallback($this->app);
        } else {
            // Default fallback for other HTTP kernels
            $this->app->setFallback(function ($request) {
                return \Plugs\Http\ResponseFactory::json([
                    'error' => 'Not Found',
                    'message' => 'The requested resource was not found',
                ], 404);
            });
        }
    }

    /**
     * Get the active kernel.
     */
    public function getKernel(): KernelInterface
    {
        return $this->kernel;
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
        $registry = new MiddlewareRegistry(config('middleware'));
        $this->container->singleton(MiddlewareRegistry::class, fn() => $registry);
    }
}
