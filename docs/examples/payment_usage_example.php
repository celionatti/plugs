<?php

/**
 * Payment Gateway Usage Examples
 * 
 * This file demonstrates how to use the Plugs Payment Transaction Handler
 * across different projects with Paystack and Flutterwave integration
 */

require_once __DIR__ . '/../../vendor/autoload.php';

use Plugs\TransactionHandler\PaymentConfig;
use Plugs\TransactionHandler\PaymentTransactionHandler;

// ============================================
// Example 1: Using PaymentConfig (Recommended)
// ============================================

echo "=== Example 1: Simple Payment Initialization ===\n\n";

try {
    // Create payment handler from .env configuration
    $payment = PaymentConfig::create('paystack'); // or 'flutterwave'

    // Process a one-time payment
    $result = $payment->processOneTimePayment([
        'amount' => 5000, // Amount in smallest currency unit (e.g., kobo for NGN)
        'email' => 'customer@example.com',
        'currency' => 'NGN',
        'callback_url' => 'https://yoursite.com/payment/callback'
    ]);

    if ($result['status'] === PaymentTransactionHandler::STATUS_SUCCESS) {
        echo "Payment initialized successfully!\n";
        echo "Authorization URL: " . $result['data']['authorization_url'] . "\n";
        echo "Reference: " . $result['data']['reference'] . "\n";
    }

} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
}

// ============================================
// Example 2: Manual Configuration
// ============================================

echo "\n=== Example 2: Manual Configuration ===\n\n";

try {
    // Create handler with manual configuration
    $paystackHandler = new PaymentTransactionHandler('paystack', [
        'secret_key' => 'sk_test_your_secret_key',
        'public_key' => 'pk_test_your_public_key'
    ]);

    $result = $paystackHandler->processOneTimePayment([
        'amount' => 10000,
        'email' => 'user@example.com',
        'metadata' => [
            'order_id' => '12345',
            'customer_name' => 'John Doe'
        ]
    ]);

    echo "Payment Status: " . $result['status'] . "\n";

} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
}

// ============================================
// Example 3: Flutterwave Payment
// ============================================

echo "\n=== Example 3: Flutterwave Payment ===\n\n";

try {
    $flutterwave = PaymentConfig::create('flutterwave');

    $result = $flutterwave->processOneTimePayment([
        'amount' => 15000,
        'email' => 'customer@example.com',
        'currency' => 'NGN',
        'customer_name' => 'Jane Doe',
        'phone' => '+2348012345678',
        'title' => 'Product Purchase',
        'description' => 'Payment for Product XYZ',
        'callback_url' => 'https://yoursite.com/payment/callback'
    ]);

    echo "Payment Link: " . ($result['data']['link'] ?? 'N/A') . "\n";

} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
}

// ============================================
// Example 4: Verify Payment
// ============================================

echo "\n=== Example 4: Verify Payment ===\n\n";

try {
    $payment = PaymentConfig::create('paystack');

    // Verify transaction using reference
    $reference = 'txn_reference_from_callback';
    $verification = $payment->verifyTransaction($reference);

    if ($verification['verified']) {
        echo "Transaction verified successfully!\n";
        echo "Amount: " . $verification['data']['amount'] . "\n";
        echo "Status: " . $verification['data']['status'] . "\n";
    } else {
        echo "Verification failed: " . $verification['message'] . "\n";
    }

} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
}

// ============================================
// Example 5: Create Subscription
// ============================================

echo "\n=== Example 5: Create Subscription ===\n\n";

try {
    $payment = PaymentConfig::create('paystack');

    $subscription = $payment->createSubscription([
        'plan_code' => 'PLN_basic_monthly',
        'email' => 'subscriber@example.com',
        'amount' => 5000,
        'currency' => 'NGN',
        'interval' => 'monthly'
    ]);

    echo "Subscription created!\n";
    echo "Subscription Code: " . $subscription['data']['subscription_code'] . "\n";

} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
}

// ============================================
// Example 6: Transfer Funds
// ============================================

echo "\n=== Example 6: Transfer Funds ===\n\n";

