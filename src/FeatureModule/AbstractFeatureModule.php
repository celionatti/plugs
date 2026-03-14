<?php

declare(strict_types=1);

namespace Plugs\FeatureModule;

use Plugs\Container\Container;
use Plugs\Plugs;

/**
 * Base class for Feature Modules with convention-based defaults.
 *
 * Extend this class and override methods to customize behavior.
 * By default, it auto-discovers routes and migrations based on
 * the module directory structure:
 *
 *   modules/Auth/
 *     AuthModule.php         ← extends AbstractFeatureModule
 *     Controllers/
 *     Models/
 *     Routes/
 *       web.php
 *       api.php
 *     Migrations/
 */
abstract class AbstractFeatureModule implements FeatureModuleInterface
{
    /**
     * The absolute path to the module root directory.
     * Set automatically by the FeatureModuleManager.
     */
    protected string $path = '';

    /**
     * Optional route prefix override.
     * If null, defaults to lowercase module name.
     */
    protected ?string $routePrefix = null;

    /**
     * Middleware to apply to all routes in this module.
     *
     * @var string[]
     */
    protected array $middleware = [];

    /**
     * {@inheritDoc}
     */
    abstract public function getName(): string;

    /**
     * {@inheritDoc}
     */
    public function getPath(): string
    {
        if ($this->path === '') {
            // Derive from the class file location
            $reflector = new \ReflectionClass($this);
            $this->path = dirname($reflector->getFileName());
        }

        return $this->path;
    }

    /**
     * Set the module root path.
     */
    public function setPath(string $path): void
    {
        $this->path = rtrim($path, '/\\');
    }

    /**
     * {@inheritDoc}
     */
    public function getWebRouteFiles(): array
    {
        $file = $this->getPath() . DIRECTORY_SEPARATOR . 'Routes' . DIRECTORY_SEPARATOR . 'web.php';

        return file_exists($file) ? [$file] : [];
    }

    /**
     * {@inheritDoc}
     */
    public function getApiRouteFiles(): array
    {
        $file = $this->getPath() . DIRECTORY_SEPARATOR . 'Routes' . DIRECTORY_SEPARATOR . 'api.php';

        return file_exists($file) ? [$file] : [];
    }

    /**
     * {@inheritDoc}
     */
    public function getMigrationPath(): ?string
    {
        $path = $this->getPath() . DIRECTORY_SEPARATOR . 'Migrations';

        return is_dir($path) ? $path : null;
    }

    /**
     * {@inheritDoc}
     */
    public function getControllerNamespace(): string
    {
        return 'Modules\\' . $this->getName() . '\\Controllers';
    }

    /**
     * {@inheritDoc}
     */
    public function getRoutePrefix(): string
    {
        if ($this->routePrefix !== null) {
            return $this->routePrefix;
        }

        return strtolower($this->getName());
    }

    /**
     * {@inheritDoc}
     */
    public function getRouteNamePrefix(): string
    {
        return strtolower($this->getName()) . '.';
    }

    /**
     * {@inheritDoc}
     */
    public function getMiddleware(): array
    {
        return $this->middleware;
    }

    /**
     * {@inheritDoc}
     */
    public function register(Container $container): void
    {
        // Override in subclass to register services
    }

    /**
     * {@inheritDoc}
     */
    public function boot(Plugs $app): void
    {
        // Override in subclass for boot logic
    }
}
