<?php

declare(strict_types=1);

namespace Plugs\Payment\DTO;

class PaymentVerification
{
    /**
     * @param string $reference The transaction reference.
     * @param string $status The actual status from the gateway (e.g., 'successful', 'failed', 'pending').
     * @param float $amount The amount paid.
     * @param string $currency The currency of the payment (e.g., 'USD', 'NGN').
     * @param string|null $message An optional message from the gateway.
     * @param array $metadata Any additional verification metadata.
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
