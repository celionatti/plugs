# Payment Transaction Handler

A universal payment gateway integration system for PHP applications supporting multiple payment platforms including Paystack, Flutterwave, Stripe, PayPal, Payoneer, and BTCPay Server.

## Features

- ✅ **Multiple Payment Platforms** - Support for 6 major payment gateways
- ✅ **Environment-based Configuration** - Easy setup via `.env` files
- ✅ **Universal API** - Same code works across all platforms
- ✅ **Complete Payment Operations** - Charge, verify, transfer, refund, subscriptions
- ✅ **Webhook Handling** - Secure webhook verification and processing
- ✅ **Portable** - Works across any PHP project

## Supported Payment Platforms

| Platform | Status | Payment Types |
|----------|--------|---------------|
| **Paystack** | ✅ Fully Implemented | Payments, Transfers, Subscriptions, Refunds |
| **Flutterwave** | ✅ Fully Implemented | Payments, Transfers, Subscriptions, Refunds |
| **Stripe** | ✅ Fully Implemented | Payment Intents, Subscriptions, Payouts, Refunds |
| **BTCPay Server** | ✅ Fully Implemented | Crypto payments, Invoices, Pull Payments |
| **PayPal** | ⚠️ Partial | Basic payment support |
| **Payoneer** | ⚠️ Partial | Basic payment support |

## Installation

### 1. Copy TransactionHandler to Your Project

```bash
cp -r src/TransactionHandler /path/to/your/project/src/
```

### 2. Set Up Environment Variables

Copy the `.env.example` file to your project root and configure your keys:

```bash
cp .env.example /path/to/your/project/.env
```

Edit `.env` and add your payment gateway credentials:

```env
# Paystack
PAYSTACK_PUBLIC_KEY=pk_test_your_key
PAYSTACK_SECRET_KEY=sk_test_your_key

# Flutterwave
FLUTTERWAVE_PUBLIC_KEY=FLWPUBK_TEST-your_key
FLUTTERWAVE_SECRET_KEY=FLWSECK_TEST-your_key
FLUTTERWAVE_ENCRYPTION_KEY=FLWSECK_TEST-your_encryption_key

# Default settings
DEFAULT_PAYMENT_PLATFORM=paystack
PAYMENT_CURRENCY=NGN
PAYMENT_ENVIRONMENT=test
```

### 3. Autoload the Classes

Ensure your composer autoloader includes the namespace:

```json
{
    "autoload": {
        "psr-4": {
            "Plugs\\TransactionHandler\\": "src/TransactionHandler/"
        }
    }
}
```

Run `composer dump-autoload` after updating.

## Quick Start

### Initialize and Process a Payment (Fluent Interface)

The new fluent interface makes it easier to build and process transactions:

```php
use Plugs\TransactionHandler\PaymentConfig;

// Create payment handler from .env
$payment = PaymentConfig::create();

// Process payment fluently
$result = $payment->amount(5000)
    ->currency('NGN')
    ->email('customer@example.com')
    ->description('Order #1234')
    ->callback('https://yoursite.com/payment/callback')
    ->pay();

if ($result->isSuccessful()) {
    // Get payment link for checkout
    $paymentUrl = $result->getData()['authorization_url'];
    echo "Redirecting to: " . $paymentUrl;
} else {
    echo "Error: " . $result->getMessage();
}
```

### Verify Payment

```php
// Get reference from callback
$reference = $_GET['reference'];

// Verify transaction
$result = $payment->verify($reference);

if ($result->isSuccessful()) {
    // Payment verified
    $data = $result->getData();
    echo "Transaction Status: " . $result->getStatus();
}
```

### Webhook Handling with Logging

```php
// webhook.php
$payload = json_decode(file_get_contents('php://input'), true);

$payment = PaymentConfig::create();
$result = $payment->handleWebhook($payload);

// Logging is automatic if configured, or manually check result
if ($result->isSuccessful()) {
    $event = $result->getData()['event'];
    // Update your database based on $event
}
```

## Available Methods (Modern API)

### Payment Operations

```php
// Build and pay (Fluent)
$payment->amount(100.50)->email('user@test.com')->pay();

// Verify transaction (Standardized Result)
$payment->verify(string $reference);

// Get transaction details
$payment->getTransaction(string $transactionId);

// List transactions
$payment->list(array $filters);
```

### Subscriptions

```php
// Create subscription
$payment->createSubscription(array $data);

// Cancel subscription
$payment->cancelSubscription(string $subscriptionId);
```

### Transfers & Withdrawals

```php
// Create recipient
$payment->createRecipient(array $data);

// Transfer funds
$payment->transferFunds(array $data);

// Withdraw to bank
$payment->withdrawFunds(array $data);
```

### Refunds

```php
// Refund transaction
$payment->refundTransaction(array $data);
```

### Account

```php
// Get balance
$payment->getBalance();
```

### Webhooks

```php
// Handle webhook
$payment->handleWebhook(array $payload);
```

## Configuration Reference

### Environment Variables

| Variable | Required | Description |
|----------|----------|-------------|
| `DEFAULT_PAYMENT_PLATFORM` | No | Default platform (paystack, flutterwave, etc.) |
| `PAYMENT_CURRENCY` | No | Default currency (NGN, USD, etc.) |
| `PAYMENT_ENVIRONMENT` | No | Environment (test, live) |

### Paystack

| Variable | Required |
|----------|----------|
| `PAYSTACK_PUBLIC_KEY` | Yes |
| `PAYSTACK_SECRET_KEY` | Yes |
| `PAYSTACK_WEBHOOK_SECRET` | No |

### Flutterwave

| Variable | Required |
|----------|----------|
| `FLUTTERWAVE_PUBLIC_KEY` | Yes |
| `FLUTTERWAVE_SECRET_KEY` | Yes |
| `FLUTTERWAVE_ENCRYPTION_KEY` | No |

### Other Platforms

See `.env.example` for complete configuration options for Stripe, PayPal, Payoneer, and BTCPay Server.

## Examples

Comprehensive examples are available in `docs/examples/payment_usage_example.php` including:

1. Simple payment initialization
2. Manual configuration
3. Flutterwave payments
4. Payment verification
5. Subscription management
6. Fund transfers
7. Refund processing
8. Webhook handling
9. Balance checking
10. Transaction listing
11. Dynamic platform switching
12. Configuration validation

## Platform-Specific Notes

### Paystack
- Amounts are in kobo (smallest currency unit)
- Supports NGN, GHS, ZAR, USD
- Webhook signature verification via `X-Paystack-Signature` header

### Flutterwave
- Amounts are in the specified currency unit
- Wide currency support
- Webhook verification via `verif-hash` header

## Testing

Both Paystack and Flutterwave provide test API keys:

- **Paystack**: Use `pk_test_` and `sk_test_` keys from dashboard
- **Flutterwave**: Use `FLWPUBK_TEST-` and `FLWSECK_TEST-` keys

Test cards and account numbers are available in their respective documentation.

## Error Handling

All methods throw `Exception` on errors. Always wrap calls in try-catch:

```php
try {
    $result = $payment->processOneTimePayment($data);
} catch (Exception $e) {
    // Handle error
    error_log($e->getMessage());
}
```

## Security

- ✅ Never commit `.env` file to version control
- ✅ Keep secret keys secure
- ✅ Verify webhook signatures
- ✅ Use HTTPS for callbacks
- ✅ Validate amounts server-side

## Support

For platform-specific documentation:

- **Paystack**: https://paystack.com/docs/api/
- **Flutterwave**: https://developer.flutterwave.com/docs

## License

This package is part of the Plugs Framework.
