<?php

declare(strict_types=1);

namespace Plugs\Billing;

use InvalidArgumentException;
use Plugs\Billing\Contracts\FeeCalculatorInterface;
use Plugs\Billing\Calculators\PaystackFeeCalculator;

class FeeManager
{
    protected array $calculators = [];
    protected array $config;

    public function __construct(array $config = [])
    {
        $this->config = $config;
        $this->registerDefaultCalculators();
    }

    protected function registerDefaultCalculators(): void
    {
        $this->extend('paystack', new PaystackFeeCalculator());
    }

    public function extend(string $name, FeeCalculatorInterface $calculator): self
    {
        $this->calculators[$name] = $calculator;
        return $this;
    }

    public function calculator(string $name): FeeCalculatorInterface
    {
        if (!isset($this->calculators[$name])) {
            throw new InvalidArgumentException("Fee calculator [$name] not found.");
        }

        return $this->calculators[$name];
    }

    public function calculate(string $provider, float $amount, string $currency = 'NGN', array $options = []): float
    {
        return $this->calculator($provider)->calculate($amount, $currency, $options);
    }
}
