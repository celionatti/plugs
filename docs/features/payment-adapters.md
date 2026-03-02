# Payment Gateway Adapter Guides

This guide provides specific examples and payloads for using each supported payment gateway in the Plugs framework.

## 1. Paystack (`paystack`)

Paystack is the default driver for many African markets, supporting local cards, bank transfers, and mobile money.

### Payment Initialization

```php
use Plugs\Facades\Payment;

$response = Payment::driver('paystack')->initialize([
    'amount' => 500000, // Amount in kobo (5000.00 NGN)
    'currency' => 'NGN',
    'email' => 'customer@email.com',
    'metadata' => [
        'order_id' => 'PLG-1234',
        'custom_field' => 'extra info'
    ],
    'callback_url' => route('payment.callback')
]);

return redirect($response->getAuthorizationUrl());
```

### Payout (Transfer)

```php
use Plugs\Facades\Payout;

// 1. Create Recipient
$recipient = Payout::driver('paystack')->createRecipient([
    'type' => 'nuban',
    'name' => 'John Doe',
    'account_number' => '0000000000',
    'bank_code' => '058', // GTBank
    'currency' => 'NGN'
]);

// 2. Transer
$transfer = Payout::driver('paystack')->transfer([
    'amount' => 100000,
    'recipient' => $recipient['recipient_code'],
    'reason' => 'Service payment'
]);
```

---

## 2. Stripe (`stripe`)

Stripe uses Payment Intents for robust global payments.

### Payment Initialization

```php
$response = Payment::driver('stripe')->initialize([
    'amount' => 1000, // 10.00 USD
    'currency' => 'USD',
    'description' => 'E-book purchase',
    'email' => 'user@example.com'
]);

// $response->getAuthorizationUrl() will return the client_secret
// for use in your frontend Stripe JS elements.
```

### Payouts (Connected Accounts)

Stripe payouts in this framework are designed for Connect:

```php
$response = Payout::driver('stripe')->transfer([
    'amount' => 500,
    'currency' => 'USD',
    'recipient' => 'acct_123456789', // Connected account ID
    'reason' => 'Platform fee payout'
]);
```

---

## 3. Flutterwave (`flutterwave`)

### Payment Initialization

```php
$response = Payment::driver('flutterwave')->initialize([
    'amount' => 2500,
    'currency' => 'KES',
    'email' => 'customer@example.ke',
    'tx_ref' => 'unique_ref_001',
    'payment_options' => 'card,mpesa,ussd'
]);
```

---

## 4. BTCPay Server (`btcpay`)

BTCPay handles on-chain and lightning network Bitcoin payments.

### Payment Initialization

```php
$response = Payment::driver('btcpay')->initialize([
    'amount' => '0.001', // BTC amount
    'currency' => 'BTC',
    'description' => 'Bitcoin Donation',
    'email' => 'donor@btc.test',
    'speed_policy' => 'HighSpeed' // or MediumSpeed, LowSpeed
]);

return redirect($response->getAuthorizationUrl()); // Redirects to BTCPay Invoice
```

### Payout (On-chain)

```php
$response = Payout::driver('btcpay')->withdraw([
    'amount' => '0.0005',
    'address' => 'bc1q...', // Destination BTC address
    'crypto_currency' => 'BTC',
    'narration' => 'Wallet withdrawal'
]);
```

---

## Webhook Signature Reference

| Gateway     | Header to Check        | Hashing Algorithm   |
| ----------- | ---------------------- | ------------------- |
| Paystack    | `X-Paystack-Signature` | HMAC SHA512         |
| Stripe      | `Stripe-Signature`     | HMAC SHA256         |
| Flutterwave | `Verif-Hash`           | Plain Secret Match  |
| BTCPay      | `Btcpay-Sig`           | HMAC SHA256         |
| PayPal      | _Varies_               | OAuth2 Verification |
| Payoneer    | _Varies_               | API Key / Token     |

---

## 5. PayPal (`paypal`) & Payoneer (`payoneer`)

These drivers are currently available as stubs in the `Plugs\Payment\Drivers` and `Plugs\Payout\Drivers` namespaces. They are ready for full API integration based on your specific requirements.

### PayPal Integration Note

The `Payment::initialize()` method is designed to return the approval URL for the PayPal checkout flow.

### Payoneer Integration Note

Payoneer integrations usually revolve around mass payouts. Use the `Payout::transfer()` method to initiate payouts to registered Payoneer accounts.

> [!IMPORTANT]
> Always use `Payment::verifyWebhookSignature($request)` which automatically handles the correct header and hashing for your active driver.
