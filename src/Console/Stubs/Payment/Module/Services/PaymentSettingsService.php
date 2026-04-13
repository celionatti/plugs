<?php

declare(strict_types=1);

namespace Modules\Payment\Services;

use Modules\Admin\Models\Setting;

class PaymentSettingsService
{
    /**
     * Metadata for all supported payment platforms.
     *
     * @return array
     */
    public function getAvailablePlatforms(): array
    {
        return [
            'stripe' => [
                'name' => 'Stripe',
                'slug' => 'stripe',
                'description' => 'Accept credit cards, debit cards, and popular payment methods worldwide.',
                'icon' => 'bi-stripe',
                'fields' => [
                    'secret_key' => ['label' => 'Secret Key', 'type' => 'password', 'required' => true, 'placeholder' => 'sk_live_...'],
                    'public_key' => ['label' => 'Publishable Key', 'type' => 'text', 'required' => true, 'placeholder' => 'pk_live_...'],
                    'webhook_secret' => ['label' => 'Webhook Secret', 'type' => 'password', 'required' => false, 'placeholder' => 'whsec_...'],
                ],
            ],
            'paystack' => [
                'name' => 'Paystack',
                'slug' => 'paystack',
                'description' => 'Accept payments from customers in Africa via card, bank transfer, and mobile money.',
                'icon' => 'bi-credit-card-2-front',
                'fields' => [
                    'secret_key' => ['label' => 'Secret Key', 'type' => 'password', 'required' => true, 'placeholder' => 'sk_live_...'],
                    'public_key' => ['label' => 'Public Key', 'type' => 'text', 'required' => true, 'placeholder' => 'pk_live_...'],
                    'webhook_secret' => ['label' => 'Webhook Secret', 'type' => 'password', 'required' => false, 'placeholder' => 'Optional'],
                ],
            ],
            'paypal' => [
                'name' => 'PayPal',
                'slug' => 'paypal',
                'description' => 'Let customers pay with their PayPal account, credit card, or debit card.',
                'icon' => 'bi-paypal',
                'fields' => [
                    'client_id' => ['label' => 'Client ID', 'type' => 'text', 'required' => true, 'placeholder' => 'Your PayPal Client ID'],
                    'client_secret' => ['label' => 'Client Secret', 'type' => 'password', 'required' => true, 'placeholder' => 'Your PayPal Client Secret'],
                    'mode' => ['label' => 'Mode', 'type' => 'select', 'required' => true, 'options' => ['sandbox' => 'Sandbox (Testing)', 'live' => 'Live (Production)']],
                ],
            ],
            'flutterwave' => [
                'name' => 'Flutterwave',
                'slug' => 'flutterwave',
                'description' => 'Accept payments across Africa and globally with cards, bank transfers, and more.',
                'icon' => 'bi-globe2',
                'fields' => [
                    'secret_key' => ['label' => 'Secret Key', 'type' => 'password', 'required' => true, 'placeholder' => 'FLWSECK-...'],
                    'public_key' => ['label' => 'Public Key', 'type' => 'text', 'required' => true, 'placeholder' => 'FLWPUBK-...'],
                    'encryption_key' => ['label' => 'Encryption Key', 'type' => 'password', 'required' => false, 'placeholder' => 'Optional'],
                ],
            ],
            'payoneer' => [
                'name' => 'Payoneer',
                'slug' => 'payoneer',
                'description' => 'Global payment platform for cross-border transactions and mass payouts.',
                'icon' => 'bi-bank',
                'fields' => [
                    'api_key' => ['label' => 'API Key', 'type' => 'password', 'required' => true, 'placeholder' => 'Your Payoneer API Key'],
                    'program_id' => ['label' => 'Program ID', 'type' => 'text', 'required' => false, 'placeholder' => 'Optional Program ID'],
                ],
            ],
            'btcpay' => [
                'name' => 'BTCPay Server',
                'slug' => 'btcpay',
                'description' => 'Accept Bitcoin and cryptocurrency payments via self-hosted BTCPay Server.',
                'icon' => 'bi-currency-bitcoin',
                'fields' => [
                    'api_key' => ['label' => 'API Key', 'type' => 'password', 'required' => true, 'placeholder' => 'Your BTCPay API Key'],
                    'store_id' => ['label' => 'Store ID', 'type' => 'text', 'required' => true, 'placeholder' => 'Your BTCPay Store ID'],
                    'base_url' => ['label' => 'Server URL', 'type' => 'text', 'required' => true, 'placeholder' => 'https://btcpay.yourdomain.com'],
                    'webhook_secret' => ['label' => 'Webhook Secret', 'type' => 'password', 'required' => false, 'placeholder' => 'Optional'],
                ],
            ],
        ];
    }

