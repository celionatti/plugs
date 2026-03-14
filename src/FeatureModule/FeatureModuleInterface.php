<?php

declare(strict_types=1);

namespace Plugs\FeatureModule;

use Plugs\Container\Container;
use Plugs\Plugs;

/**
 * Contract for application Feature Modules.
 *
 * Feature modules are self-contained mini-apps (Auth, Store, Blog, etc.)
 * that bundle their own Controllers, Models, Routes, and Migrations.
 *
 * This is separate from the framework-level ModuleInterface which handles
 * core services like Database, Session, and Cache.
 */
interface FeatureModuleInterface
{
    /**
     * Get the unique name of the module (e.g., 'Auth', 'Store').
     */
    public function getName(): string;

    /**
     * Get the absolute path to the module's root directory.
     */
    public function getPath(): string;

    /**
     * Get the list of web route files to load.
     *
     * @return string[] Absolute paths to route files
     */
    public function getWebRouteFiles(): array;

    /**
     * Get the list of API route files to load.
     *
     * @return string[] Absolute paths to route files
     */
    public function getApiRouteFiles(): array;

    /**
     * Get the migration directory path, or null if no migrations.
     */
    public function getMigrationPath(): ?string;

    /**
     * Get the namespace prefix for controllers in this module.
     */
    public function getControllerNamespace(): string;

    /**
     * Get the route URL prefix for this module.
     * Return empty string for no prefix.
     */
    public function getRoutePrefix(): string;

    /**
     * Get the route name prefix for this module (e.g., 'auth.').
     */
    public function getRouteNamePrefix(): string;

    /**
     * Get middleware to apply to all routes in this module.
     *
     * @return string[]
     */
    public function getMiddleware(): array;

    /**
     * Register any bindings in the container.
     */
    public function register(Container $container): void;

    /**
     * Boot the module after all modules have been registered.
     */
    public function boot(Plugs $app): void;
}
