<?php

declare(strict_types=1);

namespace Modules\Admin\Services;

use Modules\Admin\Services\ModuleService;

class AdminModuleService
{
    protected ModuleService $moduleService;

    public function __construct(ModuleService $moduleService = null)
    {
        $this->moduleService = $moduleService ?: new ModuleService();
    }

    /**
     * Get all modules.
     */
    public function getModules(): array
    {
        return $this->moduleService->getModules();
    }

    /**
     * Create a new module.
     */
    public function createModule(string $name): void
    {
        $this->moduleService->createModule($name);
    }

    /**
     * Toggle module status.
     */
    public function toggleModuleStatus(string $name): bool
    {
        return $this->moduleService->toggleModuleStatus($name);
    }

    /**
     * Get module settings.
     */
    public function getModuleSettings(string $name): array
    {
        return $this->moduleService->getModuleSettings($name);
    }

    /**
     * Update module settings.
     */
    public function updateModuleSettings(string $name, array $settings): bool
    {
        return $this->moduleService->updateModuleSettings($name, $settings);
    }

    /**
     * Delete a module.
     */
    public function deleteModule(string $name): bool
    {
        return $this->moduleService->deleteModule($name);
    }
}
