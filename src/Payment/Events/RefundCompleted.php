<?php

declare(strict_types=1);

namespace Plugs\Payment\Events;

use Plugs\Payment\DTO\RefundResponse;

class RefundCompleted
{
    /**
     * @param string $gateway The gateway that triggered the event (e.g., 'stripe').
     * @param RefundResponse $refund The normalized refund DTO.
     */
    public function __construct(
        public readonly string $gateway,
        public readonly RefundResponse $refund
    ) {
    }
}
