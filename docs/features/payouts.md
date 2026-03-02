# Elite Payout Module

The Elite Payout Module (or "Payments Out") provides a specialized interface for handling fund transfers, withdrawals, and balance management. It is architecturally separated from the Payment module to ensure a clean separation between money coming in and money going out.

## Configuration

Payout configuration is managed via the `payouts` config.

### Environment Variables

```env
# Default Payout Platform
DEFAULT_PAYOUT_PLATFORM=paystack

# Paystack Payouts
PAYSTACK_SECRET_KEY=sk_test_...

# Stripe Payouts (Requires Connected Accounts)
STRIPE_SECRET_KEY=sk_test_...

# Flutterwave Payouts
FLUTTERWAVE_SECRET_KEY=FLWSECK_TEST-...

# BTCPay Payouts
BTCPAY_API_KEY=...
BTCPAY_STORE_ID=...
```

## Elite Payout API

The `Payout` facade provides a consistent interface for all payout-related operations.

### Transferring Funds

Transfers are typically used for moving money to an external bank account or provider wallet.

```php
use Plugs\Facades\Payout;

$response = Payout::transfer([
    'amount' => 10000,
    'currency' => 'NGN',
    'recipient' => 'RCP_recipient_code',
    'reason' => 'Monthly Commission',
    'reference' => 'trans-unique-001'
]);

if ($response->isSuccessful()) {
    echo "Transfer Status: " . $response->getStatus();
}
```

### Withdrawals

Withdrawals represent the final stage of moving funds from the platform to the gateway/bank.

```php
$response = Payout::withdraw([
    'amount' => 5000,
    'account_number' => '0123456789',
    'bank_code' => '058',
    'narration' => 'Wallet Withdrawal'
]);
```

### Checking Balances

Retrieve the current balance of your gateway account:

```php
$balances = Payout::getBalance();

// Returns an array of balances (often keyed by currency)
print_r($balances);
```

### Recipient Management

Before transferring funds (specifically on Paystack/Flutterwave), you may need to create a recipient.

```php
$recipient = Payout::createRecipient([
    'type' => 'nuban',
    'name' => 'John Doe',
    'account_number' => '0000000000',
    'bank_code' => '011',
    'currency' => 'NGN'
]);

$recipientCode = $recipient['recipient_code'];
```

## Standardized DTOs

| DTO                  | Purpose                | Key Methods                                    |
| -------------------- | ---------------------- | ---------------------------------------------- |
| `TransferResponse`   | Result of `transfer()` | `getReference()`, `getStatus()`, `getAmount()` |
| `WithdrawResponse`   | Result of `withdraw()` | `getReference()`, `getStatus()`, `getAmount()` |
| `PayoutVerification` | Result of `verify()`   | `isSuccessful()`, `getStatus()`, `getAmount()` |

## Supported Drivers

| Driver      | Identifier    | Notes                                                                 |
| ----------- | ------------- | --------------------------------------------------------------------- |
| Paystack    | `paystack`    | Supports Transfers and Withdrawals via recipients.                    |
| Stripe      | `stripe`      | Handles transfers to connected accounts and payouts to bank accounts. |
| Flutterwave | `flutterwave` | Robust payout support across multiple regions.                        |
| BTCPay      | `btcpay`      | Supports on-chain crypto payouts.                                     |

## Advanced: Smart Payout Routing

Just like payments, payouts support smart routing:

```php
Payout::addRouteRule(function (array $payload) {
    if ($payload['currency'] === 'BTC') {
        return 'btcpay';
    }
    return 'paystack';
});

Payout::smart($payload)->transfer($payload);
```
