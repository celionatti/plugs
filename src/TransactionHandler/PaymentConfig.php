<?php

declare(strict_types=1);

namespace Plugs\TransactionHandler;

use Exception;

/*
| -----------------------------------------------------------------------
| PaymentConfig
| -----------------------------------------------------------------------
| Configuration helper for payment gateway integration
| Loads credentials from environment variables and creates payment handlers
*/

class PaymentConfig
{
    /**
     * Create a payment transaction handler from environment variables
     * 
     * @param string $platform Payment platform (paystack, flutterwave, stripe, etc.)
     * @return PaymentTransactionHandler
     * @throws Exception
     */
    public static function create(string $platform = null): PaymentTransactionHandler
    {
        // Use default platform if not specified
        if ($platform === null) {
            $platform = $_ENV['DEFAULT_PAYMENT_PLATFORM'] ?? 'paystack';
        }

        $platform = strtolower($platform);
        $config = self::getConfig($platform);

        return new PaymentTransactionHandler($platform, $config);
    }

    /**
     * Get configuration for a specific platform
     * 
     * @param string $platform
     * @return array
     * @throws Exception
     */
    public static function getConfig(string $platform): array
    {
        switch ($platform) {
            case PaymentTransactionHandler::PLATFORM_PAYSTACK:
                return self::getPaystackConfig();

            case PaymentTransactionHandler::PLATFORM_FLUTTERWAVE:
                return self::getFlutterwaveConfig();

            case PaymentTransactionHandler::PLATFORM_STRIPE:
                return self::getStripeConfig();

            case PaymentTransactionHandler::PLATFORM_PAYPAL:
                return self::getPayPalConfig();

            case PaymentTransactionHandler::PLATFORM_PAYONEER:
                return self::getPayoneerConfig();

            case PaymentTransactionHandler::PLATFORM_BTCPAY:
                return self::getBTCPayConfig();

            default:
                throw new Exception("Unsupported payment platform: {$platform}");
        }
    }

    /**
     * Get Paystack configuration
     */
    private static function getPaystackConfig(): array
    {
        $secretKey = self::getEnv('PAYSTACK_SECRET_KEY');
        $publicKey = self::getEnv('PAYSTACK_PUBLIC_KEY');

        if (empty($secretKey) || empty($publicKey)) {
            throw new Exception('Paystack credentials not configured. Set PAYSTACK_SECRET_KEY and PAYSTACK_PUBLIC_KEY in .env');
        }

        return [
            'secret_key' => $secretKey,
            'public_key' => $publicKey,
            'webhook_secret' => self::getEnv('PAYSTACK_WEBHOOK_SECRET', '')
        ];
    }

    /**
     * Get Flutterwave configuration
     */
    private static function getFlutterwaveConfig(): array
    {
        $secretKey = self::getEnv('FLUTTERWAVE_SECRET_KEY');
        $publicKey = self::getEnv('FLUTTERWAVE_PUBLIC_KEY');

        if (empty($secretKey) || empty($publicKey)) {
            throw new Exception('Flutterwave credentials not configured. Set FLUTTERWAVE_SECRET_KEY and FLUTTERWAVE_PUBLIC_KEY in .env');
        }

        return [
            'secret_key' => $secretKey,
            'public_key' => $publicKey,
            'encryption_key' => self::getEnv('FLUTTERWAVE_ENCRYPTION_KEY', '')
        ];
    }

    /**
     * Get Stripe configuration
     */
    private static function getStripeConfig(): array
    {
        $secretKey = self::getEnv('STRIPE_SECRET_KEY');

        if (empty($secretKey)) {
            throw new Exception('Stripe credentials not configured. Set STRIPE_SECRET_KEY in .env');
        }

        return [
            'secret_key' => $secretKey,
            'public_key' => self::getEnv('STRIPE_PUBLIC_KEY', ''),
            'webhook_secret' => self::getEnv('STRIPE_WEBHOOK_SECRET', '')
        ];
    }

    /**
     * Get PayPal configuration
     */
    private static function getPayPalConfig(): array
    {
        $clientId = self::getEnv('PAYPAL_CLIENT_ID');
        $clientSecret = self::getEnv('PAYPAL_CLIENT_SECRET');

        if (empty($clientId) || empty($clientSecret)) {
            throw new Exception('PayPal credentials not configured. Set PAYPAL_CLIENT_ID and PAYPAL_CLIENT_SECRET in .env');
        }

        return [
            'client_id' => $clientId,
            'client_secret' => $clientSecret,
            'mode' => self::getEnv('PAYPAL_MODE', 'sandbox') // sandbox or live
        ];
    }

    /**
     * Get Payoneer configuration
     */
    private static function getPayoneerConfig(): array
    {
        $apiKey = self::getEnv('PAYONEER_API_KEY');

        if (empty($apiKey)) {
            throw new Exception('Payoneer credentials not configured. Set PAYONEER_API_KEY in .env');
        }

        return [
            'api_key' => $apiKey,
            'program_id' => self::getEnv('PAYONEER_PROGRAM_ID', '')
        ];
    }

    /**
     * Get BTCPay configuration
     */
    private static function getBTCPayConfig(): array
    {
        $apiKey = self::getEnv('BTCPAY_API_KEY');
        $storeId = self::getEnv('BTCPAY_STORE_ID');
        $baseUrl = self::getEnv('BTCPAY_BASE_URL');

        if (empty($apiKey) || empty($storeId) || empty($baseUrl)) {
            throw new Exception('BTCPay credentials not configured. Set BTCPAY_API_KEY, BTCPAY_STORE_ID, and BTCPAY_BASE_URL in .env');
        }

        return [
            'api_key' => $apiKey,
            'store_id' => $storeId,
            'base_url' => $baseUrl,
            'webhook_secret' => self::getEnv('BTCPAY_WEBHOOK_SECRET', '')
        ];
    }

    /**
     * Get environment variable with optional default
     * 
     * @param string $key
     * @param mixed $default
     * @return mixed
     */
    private static function getEnv(string $key, $default = null)
    {
        // Try $_ENV first
        if (isset($_ENV[$key])) {
            return $_ENV[$key];
        }

        // Try getenv()
        $value = getenv($key);
        if ($value !== false) {
            return $value;
        }

        // Return default
        return $default;
    }

    /**
     * Get default currency from environment or use fallback
     * 
     * @return string
     */
    public static function getDefaultCurrency(): string
    {
        return self::getEnv('PAYMENT_CURRENCY', 'NGN');
    }

    /**
     * Check if in test mode
     * 
     * @return bool
     */
    public static function isTestMode(): bool
    {
        $environment = self::getEnv('PAYMENT_ENVIRONMENT', 'test');
        return strtolower($environment) === 'test';
    }

    /**
     * Validate that required environment variables are set for a platform
     * 
     * @param string $platform
     * @return bool
     */
    public static function validateConfig(string $platform): bool
    {
        try {
            self::getConfig($platform);
            return true;
        } catch (Exception $e) {
            return false;
        }
    }
}
