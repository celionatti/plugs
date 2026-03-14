<?php

declare(strict_types=1);

namespace Modules\Admin\Controllers;

use Plugs\Http\ResponseFactory;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use App\Models\Setting;

class AdminSettingsController
{
    /**
     * Display settings page.
     */
    public function index(ServerRequestInterface $request): ResponseInterface
    {
        $settings = [
            'site_name' => Setting::getValue('site_name', 'Plugs Framework'),
            'site_description' => Setting::getValue('site_description', 'A premium PHP framework.'),
            'admin_email' => Setting::getValue('admin_email', 'admin@plugs.io'),
        ];

        return response(view('admin::settings', [
            'title' => 'Settings',
            'settings' => $settings
        ]));
    }

    /**
     * Update settings.
     */
    public function store(ServerRequestInterface $request): ResponseInterface
    {
        $data = $request->getParsedBody();

        foreach ($data as $key => $value) {
            if (in_array($key, ['site_name', 'site_description', 'admin_email'])) {
                Setting::setValue($key, $value);
            }
        }

        return ResponseFactory::redirect(route('admin.settings.index'))
            ->with('success', 'Settings updated successfully.');
    }
}
