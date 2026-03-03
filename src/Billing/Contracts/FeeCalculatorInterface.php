<?php

declare(strict_types=1);

namespace Plugs\Billing\Contracts;

interface FeeCalculatorInterface
{
    /**
     * Calculate the fee for a given amount.
     *
     * @param float $amount
     * @param string $currency
     * @param array $options
     * @return float
     */
    public function calculate(float $amount, string $currency = 'NGN', array $options = []): float;

    /**
     * Get the name of the provider.
     *
     * @return string
     */
    public function getName(): string;
}
