<?php

declare(strict_types=1);

namespace Modules\Admin\Controllers;

use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Modules\Admin\Services\AdminModuleService;

class AdminModuleController
{
    protected AdminModuleService $moduleService;

    public function __construct(AdminModuleService $moduleService)
    {
        $this->moduleService = $moduleService;
    }

    /**
     * List all installed modules.
     */
    public function index(ServerRequestInterface $request): ResponseInterface
    {
        $modules = $this->moduleService->getModules();

        return response(view('admin::modules-list.index', [
            'title' => 'Modules Management',
            'modules' => $modules
        ]));
    }

    /**
     * Show form to create a new module.
     */
    public function create(ServerRequestInterface $request): ResponseInterface
    {
        return response(view('admin::modules-list.create', [
            'title' => 'Create New Module'
        ]));
    }

    /**
     * Store a newly created module.
     */
    public function store(ServerRequestInterface $request): ResponseInterface
    {
        $data = $request->getParsedBody();
        $name = $data['name'] ?? '';

        if (empty($name)) {
            return redirect('/admin/modules?error=Name is required');
        }

        try {
            $this->moduleService->createModule($name);
            return redirect('/admin/modules?success=Module created successfully');
        }
        catch (\Exception $e) {
            return redirect('/admin/modules?error=' . urlencode($e->getMessage()));
        }
    }

    /**
     * Toggle module status.
     */
    public function toggle(ServerRequestInterface $request, string $name): ResponseInterface
    {
        if ($this->moduleService->toggleModuleStatus($name)) {
            return redirect('/admin/modules?success=Module status updated');
        }

        return redirect('/admin/modules?error=Failed to update module status');
    }

    /**
     * Show module configuration page.
     */
    public function show(ServerRequestInterface $request, string $name): ResponseInterface
    {
        $modules = $this->moduleService->getModules();
        $module = null;

        foreach ($modules as $m) {
            if ($m['name'] === $name) {
                $module = $m;
                break;
            }
        }

        if (!$module) {
            return redirect('/admin/modules?error=Module not found');
        }

        $settings = $this->moduleService->getModuleSettings($name);

        return response(view('admin::modules-list.configure', [
            'title' => 'Configure Module: ' . $name,
            'module' => $module,
            'settings' => $settings
        ]));
    }

    /**
     * Update module settings.
     */
    public function updateSettings(ServerRequestInterface $request, string $name): ResponseInterface
    {
        $data = $request->getParsedBody();
        unset($data['_token']); // Remove CSRF token from settings

        if ($this->moduleService->updateModuleSettings($name, $data)) {
            return redirect("/admin/modules/{$name}/configure?success=Settings updated successfully");
        }

        return redirect("/admin/modules/{$name}/configure?error=Failed to update settings");
    }

    /**
     * Delete a module.
     */
    public function destroy(ServerRequestInterface $request, string $name): ResponseInterface
    {
        if ($this->moduleService->deleteModule($name)) {
            return redirect('/admin/modules?success=Module deleted successfully');
        }

        return redirect('/admin/modules?error=Failed to delete module');
    }
}