try {
    $payment = PaymentConfig::create('paystack');

    // First, create a recipient
    $recipient = $payment->createRecipient([
        'type' => 'nuban',
        'name' => 'Recipient Name',
        'account_number' => '0123456789',
        'bank_code' => '058', // GTBank code
        'currency' => 'NGN'
    ]);

    echo "Recipient created: " . $recipient['data']['recipient_code'] . "\n";

    // Then initiate transfer
    $transfer = $payment->transferFunds([
        'amount' => 50000,
        'recipient' => $recipient['data']['recipient_code'],
        'reason' => 'Payment for services',
        'currency' => 'NGN'
    ]);

    echo "Transfer initiated!\n";
    echo "Transfer Code: " . $transfer['data']['transfer_code'] . "\n";

} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
}

// ============================================
// Example 7: Refund Transaction
// ============================================

echo "\n=== Example 7: Refund Transaction ===\n\n";

try {
    $payment = PaymentConfig::create('flutterwave');

    $refund = $payment->refundTransaction([
        'transaction_id' => '12345',
        'amount' => 5000, // Optional: partial refund
        'reason' => 'Customer request'
    ]);

    echo "Refund processed!\n";
    echo "Refund Status: " . $refund['status'] . "\n";

} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
}

// ============================================
// Example 8: Handle Webhook
// ============================================

echo "\n=== Example 8: Handle Webhook ===\n\n";

// This would typically be in a separate webhook handler file
// e.g., webhook.php

/**
 * webhook.php - Sample webhook handler
 */
/*
<?php

require_once __DIR__ . '/vendor/autoload.php';

use Plugs\TransactionHandler\PaymentConfig;

// Get the payload from the request body
$payload = @file_get_contents('php://input');
$event = json_decode($payload, true);

try {
    // Create payment handler
    $payment = PaymentConfig::create('paystack'); // or 'flutterwave'

    // Handle the webhook
    $result = $payment->handleWebhook($event);

    // Process based on event type
    switch ($result['data']['event']) {
        case 'payment_successful':
            // Update order status
            // Send confirmation email
            break;

        case 'transfer_successful':
            // Update transfer status
            break;

        case 'refund_successful':
            // Update refund status
            break;
    }

    http_response_code(200);
    echo json_encode(['status' => 'success']);

} catch (Exception $e) {
    http_response_code(400);
    echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
}
 */

// ============================================
// Example 9: Get Account Balance
// ============================================

echo "\n=== Example 9: Get Account Balance ===\n\n";

try {
    $payment = PaymentConfig::create('paystack');

    $balance = $payment->getBalance();

    foreach ($balance['data'] as $wallet) {
        echo "Currency: " . $wallet['currency'] . "\n";
        echo "Balance: " . $wallet['balance'] . "\n";
        echo "---\n";
    }

} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
}

// ============================================
// Example 10: List Transactions
// ============================================

echo "\n=== Example 10: List Transactions ===\n\n";

try {
    $payment = PaymentConfig::create('paystack');

    $transactions = $payment->listTransactions([
        'perPage' => 10,
        'page' => 1,
        'from' => '2024-01-01',
        'to' => '2024-12-31'
    ]);

    foreach ($transactions['data'] as $transaction) {
        echo "Reference: " . $transaction['reference'] . "\n";
        echo "Amount: " . $transaction['amount'] . "\n";
        echo "Status: " . $transaction['status'] . "\n";
        echo "---\n";
    }

} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
}

// ============================================
// Example 11: Switching Between Platforms
// ============================================

echo "\n=== Example 11: Dynamic Platform Switching ===\n\n";

try {
    // Get platform from configuration or user preference
    $platform = $_ENV['DEFAULT_PAYMENT_PLATFORM'] ?? 'paystack';

    $payment = PaymentConfig::create($platform);

    echo "Using platform: " . $platform . "\n";

    // Process payment - same code works for any platform!
    $result = $payment->processOneTimePayment([
        'amount' => 5000,
        'email' => 'customer@example.com',
        'currency' => PaymentConfig::getDefaultCurrency()
    ]);

    echo "Payment initialized on " . $result['platform'] . "\n";

} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
}

// ============================================
// Example 12: Validate Configuration
// ============================================

echo "\n=== Example 12: Validate Configuration ===\n\n";

$platforms = ['paystack', 'flutterwave', 'stripe'];

foreach ($platforms as $platform) {
    $isValid = PaymentConfig::validateConfig($platform);
    echo ucfirst($platform) . " configuration: " . ($isValid ? "✓ Valid" : "✗ Invalid") . "\n";
}

echo "\n=== Examples Complete ===\n";
