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
    protected $app;

    /**
     * Create a new service provider instance.
     *
     * @param  \Plugs\Container\Container  $app
     * @return void
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
}
