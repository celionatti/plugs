<?php

declare(strict_types=1);

namespace Plugs\Payment\Events;

class WebhookHandled
{
    /**
     * @param string $gateway The gateway that triggered the event (e.g., 'stripe').
     * @param array $payload The raw webhook payload data.
     */
    public function __construct(
        public readonly string $gateway,
        public readonly array $payload
    ) {
    }
}
