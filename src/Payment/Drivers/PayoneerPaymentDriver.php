<?php

declare(strict_types=1);

namespace Plugs\Payment\Drivers;

use Plugs\Http\Request;
use Plugs\Payment\Contracts\PaymentDriverInterface;
use Plugs\Payment\DTO\PaymentResponse;
use Plugs\Payment\DTO\PaymentVerification;
use Plugs\Payment\DTO\RefundResponse;

class PayoneerPaymentDriver implements PaymentDriverInterface
{
    public function __construct(array $config = [])
    {
    }

    public function initialize(array $payload): PaymentResponse
    {
        return new PaymentResponse('', '', 'pending', 0, 'USD', 'Stubbed', []);
    }

    public function verify(string $reference): PaymentVerification
    {
        return new PaymentVerification($reference, 'pending', 0, 'USD', 'Stubbed', []);
    }

    public function refund(string $reference, float $amount, ?string $reason = null): RefundResponse
    {
        return new RefundResponse($reference, 'pending', $amount, 'USD', 'Stubbed', []);
    }

    public function webhook(array $payload): void
    {
    }

    public function verifyWebhookSignature(Request $request): bool
    {
        return true;
    }
}
