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
        $container->singleton(Services\AdminArticleService::class, fn() => new Services\AdminArticleService());
        $container->singleton(Services\AdminUserService::class, fn() => new Services\AdminUserService());
        $container->singleton(Services\AdminModuleService::class, fn() => new Services\AdminModuleService($container->make(\App\Services\ModuleService::class)));
        $container->singleton(Services\AdminSettingsService::class, fn() => new Services\AdminSettingsService());
        $container->singleton(Services\AdminLogService::class, fn() => new Services\AdminLogService($container->make('log')));
        $container->singleton(Services\AdminMigrationService::class, fn() => new Services\AdminMigrationService());
    }

    /**
     * Boot the module.
     */
    public function boot(Plugs $app): void
    {
        // Custom boot logic for Admin module
        $app->getContainer()
            ->make(\Plugs\Http\Middleware\MiddlewareRegistry::class)
            ->addAlias('admin', \Modules\Admin\Middleware\AdminMiddleware::class);
    }
}
