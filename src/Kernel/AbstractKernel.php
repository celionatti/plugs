<?php

declare(strict_types=1);

namespace Plugs\Kernel;

use Plugs\Bootstrap\ContextType;
use Plugs\Container\Container;

/**
 * Base kernel with shared bootstrap logic.
 *
 * All concrete kernels extend this class and override the middleware
 * layers and boot methods to provide context-specific behavior.
 */
abstract class AbstractKernel implements KernelInterface
{
    protected Container $container;
    protected ContextType $context;
    protected bool $booted = false;

    /**
     * Middleware layers organized by category.
     * Subclasses override this to define their pipeline.
     *
     * @var array<string, array<string>>
     */
    protected array $middlewareLayers = [
        'security' => [],
        'performance' => [],
        'business' => [],
    ];

    public function __construct(ContextType $context)
    {
        $this->context = $context;
        $this->container = Container::getInstance();
    }

    public function getContext(): ContextType
    {
        return $this->context;
    }

    public function isBooted(): bool
    {
        return $this->booted;
    }

    /**
     * Boot the kernel. Calls context-specific bootServices().
     */
    public function boot(): void
    {
        if ($this->booted) {
            return;
        }

        $this->bootServices();
        $this->booted = true;
    }

    /**
     * Context-specific service bootstrapping.
     * Override in concrete kernels.
     */
    abstract protected function bootServices(): void;

    /**
     * Get the middleware layers for this kernel.
     *
     * @return array<string, array<string>>
     */
    public function getMiddlewareLayers(): array
    {
        return $this->middlewareLayers;
    }

    /**
     * Get the flattened middleware stack in execution order.
     * Security → Performance → Business.
     *
     * @return array<string>
     */
    public function getMiddleware(): array
    {
        return array_merge(
            $this->middlewareLayers['security'] ?? [],
            $this->middlewareLayers['performance'] ?? [],
            $this->middlewareLayers['business'] ?? [],
        );
    }

    /**
     * Cleanup after the request lifecycle.
     */
    public function terminate(): void
    {
        // Base implementation — subclasses can override
    }

    /**
     * Setup the router and register it in the container.
     */
    protected function setupRouter(): void
    {
        $router = new \Plugs\Router\Router();

        $this->container->singleton('router', fn() => $router);
        $this->container->singleton(\Plugs\Router\Router::class, fn() => $router);
        \Plugs\Facades\Route::setFacadeInstance('router', $router);

        // Load groups from config if available
        $middlewareConfig = config('middleware');
        if (isset($middlewareConfig['groups'])) {
            foreach ($middlewareConfig['groups'] as $group => $middlewares) {
                $router->middlewareGroup($group, $middlewares);
            }
        }
    }

    /**
     * Setup the server request and bind it to the container.
     */
    protected function setupRequest(): void
    {
        $request = \Plugs\Http\Message\ServerRequest::fromGlobals();

        $this->container->singleton(
            \Psr\Http\Message\ServerRequestInterface::class,
            fn() => $request
        );
    }
}
