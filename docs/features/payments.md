# Elite Payment Module

The Elite Payment Module provides a standardized, unified interface for handling "Pay-In" operations across multiple gateways like Paystack, Stripe, Flutterwave, and BTCPay Server. It uses a driver-based architecture that returns standardized Data Transfer Objects (DTOs) and handles webhooks with modern PSR-7 request objects.

## Installation & Configuration

Configuration is managed via the `payments` config. Ensure your drivers are configured with the necessary API keys and secrets.

### Environment Variables

Add your credentials to `.env`:

```env
# Default Settings
DEFAULT_PAYMENT_PLATFORM=paystack

# Paystack
PAYSTACK_SECRET_KEY=sk_test_...
PAYSTACK_WEBHOOK_SECRET=...

# Stripe
STRIPE_SECRET_KEY=sk_test_...
STRIPE_WEBHOOK_SECRET=...

# Flutterwave
FLUTTERWAVE_SECRET_KEY=FLWSECK_TEST-...

# BTCPay Server
BTCPAY_API_KEY=...
BTCPAY_STORE_ID=...
BTCPAY_BASE_URL=https://btcpay.yourserver.com
BTCPAY_WEBHOOK_SECRET=...
```

## Elite Payment API

Instead of passing raw arrays and getting back unstructured data, the Elite API uses the `Payment` facade and DTOs.

### Basic Usage

```php
use Plugs\Facades\Payment;

// Initialize a payment
$response = Payment::initialize([
    'amount' => 5000,
    'currency' => 'NGN',
    'email' => 'user@example.com',
    'reference' => 'unique-ref-123',
    'callback_url' => 'https://app.test/checkout/callback'
]);

// Standardized DTO methods
echo $response->getReference();
echo $response->getAuthorizationUrl();
echo $response->getStatus(); // 'pending', 'success', 'failed'
```

### Verifying Payments

Standardized verification regardless of the gateway used:

```php
$verification = Payment::verify($reference);

if ($verification->isSuccessful()) {
    // Transaction successful
    $amount = $verification->getAmount();
    $metadata = $verification->getMetadata();
}
```

### Handling Refunds

```php
$refund = Payment::refund($reference, 1000.0, 'Partial refund for item return');

if ($refund->isSuccessful()) {
    echo "Refunded: " . $refund->getAmount();
}
```

## Supported Drivers

The framework includes several native drivers out of the box. You can switch drivers fluently:

```php
// Use Stripe specifically
Payment::driver('stripe')->initialize([...]);

// Default driver (from config)
Payment::initialize([...]);
```

| Driver      | Identifier    | Notes                                              |
| ----------- | ------------- | -------------------------------------------------- |
| Paystack    | `paystack`    | High-fidelity implementation for Nigerian markets. |
| Stripe      | `stripe`      | Global powerhouse, uses PaymentIntents.            |
| Flutterwave | `flutterwave` | African & global payments support.                 |
| BTCPay      | `btcpay`      | Self-hosted crypto payment processor.              |

## Advanced: Smart Routing

You can define rules to automatically choose a gateway based on the payload:

```php
Payment::addRouteRule(function (array $payload) {
    if ($payload['currency'] === 'USD') {
        return 'stripe';
    }
    return 'paystack';
});

// Automatically chooses based on currency
Payment::smart($payload)->initialize($payload);
```

For more advanced routing and failover strategies, see the [Multi-Gateway Guide](file:///c:/xampp/htdocs/plugs/docs/features/multi-gateway.md).

## Webhook Handling

Elite drivers implement secure, modern webhook signature verification using PSR-7 `Request` objects.

```php
public function handleWebhook(\Plugs\Http\Request $request)
{
    // Secure cryptographic verification
    if (!Payment::verifyWebhookSignature($request)) {
        return response('Invalid signature', 400);
    }

    $payload = $request->all();

    // Process the event
    Payment::webhook($payload);

    return response('Webhook processed');
}
```

## Standardized DTOs

Every operation returns a DTO from the `Plugs\Payment\DTO` namespace:

| DTO                   | Purpose                  | Key Methods                                              |
| --------------------- | ------------------------ | -------------------------------------------------------- |
| `PaymentResponse`     | Result of `initialize()` | `getReference()`, `getAuthorizationUrl()`, `getStatus()` |
| `PaymentVerification` | Result of `verify()`     | `isSuccessful()`, `getAmount()`, `getCurrency()`         |
| `RefundResponse`      | Result of `refund()`     | `isSuccessful()`, `getStatus()`, `getAmount()`           |

---

> [!TIP]
> **Legacy Support**: If you are maintaining older code, the `PaymentTransactionHandler` still works but now acts as a bridge to this Elite Engine. We recommend migrating to the `Payment` facade for new features.
