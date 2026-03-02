<?php

declare(strict_types=1);

namespace Plugs\Payment\Contracts;

use Plugs\Payment\DTO\PaymentResponse;
use Plugs\Payment\DTO\PaymentVerification;
use Plugs\Payment\DTO\RefundResponse;

interface PaymentDriverInterface
{
    /**
     * Initialize a payment transaction.
     *
     * @param array $payload The payment payload.
     * @return PaymentResponse
     */
    public function initialize(array $payload): PaymentResponse;

    /**
     * Verify a payment transaction via its reference.
     *
     * @param string $reference The transaction reference.
     * @return PaymentVerification
     */
    public function verify(string $reference): PaymentVerification;

    /**
     * Issue a refund for a specific transaction.
     *
     * @param string $reference The transaction reference.
     * @param float $amount The amount to refund.
     * @return RefundResponse
     */
    public function refund(string $reference, float $amount): RefundResponse;

    /**
     * Handle payment webhook payloads.
     *
     * @param array $payload The webhook payload data.
     * @return void
     */
    public function webhook(array $payload): void;

    /**
     * Verify the authenticity of a webhook payload via signature headers.
     *
     * @param \Plugs\Http\Request $request
     * @return bool
     */
    public function verifyWebhookSignature(\Plugs\Http\Request $request): bool;
}
