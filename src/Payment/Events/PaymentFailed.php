<?php

declare(strict_types=1);

namespace Plugs\Payment\Events;

use Plugs\Payment\DTO\PaymentVerification;

class PaymentFailed
{
    /**
     * @param string $gateway The gateway that triggered the event (e.g., 'stripe').
     * @param PaymentVerification $payment The normalized payment verification DTO.
     */
    public function __construct(
        public readonly string $gateway,
        public readonly PaymentVerification $payment
    ) {
    }
}
