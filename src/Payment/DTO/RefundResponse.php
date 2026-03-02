<?php

declare(strict_types=1);

namespace Plugs\Payment\DTO;

class RefundResponse
{
    /**
     * @param string $reference The transaction reference.
     * @param string $status The refund status (e.g., 'processed', 'failed').
     * @param float $amount The amount refunded.
     * @param string $currency The currency of the refunded payment.
     * @param string|null $message An optional message returned by the gateway.
     * @param array $metadata Any additional refund metadata.
     */
    public function __construct(
        public readonly string $reference,
        public readonly string $status,
        public readonly float $amount,
        public readonly string $currency,
        public readonly ?string $message = null,
        public readonly array $metadata = []
    ) {
    }
}
