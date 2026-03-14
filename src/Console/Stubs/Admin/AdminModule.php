<?php

declare(strict_types=1);

namespace Modules\Admin;

use Plugs\FeatureModule\AbstractFeatureModule;
use Plugs\Container\Container;
use Plugs\Plugs;

class AdminModule extends AbstractFeatureModule
{
    /**
     * Get the unique name of the module.
     */
    public function getName(): string
    {
        return 'Admin';
    }

    /**
     * Get the route URL prefix for this module.
     */
    public function getRoutePrefix(): string
    {
        return 'admin';
    }

    /**
     * Get middleware to apply to all routes in this module.
     */
    public function getMiddleware(): array
    {
        return ['web', 'auth', 'admin'];
    }

    /**
     * Register any bindings in the container.
     */
    public function register(Container $container): void
    {
        // Add any admin-specific service registrations here
    }

    /**
     * Boot the module.
     */
    public function boot(Plugs $app): void
    {
        // Custom boot logic for Admin module
    }
}
