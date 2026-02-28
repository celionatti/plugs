<?php

declare(strict_types=1);

namespace Plugs;

use Plugs\Module\ModuleManager;

class Framework
{
    /**
     * Get the framework version.
     */
    public static function version(): string
    {
        return Plugs::version();
    }

    /**
     * Disable a core module dynamically before the framework boots.
     * 
     * Example: Framework::disableModule('Session');
     */
    public static function disableModule(string $name): void
    {
        ModuleManager::getInstance()->disableModule($name);
    }

    /**
     * Check if a specific module is enabled.
     */
    public static function isModuleEnabled(string $name): bool
    {
        return ModuleManager::getInstance()->isEnabled($name);
    }

    /**
     * Register additional modules dynamically.
     */
    public static function addModule(string|object|array $modules): void
    {
        ModuleManager::getInstance()->addModule($modules);
    }
}
