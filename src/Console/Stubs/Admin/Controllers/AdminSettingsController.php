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
            // General
            'site_name' => Setting::getValue('site_name', 'Plugs Framework'),
            'site_description' => Setting::getValue('site_description', 'A premium PHP framework.'),
            'admin_email' => Setting::getValue('admin_email', 'admin@plugs.io'),
            
            // Appearance
            'primary_color' => Setting::getValue('primary_color', '#6366f1'),
            'dark_mode' => Setting::getValue('dark_mode', 'false'),
            
            // Security
            'registration_enabled' => Setting::getValue('registration_enabled', 'true'),
            'two_factor_auth' => Setting::getValue('two_factor_auth', 'false'),
            
            // SEO
            'meta_keywords' => Setting::getValue('meta_keywords', 'php, framework, plugs'),
            'google_analytics_id' => Setting::getValue('google_analytics_id', ''),
        ];

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
        $data = $request->getParsedBody();
        $allowedKeys = [
            'site_name', 'site_description', 'admin_email',
            'primary_color', 'secondary_color', 'dark_mode', 'border_radius',
            'registration_enabled', 'two_factor_auth',
            'meta_keywords', 'google_analytics_id'
        ];

        foreach ($data as $key => $value) {
            if (in_array($key, $allowedKeys)) {
                // Determine group based on key
                $group = $this->getSettingGroup($key);
                Setting::setValue($key, (string) $value, $group);
            }
        }

        return ResponseFactory::redirect('/admin/settings')
            ->with('success', 'Configuration updated successfully.');
    }

    /**
     * Helper to group settings.
     */
    protected function getSettingGroup(string $key): string
    {
        if (str_contains($key, 'color') || str_contains($key, 'dark_mode') || $key === 'border_radius') return 'appearance';
        if (str_contains($key, 'registration') || str_contains($key, 'auth')) return 'security';
        if (str_contains($key, 'meta') || str_contains($key, 'analytics')) return 'seo';
        return 'general';
    }
}
