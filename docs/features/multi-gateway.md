# Multi-Gateway & Failover Strategies

The Elite Payment and Payout system is designed to support multiple gateways simultaneously. This allows for high availability, regional optimization, and automated failover.

## 1. Multi-Gateway Configuration

Ensure all your active gateways are configured in your `.env` file. You can have any number of gateways configured at once.

```env
# Primary Gateway
DEFAULT_PAYMENT_PLATFORM=paystack

# Secondary Gateways
PAYSTACK_SECRET_KEY=sk_test_...
STRIPE_SECRET_KEY=sk_test_...
FLUTTERWAVE_SECRET_KEY=FLWSECK_TEST-...
PAYPAL_CLIENT_ID=...
PAYONEER_CLIENT_ID=...
BTCPAY_API_KEY=...
```

## 2. Dynamic Driver Switching

You can switch between drivers at runtime using the `driver()` method on either the `Payment` or `Payout` facade.

```php
use Plugs\Facades\Payment;
use Plugs\Facades\Payout;

// Force a specific driver for a single call
$response = Payment::driver('stripe')->initialize($payload);

// Switch payout driver dynamically
$payout = Payout::driver('paypal')->transfer($payload);
```

## 3. Smart Routing

Smart routing allows you to define custom rules for choosing the best gateway based on transaction data (e.g., currency, amount, customer location).

### Defining Routing Rules

You can define these in a Service Provider or during application bootstrap:

```php
use Plugs\Facades\Payment;

Payment::addRouteRule(function (array $payload) {
    // Route USD transactions to Stripe
    if (($payload['currency'] ?? '') === 'USD') {
        return 'stripe';
    }

    // Route Bitcoin transactions to BTCPay
    if (($payload['currency'] ?? '') === 'BTC') {
        return 'btcpay';
    }

    // Default to Paystack
    return 'paystack';
});
```

### Executing Smart Payments

```php
// Use the 'smart()' method to automatically select based on rules
$response = Payment::smart($payload)->initialize($payload);
```

## 4. Automated Payout Failover

The `FallbackPayoutDriver` is a specialized driver that attempts multiple gateways in sequence until one succeeds. This is ideal for ensuring payouts are delivered even if a primary gateway is experiencing downtime or has insufficient funds.

### Configuration

The `FallbackPayoutDriver` takes an array of driver identifiers:

```php
use Plugs\Payout\Drivers\FallbackPayoutDriver;
use Plugs\Payout\PayoutManager;

// Create the fallback sequence
$fallbackSequence = ['paystack', 'flutterwave', 'stripe'];

// The PayoutManager can resolve this if configured
// Or you can use it manually:
$fallbackDriver = new FallbackPayoutDriver($payoutManager, $fallbackSequence);

$response = $fallbackDriver->transfer($payload);
// It will try Paystack -> then Flutterwave (if Paystack fails) -> then Stripe.
```

## 5. Driver Decorators (Logging & Auditing)

You can wrap any driver with the `LoggingPayoutDriver` to automatically log all API interactions for that specific gateway.

```php
use Plugs\Payout\Drivers\LoggingPayoutDriver;
use Psr\Log\LoggerInterface;

$baseDriver = Payout::driver('stripe');
$loggingDriver = new LoggingPayoutDriver($baseDriver, $logger);

// All calls made through $loggingDriver will be audited
$loggingDriver->transfer($payload);
```

---

## Best Practices

1. **Keep a Primary & Secondary**: Always have at least two gateways configured for critical regions.
2. **Regional Routing**: Use Smart Routing to avoid high cross-border transaction fees (e.g., use Paystack for NGN and Stripe for USD).
3. **Handle Exceptions**: Wrap multi-gateway calls in `try/catch` using `GatewayException` to handle cases where all gateways in a fallback sequence might fail.
