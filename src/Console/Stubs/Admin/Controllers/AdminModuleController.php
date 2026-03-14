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

        return response(view('admin::modules', [
            'title' => 'Modules Management',
            'modules' => $modules
        ]));
    }
}