    /**
     * Get all payment-related settings.
     *
     * @return array
     */
    public function getPaymentSettings(): array
    {
        $platforms = $this->getAvailablePlatforms();
        $settings = [
            'payment_mode' => Setting::getValue('payment_mode', 'single'),
            'payment_default_platform' => Setting::getValue('payment_default_platform', 'stripe'),
            'payment_multi_platforms' => Setting::getValue('payment_multi_platforms', ''),
            'payment_default_currency' => Setting::getValue('payment_default_currency', 'USD'),
        ];

        // Load per-platform credentials
        foreach ($platforms as $slug => $platform) {
            $settings["payment_{$slug}_enabled"] = Setting::getValue("payment_{$slug}_enabled", 'false');
            foreach ($platform['fields'] as $fieldKey => $fieldMeta) {
                $settings["payment_{$slug}_{$fieldKey}"] = Setting::getValue("payment_{$slug}_{$fieldKey}", '');
            }
        }

        return $settings;
    }

    /**
     * Update payment settings.
     *
     * @param array $data
     * @return void
     */
    public function updatePaymentSettings(array $data): void
    {
        $platforms = $this->getAvailablePlatforms();

        // Core settings
        $coreKeys = ['payment_mode', 'payment_default_platform', 'payment_multi_platforms', 'payment_default_currency'];
        foreach ($coreKeys as $key) {
            if (array_key_exists($key, $data)) {
                Setting::setValue($key, (string) $data[$key], 'payment');
            }
        }

        // Per-platform settings
        foreach ($platforms as $slug => $platform) {
            $enabledKey = "payment_{$slug}_enabled";
            // Checkboxes: if not in POST, it means unchecked → 'false'
            $enabledValue = isset($data[$enabledKey]) ? 'true' : 'false';
            Setting::setValue($enabledKey, $enabledValue, 'payment');

            foreach ($platform['fields'] as $fieldKey => $fieldMeta) {
                $settingKey = "payment_{$slug}_{$fieldKey}";
                if (array_key_exists($settingKey, $data)) {
                    Setting::setValue($settingKey, (string) $data[$settingKey], 'payment');
                }
            }
        }
    }

    /**
     * Get runtime-ready config array for PaymentManager.
     *
     * @return array
     */
    public function getActiveConfig(): array
    {
        $settings = $this->getPaymentSettings();
        $platforms = $this->getAvailablePlatforms();

        $drivers = [];
        foreach ($platforms as $slug => $platform) {
            if ($settings["payment_{$slug}_enabled"] === 'true') {
                $driverConfig = [];
                foreach ($platform['fields'] as $fieldKey => $fieldMeta) {
                    $driverConfig[$fieldKey] = $settings["payment_{$slug}_{$fieldKey}"] ?? '';
                }
                $drivers[$slug] = $driverConfig;
            }
        }

        return [
            'default' => $settings['payment_default_platform'],
            'mode' => $settings['payment_mode'],
            'multi_platforms' => array_filter(explode(',', $settings['payment_multi_platforms'] ?? '')),
            'currency' => $settings['payment_default_currency'],
            'drivers' => $drivers,
        ];
    }
}
