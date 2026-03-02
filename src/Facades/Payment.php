<?php

declare(strict_types=1);

namespace Plugs\Facades;

use Plugs\Facade;
use Plugs\Payment\Contracts\PaymentDriverInterface;
use Plugs\Payment\PaymentManager;

/**
 * @method static PaymentDriverInterface driver(string|array $driver = null)
 * @method static PaymentDriverInterface fallback(array $drivers)
 * @method static PaymentDriverInterface smart(array $payload = [])
 * @method static \Plugs\Payment\PaymentManager addRouteRule(callable $rule)
 * @method static \Plugs\Payment\DTO\PaymentResponse initialize(array $payload)
 * @method static \Plugs\Payment\DTO\PaymentVerification verify(string $reference)
 * @method static \Plugs\Payment\DTO\RefundResponse refund(string $reference, float $amount)
 * @method static void webhook(array $payload)
 * 
 * @see \Plugs\Payment\PaymentManager
 */
class Payment extends Facade
{
    /**
     * Get the registered name of the component.
     *
     * @return string
     */
    protected static function getFacadeAccessor(): string
    {
        return PaymentManager::class;
    }
}
