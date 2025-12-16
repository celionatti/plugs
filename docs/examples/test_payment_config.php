<?php

/**
 * Test Script for Payment Configuration
 * 
 * This script verifies that the payment configuration system works correctly
 * and can load credentials from environment variables.
 */

// Load the environment variables from .env file
function loadEnv($path)
{
    if (!file_exists($path)) {
        return false;
    }

    $lines = file($path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    foreach ($lines as $line) {
        // Skip comments
        if (strpos(trim($line), '#') === 0) {
            continue;
        }

        // Parse KEY=VALUE
        $parts = explode('=', $line, 2);
        if (count($parts) === 2) {
            $key = trim($parts[0]);
            $value = trim($parts[1]);

            // Remove quotes if present
            if (
                preg_match('/^["\'](.*)["\'
]$/', $value, $matches)
            ) {
                $value = $matches[1];
            }

            $_ENV[$key] = $value;
            putenv("$key=$value");
        }
    }

    return true;
}

// Load .env file
$envPath = __DIR__ . '/../.env';
if (!loadEnv($envPath)) {
    echo "⚠️  Warning: .env file not found at {$envPath}\n";
    echo "Please copy .env.example to .env and configure your credentials.\n\n";
}

require_once __DIR__ . '/../vendor/autoload.php';

use Plugs\TransactionHandler\PaymentConfig;
use Plugs\TransactionHandler\PaymentTransactionHandler;

echo "==========================================================\n";
echo "        Payment Configuration Verification Test           \n";
echo "==========================================================\n\n";

// Test 1: Check if environment variables are loaded
echo "Test 1: Environment Variables\n";
echo "----------------------------\n";

$envVars = [
    'PAYSTACK_PUBLIC_KEY',
    'PAYSTACK_SECRET_KEY',
    'FLUTTERWAVE_PUBLIC_KEY',
    'FLUTTERWAVE_SECRET_KEY',
    'DEFAULT_PAYMENT_PLATFORM',
    'PAYMENT_CURRENCY'
];

$envLoaded = 0;
foreach ($envVars as $var) {
    $value = $_ENV[$var] ?? getenv($var);
    if ($value) {
        echo "✓ {$var}: " . substr($value, 0, 20) . "...\n";
        $envLoaded++;
    } else {
        echo "✗ {$var}: Not set\n";
    }
}

echo "\nLoaded {$envLoaded}/" . count($envVars) . " environment variables\n\n";

// Test 2: Validate platform configurations
echo "Test 2: Platform Configuration Validation\n";
echo "-------------------------------------------\n";

$platforms = [
    'paystack' => 'Paystack',
    'flutterwave' => 'Flutterwave',
    'stripe' => 'Stripe',
    'paypal' => 'PayPal',
    'btcpay' => 'BTCPay Server'
];

$configuredPlatforms = [];

foreach ($platforms as $key => $name) {
    $isValid = PaymentConfig::validateConfig($key);
    if ($isValid) {
        echo "✓ {$name}: Configuration valid\n";
        $configuredPlatforms[] = $key;
    } else {
        echo "✗ {$name}: Configuration missing or incomplete\n";
    }
}

echo "\n" . count($configuredPlatforms) . " platform(s) properly configured\n\n";

// Test 3: Create payment handlers
echo "Test 3: Payment Handler Creation\n";
echo "---------------------------------\n";

foreach ($configuredPlatforms as $platform) {
    try {
        $handler = PaymentConfig::create($platform);
        echo "✓ {$platforms[$platform]}: Handler created successfully\n";
        echo "  Platform: " . ucfirst($platform) . "\n";
        echo "  Class: " . get_class($handler) . "\n";
    } catch (Exception $e) {
        echo "✗ {$platforms[$platform]}: Failed - " . $e->getMessage() . "\n";
    }
}

echo "\n";

// Test 4: Default configuration
echo "Test 4: Default Configuration\n";
echo "------------------------------\n";

try {
    $defaultPlatform = $_ENV['DEFAULT_PAYMENT_PLATFORM'] ?? 'paystack';
    echo "Default Platform: " . ucfirst($defaultPlatform) . "\n";

    $defaultHandler = PaymentConfig::create();
    echo "✓ Default handler created successfully\n";

    $defaultCurrency = PaymentConfig::getDefaultCurrency();
    echo "Default Currency: {$defaultCurrency}\n";

    $testMode = PaymentConfig::isTestMode();
    echo "Test Mode: " . ($testMode ? "Yes" : "No") . "\n";

} catch (Exception $e) {
    echo "✗ Failed: " . $e->getMessage() . "\n";
}

echo "\n";

// Test 5: Test payment data structure
echo "Test 5: Payment Data Structure Test\n";
echo "------------------------------------\n";

if (count($configuredPlatforms) > 0) {
    $testPlatform = $configuredPlatforms[0];

    try {
        $handler = PaymentConfig::create($testPlatform);

        // Prepare test payment data (will not actually charge)
        $testData = [
            'amount' => 100, // 1.00 in smallest unit
            'email' => 'test@example.com',
            'currency' => PaymentConfig::getDefaultCurrency(),
            'metadata' => [
                'test' => true,
                'timestamp' => time()
            ]
        ];

        echo "✓ Test payment data structure created\n";
        echo "  Platform: " . ucfirst($testPlatform) . "\n";
        echo "  Amount: " . $testData['amount'] . "\n";
        echo "  Currency: " . $testData['currency'] . "\n";
        echo "  Email: " . $testData['email'] . "\n";
        echo "\n";
        echo "⚠️  Note: Actual API call not made in this test\n";
        echo "   Use the examples in docs/examples/ for real transactions\n";

    } catch (Exception $e) {
        echo "✗ Failed: " . $e->getMessage() . "\n";
    }
} else {
    echo "⚠️  No platforms configured for testing\n";
}

echo "\n";

// Summary
echo "==========================================================\n";
echo "                    Test Summary                          \n";
echo "==========================================================\n\n";

if (count($configuredPlatforms) >= 2) {
    echo "✓ SUCCESS: Your payment system is properly configured!\n";
    echo "\n";
    echo "Configured platforms: " . implode(', ', array_map('ucfirst', $configuredPlatforms)) . "\n";
    echo "\n";
    echo "Next steps:\n";
    echo "1. Review the examples in docs/examples/payment_usage_example.php\n";
    echo "2. Set up webhooks in your payment platform dashboard\n";
    echo "3. Test with test API keys before going live\n";
} elseif (count($configuredPlatforms) === 1) {
    echo "✓ PARTIAL: One payment platform configured\n";
    echo "\n";
    echo "Consider configuring additional platforms for redundancy.\n";
} else {
    echo "✗ INCOMPLETE: No payment platforms configured\n";
    echo "\n";
    echo "Please:\n";
    echo "1. Copy .env.example to .env\n";
    echo "2. Add your payment gateway credentials\n";
    echo "3. Run this test again\n";
}

echo "\n==========================================================\n";
