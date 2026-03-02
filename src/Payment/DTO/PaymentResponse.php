<?php

declare(strict_types=1);

namespace Plugs\Payment\DTO;

class PaymentResponse
{
    /**
     * @param string $reference The generated payment reference.
     * @param string $authorization_url The URL to redirect the user to for payment.
     * @param string $status The initialization status.
     * @param float $amount The transaction amount.
     * @param string $currency The transaction currency.
     * @param string|null $message An optional message from the gateway.
     * @param array $metadata Any additional metadata returned.
     */
    public function __construct(
        public readonly string $reference,
        public readonly string $authorization_url,
        public readonly string $status,
        public readonly float $amount,
        public readonly string $currency,
        public readonly ?string $message = null,
        public readonly array $metadata = []
    ) {
    }
}
