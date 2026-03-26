<?php

declare(strict_types=1);

namespace Modules\Payment;

use Plugs\FeatureModule\AbstractFeatureModule;
use Plugs\Container\Container;
use Plugs\Plugs;

class PaymentModule extends AbstractFeatureModule
{
    /**
     * Get the unique name of the module.
     */
    public function getName(): string
    {
        return 'Payment';
    }

    /**
     * Get the route URL prefix for this module.
     */
    public function getRoutePrefix(): string
    {
        return 'admin/payment';
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
        $container->singleton(Services\PaymentSettingsService::class, fn() => new Services\PaymentSettingsService());
    }

    /**
     * Boot the module.
     */
    public function boot(Plugs $app): void
    {
        // Custom boot logic for Payment module
    }
}
