<?php

declare(strict_types=1);

namespace Plugs\Billing\Calculators;

use Plugs\Billing\Contracts\FeeCalculatorInterface;

class PaystackFeeCalculator implements FeeCalculatorInterface
{
    /**
     * Paystack Fee Logic:
     * - Local (NGN): 1.5% + 100 NGN
     * - 100 NGN fee is waived for transactions below 2500 NGN.
     * - Fees are capped at 2000 NGN.
     * - International: 3.9% + 100 NGN (usually varies, but this is a standard baseline for many setups)
     * 
     * Note: This implementation focuses on the NGN logic as a primary example.
     */
    public function calculate(float $amount, string $currency = 'NGN', array $options = []): float
    {
        $currency = strtoupper($currency);
        $isInternational = $options['international'] ?? false;

        if ($currency !== 'NGN') {
            // For non-NGN, we might use different logic or international rates
            return $this->calculateInternational($amount, $currency);
        }

        if ($isInternational) {
            return $this->calculateInternational($amount, $currency);
        }

        $percentageFee = $amount * 0.015;
        $fixedFee = ($amount < 2500) ? 0 : 100;

        $totalFee = $percentageFee + $fixedFee;

        // Cap at 2000 NGN
        return min($totalFee, 2000.0);
    }

    protected function calculateInternational(float $amount, string $currency): float
    {
        // Example international rate: 3.9% + equivalent of 100 NGN fixed fee
        $percentageFee = $amount * 0.039;
        $fixedFee = 100; // Simplified fixed fee for international

        return $percentageFee + $fixedFee;
    }

    public function getName(): string
    {
        return 'paystack';
    }
}
