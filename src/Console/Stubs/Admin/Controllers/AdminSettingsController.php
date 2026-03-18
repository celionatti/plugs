<?php

declare(strict_types=1);

namespace Modules\Admin\Controllers;

use Plugs\Http\ResponseFactory;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Modules\Admin\Services\AdminSettingsService;

class AdminSettingsController
{
    protected AdminSettingsService $settingsService;

    public function __construct(AdminSettingsService $settingsService)
    {
        $this->settingsService = $settingsService;
    }

    /**
     * Display settings page.
     */
    public function index(ServerRequestInterface $request): ResponseInterface
    {
        $settings = $this->settingsService->getSettings();

        return response(view('admin::settings.index', [
            'title' => 'Settings',
            'settings' => $settings
        ]));
    }

    /**
     * Update settings.
     */
    public function store(ServerRequestInterface $request): ResponseInterface
    {
        $this->settingsService->updateSettings($request->getParsedBody());

        return ResponseFactory::redirect('/admin/settings')
            ->with('success', 'Configuration updated successfully.');
    }

}
