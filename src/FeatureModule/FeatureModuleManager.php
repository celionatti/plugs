<?php

declare(strict_types=1);

namespace Plugs\FeatureModule;

use Plugs\Container\Container;
use Plugs\Plugs;

/**
 * Feature Module Manager — discovers, registers, and boots feature modules.
 *
 * Feature modules are self-contained mini-apps living in the `modules/`
 * directory. Each module bundles its own Controllers, Models, Routes,
 * and Migrations.
 *
 * The manager supports two discovery modes:
 * 1. Auto-discovery: scans `modules/` for directories with a `{Name}Module.php`
 * 2. Explicit: reads `config/modules.php` for a list of enabled modules
 */
class FeatureModuleManager
{
    /**
     * The singleton instance.
     */
    private static ?self $instance = null;

    /**
     * Booted feature module instances.
     *
     * @var FeatureModuleInterface[]
     */
    protected array $modules = [];

    /**
     * Whether modules have been booted.
     */
    protected bool $booted = false;

    /**
     * The base path for modules directory.
     */
    protected string $modulesPath = '';

    private function __construct()
    {
        // Singleton
    }

    /**
     * Get the singleton instance.
     */
    public static function getInstance(): self
    {
        if (self::$instance === null) {
            self::$instance = new self();
        }

        return self::$instance;
    }

    /**
     * Reset the manager (for testing).
     */
    public static function reset(): void
    {
        self::$instance = null;
    }

    /**
     * Set the base modules directory path.
     */
    public function setModulesPath(string $path): void
    {
        $this->modulesPath = rtrim($path, '/\\');
    }

    /**
     * Get the base modules directory path.
     */
    public function getModulesPath(): string
    {
        if ($this->modulesPath === '') {
            $this->modulesPath = (defined('BASE_PATH') ? BASE_PATH : getcwd() . '/') . 'modules';
        }

        return $this->modulesPath;
    }

    /**
     * Discover, register, and boot all feature modules.
     */
    public function boot(Plugs $app): void
    {
        if ($this->booted) {
            return;
        }

        $container = Container::getInstance();
        $config = $this->getConfig();

        // Discover modules
        $moduleInstances = $this->discoverModules($config);

        // Phase 1: Register
        foreach ($moduleInstances as $module) {
            $module->register($container);
        }

        // Phase 2: Boot
        foreach ($moduleInstances as $module) {
            $module->boot($app);

            // Register view namespace if Views directory exists
            $viewPath = $module->getViewPath();
            if (is_dir($viewPath)) {
                $namespace = strtolower($module->getName());
                if ($container->has(\Plugs\View\ViewManager::class)) {
                    $container->make(\Plugs\View\ViewManager::class)->addNamespace($namespace, $viewPath);
                }
            }

            $this->modules[] = $module;
        }

        // Store manager in container for global access
        $container->instance(self::class, $this);

        $this->booted = true;
    }

    /**
     * Discover feature modules from filesystem and config.
     *
     * @param array<string, mixed> $config
     * @return FeatureModuleInterface[]
     */
    protected function discoverModules(array $config): array
    {
        $modulesPath = $this->getModulesPath();
        $modules = [];

        if (!is_dir($modulesPath)) {
            return [];
        }

        $autoDiscover = $config['auto_discover'] ?? true;
        $enabled = $config['enabled'] ?? [];
        $disabled = $config['disabled'] ?? [];

        if ($autoDiscover) {
            // Scan modules/ directory for module directories
            $dirs = array_filter(glob($modulesPath . '/*'), 'is_dir');

            foreach ($dirs as $dir) {
                $name = basename($dir);

                // Skip disabled modules
                if (in_array($name, $disabled, true)) {
                    continue;
                }

                $module = $this->resolveModule($name, $dir);
                if ($module !== null) {
                    $modules[] = $module;
                }
            }
        } else {
            // Only load explicitly enabled modules
            foreach ($enabled as $name) {
                if (in_array($name, $disabled, true)) {
                    continue;
                }

                $dir = $modulesPath . DIRECTORY_SEPARATOR . $name;
                if (!is_dir($dir)) {
                    continue;
                }

                $module = $this->resolveModule($name, $dir);
                if ($module !== null) {
                    $modules[] = $module;
                }
            }
        }

        return $modules;
    }

    /**
     * Resolve a module instance from its directory.
     *
     * Looks for a {Name}Module.php file first, then falls back to
     * creating a convention-based module.
     */
    protected function resolveModule(string $name, string $dir): ?FeatureModuleInterface
    {
        // Look for explicit module class: {Name}Module.php
        $moduleFile = $dir . DIRECTORY_SEPARATOR . $name . 'Module.php';
        $className = 'Modules\\' . $name . '\\' . $name . 'Module';

        if (file_exists($moduleFile)) {
            // Ensure the file is loaded (PSR-4 should handle this, but just in case)
            if (!class_exists($className)) {
                require_once $moduleFile;
            }

            if (class_exists($className)) {
                $instance = new $className();

                if ($instance instanceof FeatureModuleInterface) {
                    if ($instance instanceof AbstractFeatureModule) {
                        $instance->setPath($dir);
                    }

                    return $instance;
                }
            }
        }

        // Fallback: Create a convention-based module
        return new ConventionModule($name, $dir);
    }

    /**
     * Get the modules config.
     *
     * @return array<string, mixed>
     */
    protected function getConfig(): array
    {
        if (function_exists('config')) {
            return config('modules') ?: [];
        }

        return [];
    }

    /**
     * Get all booted feature modules.
     *
     * @return FeatureModuleInterface[]
     */
    public function getModules(): array
    {
        return $this->modules;
    }

    /**
     * Get a specific module by name.
     */
    public function getModule(string $name): ?FeatureModuleInterface
    {
        foreach ($this->modules as $module) {
            if ($module->getName() === $name) {
                return $module;
            }
        }

        return null;
    }

    /**
     * Check if a module is loaded.
     */
    public function hasModule(string $name): bool
    {
        return $this->getModule($name) !== null;
    }

    /**
     * Get all migration paths from all loaded feature modules.
     *
     * @return string[]
     */
    public function getMigrationPaths(): array
    {
        $paths = [];

        foreach ($this->modules as $module) {
            $migrationPath = $module->getMigrationPath();
            if ($migrationPath !== null && is_dir($migrationPath)) {
                $paths[] = $migrationPath;
            }
        }

        return $paths;
    }

    /**
     * Get all web route files from all loaded feature modules.
     *
     * @return array<int, array{file: string, module: FeatureModuleInterface}>
     */
    public function getWebRouteEntries(): array
    {
        $entries = [];

        foreach ($this->modules as $module) {
            foreach ($module->getWebRouteFiles() as $file) {
                $entries[] = [
                    'file' => $file,
                    'module' => $module,
                ];
            }
        }

        return $entries;
    }

    /**
     * Get all API route files from all loaded feature modules.
     *
     * @return array<int, array{file: string, module: FeatureModuleInterface}>
     */
    public function getApiRouteEntries(): array
    {
        $entries = [];

        foreach ($this->modules as $module) {
            foreach ($module->getApiRouteFiles() as $file) {
                $entries[] = [
                    'file' => $file,
                    'module' => $module,
                ];
            }
        }

        return $entries;
    }
}
