<?php

declare(strict_types=1);

namespace Modules\Admin\Controllers;

use Plugs\Http\ResponseFactory;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use App\Services\ModuleService;

class AdminModuleController
{
    protected ModuleService $moduleService;

    public function __construct(ModuleService $moduleService)
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
        } catch (\Exception $e) {
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
