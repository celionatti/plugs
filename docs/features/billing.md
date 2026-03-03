# Billing & Tax Management

The Plugs Framework provides a robust system for handling transaction fees and tax calculations. This is particularly useful for e-commerce, ticket sales, and any application dealing with payments.

## Configuration

Default billing settings are managed in `src/Config/DefaultConfig.php`. You can override these by creating a `config/billing.php` file in your project root.

```php
return [
    'tax_rate' => 7.5,
    'regional_tax_rates' => [
        'NG' => 7.5,
        'US-NY' => 8.875,
    ],
    'fees' => [
        'paystack' => [
            'waive_fixed_under' => 2500,
            'fixed_fee' => 100,
            'percentage_fee' => 0.015,
            'cap' => 2000,
        ],
    ],
];
```

## Fee Management

The `FeeManager` allows you to calculate gateway-specific transaction fees automatically.

### Usage

```php
use Plugs\Billing\FeeManager;

$feeManager = new FeeManager();

// Calculate Paystack fee for 5000 NGN
$amount = 5000;
$fee = $feeManager->calculate('paystack', $amount);
// Result: 175.0 (1.5% of 5000 + 100 fixed fee)
```

### Paystack Fee Logic

The framework includes a built-in `PaystackFeeCalculator` that follows Paystack's standard rules:

- **Local Transactions (NGN)**: 1.5% + 100 NGN.
- **Waiver**: The 100 NGN fixed fee is waived for transactions below 2500 NGN.
- **Cap**: Total fees are capped at 2000 NGN for local transactions.
- **International Transactions**: 3.9% + 100 NGN.

To calculate international fees:

```php
$fee = $feeManager->calculate('paystack', $amount, 'NGN', ['international' => true]);
```

## Tax Calculation

The `TaxCalculator` provides utilities for simple and region-based tax calculations.

### Simple Calculation

```php
use Plugs\Billing\TaxCalculator;

$taxCalculator = new TaxCalculator();
$subtotal = 1000;
$rate = 7.5; // 7.5%

$taxAmount = $taxCalculator->calculate($subtotal, $rate); // 75.0
$totalWithTax = $taxCalculator->calculateTotal($subtotal, $rate); // 1075.0
```

### Region-Based Calculation

You can define a map of regional rates and let the calculator determine the correct one.

```php
$rates = [
    'NG' => 7.5,
    'US-NY' => 8.875,
    'default' => 5.0
];

$tax = $taxCalculator->calculateForRegion(1000, 'US-NY', $rates); // 88.75
```

## Integration Example: Ticket Sale

Here is how you might use both for a ticket sale application:

```php
$ticketPrice = 10000;
$region = 'NG';

// 1. Calculate Tax
$taxRate = Config::get('billing.regional_tax_rates.' . $region, 0);
$tax = $taxCalculator->calculate($ticketPrice, $taxRate);

$subtotal = $ticketPrice + $tax;

// 2. Calculate Payment Gateway Fee (if passed to customer)
$gatewayFee = $feeManager->calculate('paystack', $subtotal);

$totalToPay = $subtotal + $gatewayFee;
```

## Extending Fee Calculators

You can add your own custom fee calculators by implementing the `FeeCalculatorInterface`.

```php
use Plugs\Billing\Contracts\FeeCalculatorInterface;

class MyCustomCalculator implements FeeCalculatorInterface
{
    public function calculate(float $amount, string $currency = 'NGN', array $options = []): float
    {
        return $amount * 0.01; // 1% flat fee
    }

    public function getName(): string
    {
        return 'my_custom_provider';
    }
}

// Register it with the manager
$feeManager->extend('my_custom', new MyCustomCalculator());
```
