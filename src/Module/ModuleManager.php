<?php

declare(strict_types=1);

namespace Plugs\Module;

use Plugs\Bootstrap\ContextType;
use Plugs\Container\Container;
use Plugs\Plugs;

class ModuleManager
{
    /**
     * The singleton instance.
     */
    private static ?ModuleManager $instance = null;

    /**
     * The list of fully qualified module class names or instances.
     * @var array<string|ModuleInterface>
     */
    protected array $modules = [];

    /**
     * The names of modules that have been explicitly disabled.
     * @var string[]
     */
    protected array $disabledModules = [];

    /**
     * The list of booted modules.
     * @var ModuleInterface[]
     */
    protected array $bootedModules = [];

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

    private function __construct()
    {
        // Protected constructor for singleton
    }

    /**
     * Register a module or list of modules.
     *
     * @param string|ModuleInterface|array<string|ModuleInterface> $modules
     */
    public function addModule($modules): self
    {
        if (!is_array($modules)) {
            $modules = [$modules];
        }

        foreach ($modules as $module) {
            $this->modules[] = $module;
        }

        return $this;
    }

    /**
     * Disable a specific module by name.
     */
    public function disableModule(string $name): self
    {
        if (!in_array($name, $this->disabledModules, true)) {
            $this->disabledModules[] = $name;
        }

        return $this;
    }

    /**
     * Check if a module is enabled.
     */
    public function isEnabled(string $name): bool
    {
        return !in_array($name, $this->disabledModules, true);
    }

    /**
     * Boot all registered modules that should boot in the current context.
     */
    public function bootModules(Plugs $app, ContextType $context): void
    {
        $container = Container::getInstance();

        // Instantiate modules
        /** @var ModuleInterface[] $instances */
        $instances = [];
        foreach ($this->modules as $module) {
            if (is_string($module)) {
                $instance = $container->make($module);
            } else {
                $instance = $module;
            }

            if (!$instance instanceof ModuleInterface) {
                throw new \RuntimeException(sprintf('Module %s must implement ModuleInterface', get_class($instance)));
            }

            $instances[] = $instance;
        }

        // Process only enabled modules that should boot in this context
        $activeModules = [];
        foreach ($instances as $instance) {
            if ($this->isEnabled($instance->getName()) && $instance->shouldBoot($context)) {
                $activeModules[] = $instance;
            }
        }

        // Phase 1: Register
        foreach ($activeModules as $module) {
            $module->register($container);
        }

        // Phase 2: Boot
        foreach ($activeModules as $module) {
            $module->boot($app);
            $this->bootedModules[] = $module;
        }
    }

    /**
     * Get currently booted modules.
     *
     * @return ModuleInterface[]
     */
    public function getBootedModules(): array
    {
        return $this->bootedModules;
    }

    /**
     * Reset the manager (mainly for testing).
     */
    public static function reset(): void
    {
        self::$instance = null;
    }
}
