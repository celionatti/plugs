# Payment Transaction Handler

The Payment Transaction Handler is a universal payment gateway integration system that supports multiple payment platforms including Paystack, Flutterwave, Stripe, PayPal, Payoneer, and BTCPay Server. It provides a modern, fluent API for handling payments, subscriptions, transfers, and more.

## Installation & Configuration

The transaction handler is included in the core framework. To use it, you need to configure your API keys in your `.env` file.

### Environment Variables

Add your credentials to `.env`:

```env
# Default Settings
DEFAULT_PAYMENT_PLATFORM=paystack
PAYMENT_CURRENCY=NGN
PAYMENT_ENVIRONMENT=test

# Paystack
PAYSTACK_PUBLIC_KEY=pk_test_...
PAYSTACK_SECRET_KEY=sk_test_...
PAYSTACK_WEBHOOK_SECRET=...

# Flutterwave
FLUTTERWAVE_PUBLIC_KEY=FLWPUBK_TEST-...
FLUTTERWAVE_SECRET_KEY=FLWSECK_TEST-...

# Stripe
STRIPE_PUBLIC_KEY=pk_test_...
STRIPE_SECRET_KEY=sk_test_...
STRIPE_WEBHOOK_SECRET=...

# BTCPay Server
BTCPAY_API_KEY=...
BTCPAY_STORE_ID=...
BTCPAY_BASE_URL=https://btcpay.yourserver.com
```

## Usage

### Initializing the Handler

Use the `PaymentConfig` helper to create a handler instance:

```php
use Plugs\TransactionHandler\PaymentConfig;

// Uses default platform from .env
$payment = PaymentConfig::create();

// Or specify a platform
$stripe = PaymentConfig::create('stripe');
```

### Fluent Payment API

The fluent interface allows you to build a transaction step-by-step:

```php
$result = $payment->amount(5000)
    ->currency('NGN')
    ->email('user@example.com')
    ->description('Order #1234')
    ->callback('http://app.test/payment/callback')
    ->pay();

if ($result->isSuccessful()) {
    // Redirect user to payment page
    $url = $result->getData()['authorization_url'];
    return redirect($url);
}

return back()->withError($result->getMessage());
```

### Subscriptions

```php
$result = $payment->email('user@example.com')
    ->amount(1500)
    ->with(['plan_code' => 'PLN_monthly_basic'])
    ->subscribe();
```

### Verification

Always verify payments on your callback page:

```php
$reference = $request->query('reference');
$result = $payment->verify($reference);

if ($result->isSuccessful()) {
    // Update order status in database
    $orderId = $result->getData()['metadata']['order_id'];
}
```

## Handling Webhooks

Webhooks allow your application to receive asynchronous updates from payment gateways.

```php
$payload = $request->all();
$result = $payment->handleWebhook($payload);

if ($result->isSuccessful()) {
    $event = $result->getData()['event'];
    $data = $result->getData()['data'];
    
    switch ($event) {
        case 'payment_successful':
            // Handle success
            break;
        case 'subscription_cancelled':
            // Handle cancellation
            break;
    }
}
```

## Processing Standardized Results

All payment operations return a `Plugs\TransactionHandler\TransactionResult` object, providing a consistent API:

| Method | Description |
|--------|-------------|
| `isSuccessful()` | Returns `true` if operation was successful |
| `getStatus()` | Returns the transaction status (success, failed, pending) |
| `getReference()`| Returns the platform-specific reference |
| `getMessage()` | Returns a human-readable message or error |
| `getData()` | Returns the raw array response from the platform |
| `getPlatform()`| Returns the platform used (e.g., 'paystack') |

## Logging

The handler automatically logs transaction events if a logger is available. You can find logs in `storage/logs/plugs.log`.

Events logged include:
- Transaction initiation with details (amount, currency, email)
- Successful transaction results
- Failed attempts with error messages
- Webhook signature verification and processing

## API Reference

### PaymentTransactionHandler Methods

- `amount(float $amount)`: Set transaction amount
- `currency(string $currency)`: Set currency code
- `email(string $email)`: Set customer email
- `reference(string $reference)`: Set manual reference
- `description(string $desc)`: Set transaction description
- `metadata(array $data)`: Set custom metadata
- `callback(string $url)`: Set redirect URL
- `with(array $data)`: Add extra platform-specific parameters
- `pay()`: Process the payment
- `subscribe(array $data)`: Create subscription
- `verify(string $ref)`: Verify transaction
- `refund(array $data)`: Process refund
- `transfer(array $data)`: Build and send transfer
- `withdraw(array $data)`: Build and send withdrawal
- `getBalance()`: Get account balance
- `list(array $filters)`: List recent transactions
