<?php

declare(strict_types=1);

namespace Plugs\Security\Authorization;

use Plugs\Support\ServiceProvider;

class AuthorizationServiceProvider extends ServiceProvider
{
    /**
     * Register the service provider.
     *
     * @return void
     */
    public function register(): void
    {
        $this->app->singleton(Gate::class, function () {
            return new Gate();
        });

        $this->app->alias(Gate::class, 'gate');
    }

    /**
     * Boot the service provider.
     *
     * @return void
     */
    public function boot(): void
    {
        // Integration with views could be handled here or in ViewServiceProvider
    }
}
