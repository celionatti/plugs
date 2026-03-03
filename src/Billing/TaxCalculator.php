<?php

declare(strict_types=1);

namespace Plugs\Billing;

class TaxCalculator
{
    /**
     * Calculate tax amount.
     *
     * @param float $subtotal
     * @param float $rate (Percentage, e.g., 7.5 for 7.5%)
     * @return float
     */
    public function calculate(float $subtotal, float $rate): float
    {
        return round($subtotal * ($rate / 100), 2);
    }

    /**
     * Calculate total including tax.
     *
     * @param float $subtotal
     * @param float $rate
     * @return float
     */
    public function calculateTotal(float $subtotal, float $rate): float
    {
        return $subtotal + $this->calculate($subtotal, $rate);
    }

    /**
     * Calculate tax for a specific region based on rules.
     *
     * @param float $subtotal
     * @param string $region
     * @param array $regionRates Mapping of region to rate
     * @return float
     */
    public function calculateForRegion(float $subtotal, string $region, array $regionRates): float
    {
        $rate = $regionRates[$region] ?? $regionRates['default'] ?? 0;
        return $this->calculate($subtotal, (float) $rate);
    }
}
