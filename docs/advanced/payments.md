# Payments & Billing

The Plugs **Elite Payment Layer** provides a highly extensible system for handling transactions, subscriptions, and payouts across multiple gateways (Stripe, PayPal, Flutterwave, etc.).

---

## 1. Multi-Gateway Architecture

Plugs uses an **Adapter Pattern**, allowing you to switch payment providers without changing your business logic.

```php
use Plugs\Payment\Gateway;

$gateway = Gateway::driver('stripe');
$response = $gateway->charge($amount, $token);
```

### Supported Adapters
- **Stripe**: Subscriptions, One-time charges, Connect.
- **PayPal**: Checkout, Recurring payments.
- **Flutterwave**: Mobile money, Cards.
- **Paystack**: African payments.

---

## 2. Subscriptions & Payouts

- **Billing**: Manage customer invoices, tax calculation, and discounts.
- **Payouts**: Automate vendor payouts and commission splits for marketplaces.

---

## 3. Webhooks

Plugs automatically handles payment webhooks, ensuring your application stays in sync with the gateway's state (e.g., successful payment, subscription cancelled).

```php
Route::post('/webhooks/stripe', 'PaymentController@handleWebhook');
```

---

## Next Steps
Optimize your application's [Performance & Caching](./performance.md).
