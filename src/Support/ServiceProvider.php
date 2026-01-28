<?php

declare(strict_types=1);

namespace Plugs\Support;

use Plugs\Container\Container;
abstract class ServiceProvider
{
    /**
     * The application instance.
     *
     * @var \Plugs\Container\Container
     */
    protected Container $app;

    /**
     * Indicates if loading of the provider is deferred.
     *
     * @var bool
     */
    protected bool $defer = false;

    /**
     * Create a new service provider instance.
     *
     * @param  \Plugs\Container\Container  $app
     */
    public function __construct(Container $app)
    {
        $this->app = $app;
    }

    /**
     * Register any application services.
     *
     * @return void
     */
    public function register()
    {
        //
    }

    /**
     * Boot any application services.
     *
     * @return void
     */
    public function boot()
    {
        //
    }

    /**
     * Get the services provided by the provider.
     *
     * @return array
     */
    public function provides(): array
    {
        return [];
    }

    /**
     * Get the application instance.
     *
     * @return \Plugs\Container\Container
     */
    public function app(): Container
    {
        return $this->app;
    }

    /**
     * Determine if the provider is deferred.
     *
     * @return bool
     */
    public function isDeferred(): bool
    {
        return $this->defer;
    }
}
