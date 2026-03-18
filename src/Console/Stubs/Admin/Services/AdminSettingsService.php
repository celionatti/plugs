<?php

declare(strict_types=1);

namespace Modules\Admin\Services;

use App\Models\Setting;

class AdminSettingsService
{
    /**
     * Get all active settings.
     *
     * @return array
     */
    public function getSettings(): array
    {
        return [
            // General
            'site_name' => Setting::getValue('site_name', 'Plugs Framework'),
            'site_description' => Setting::getValue('site_description', 'A premium PHP framework.'),
            'admin_email' => Setting::getValue('admin_email', 'admin@plugs.io'),
            
            // Appearance
            'primary_color' => Setting::getValue('primary_color', '#6366f1'),
            'secondary_color' => Setting::getValue('secondary_color', '#4f46e5'),
            'border_radius' => Setting::getValue('border_radius', '1.5rem'),
            'dark_mode' => Setting::getValue('dark_mode', 'false'),
            
            // Security
            'registration_enabled' => Setting::getValue('registration_enabled', 'true'),
            'two_factor_auth' => Setting::getValue('two_factor_auth', 'false'),
            
            // SEO
            'meta_keywords' => Setting::getValue('meta_keywords', 'php, framework, plugs'),
            'google_analytics_id' => Setting::getValue('google_analytics_id', ''),
        ];
    }

    /**
     * Update a collection of settings.
     *
     * @param array $data
     * @return void
     */
    public function updateSettings(array $data): void
    {
        $allowedKeys = [
            'site_name', 'site_description', 'admin_email',
            'primary_color', 'secondary_color', 'dark_mode', 'border_radius',
            'registration_enabled', 'two_factor_auth',
            'meta_keywords', 'google_analytics_id'
        ];

        foreach ($data as $key => $value) {
            if (in_array($key, $allowedKeys)) {
                $group = $this->getSettingGroup($key);
                Setting::setValue($key, (string) $value, $group);
            }
        }
    }

    /**
     * Helper to group settings.
     *
     * @param string $key
     * @return string
     */
    protected function getSettingGroup(string $key): string
    {
        if (str_contains($key, 'color') || str_contains($key, 'dark_mode') || $key === 'border_radius') {
            return 'appearance';
        }
        
        if (str_contains($key, 'registration') || str_contains($key, 'auth')) {
            return 'security';
        }
        
        if (str_contains($key, 'meta') || str_contains($key, 'analytics')) {
            return 'seo';
        }
        
        return 'general';
    }
}
