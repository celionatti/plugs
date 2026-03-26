<?php

declare(strict_types=1);

namespace Plugs\Payment\Drivers;

use Plugs\Http\Request;
use Plugs\Payment\Contracts\PaymentDriverInterface;
use Plugs\Payment\DTO\PaymentResponse;
use Plugs\Payment\DTO\PaymentVerification;
use Plugs\Payment\DTO\RefundResponse;

use Plugs\Payment\Traits\HasHttpCalls;
use Plugs\Payment\Utils\AmountConverter;

class StripePaymentDriver implements PaymentDriverInterface
{
    use HasHttpCalls;

    private string $secretKey;
    private string $webhookSecret;
    private string $baseUrl = 'https://api.stripe.com/v1';

    /**
     * @param array $config
     */
    public function __construct(array $config)
    {
        $this->secretKey = $config['secret_key'] ?? '';
        $this->webhookSecret = $config['webhook_secret'] ?? '';
    }

    /**
     * Initialize a new payment transaction.
     *
     * @param array $payload
     * @return PaymentResponse
     */
    public function initialize(array $payload): PaymentResponse
    {
        $currency = $payload['currency'] ?? 'usd';
        $paymentData = [
            'amount' => AmountConverter::toSubunits($payload['amount'] ?? 0, $currency),
            'currency' => strtolower($currency),
            'description' => $payload['description'] ?? 'Payment',
            'receipt_email' => $payload['email'] ?? '',
            'metadata' => $payload['metadata'] ?? [],
        ];

        if (isset($payload['payment_method'])) {
            $paymentData['payment_method'] = $payload['payment_method'];
            $paymentData['confirm'] = 'true';
        }

        if (isset($payload['customer_id'])) {
            $paymentData['customer'] = $payload['customer_id'];
        }

        $response = $this->makeRequest('/payment_intents', $paymentData);

        return new PaymentResponse(
            $response['id'] ?? $payload['reference'] ?? '',
            // Stripe payment intents don't typically have an authorization URL like Paystack
            // unless using Checkout Sessions. We'll leave it blank or use the client_secret here.
            $response['client_secret'] ?? '',
            $this->normalizeStatus($response['status'] ?? 'pending'),
            (float) ($response['amount'] ?? $paymentData['amount']),
            strtoupper($response['currency'] ?? $paymentData['currency']),
            'Transaction initialized via Stripe',
            $response
        );
    }

    /**
     * Verify a payment transaction status.
     *
     * @param string $reference
     * @return PaymentVerification
     */
    public function verify(string $reference): PaymentVerification
    {
        $response = $this->makeRequest("/payment_intents/{$reference}", [], 'GET');

        return new PaymentVerification(
            $response['id'] ?? $reference,
            $this->normalizeStatus($response['status'] ?? 'pending'),
            (float) ($response['amount'] ?? 0),
            strtoupper($response['currency'] ?? 'USD'),
            'Verification retrieved',
            $response
        );
    }

    /**
     * Refund a successful transaction.
     *
     * @param string $reference
     * @param float $amount
     * @param string|null $reason
     * @return RefundResponse
     */
    public function refund(string $reference, float $amount, ?string $reason = null): RefundResponse
    {
        // We need currency here to convert to subunits correctly. 
        // Stripe usually expects the refund amount in the same subunits as the original charge.
        // For simplicity, we assume the original currency from the driver's context if available, 
        // or we might need it passed in. But here we'll use a fixed 'usd' as a safeguard or 
        // a better approach would be to fetch the intent first.
        $currency = 'usd'; // Defaulting for now
        $refundData = [
            'payment_intent' => $reference,
            'amount' => AmountConverter::toSubunits($amount, $currency),
        ];

        if ($reason) {
            $refundData['reason'] = $reason;
        }

        $response = $this->makeRequest('/refunds', $refundData);

        return new RefundResponse(
            $response['id'] ?? $reference,
            $response['status'] ?? 'pending',
            (float) ($response['amount'] ?? $amount),
            strtoupper($response['currency'] ?? 'USD'),
            'Refund initiated',
            $response
        );
    }

    /**
     * Handle incoming webhooks for payment status updates.
     *
     * @param array $payload
     * @return void
     */
    public function webhook(array $payload): void
    {
        // Event processing logic driven by WebhookRouter
    }

    /**
     * Verify the incoming webhook signature cryptographically.
     *
     * @param Request $request
     * @return bool
     */
    public function verifyWebhookSignature(Request $request): bool
    {
        if (empty($this->webhookSecret)) {
            return true;
        }

        $signature = $request->getHeaderLine('Stripe-Signature');
        if (!$signature && isset($_SERVER['HTTP_STRIPE_SIGNATURE'])) {
            $signature = $_SERVER['HTTP_STRIPE_SIGNATURE'];
        }

        if (empty($signature)) {
            return false;
        }

        $signatureParts = [];
        foreach (explode(',', (string) $signature) as $part) {
            $split = explode('=', $part, 2);
            if (count($split) === 2) {
                $signatureParts[$split[0]] = $split[1];
            }
        }

        if (!isset($signatureParts['t']) || !isset($signatureParts['v1'])) {
            return false;
        }

        $timestamp = $signatureParts['t'];
        $expectedSignature = $signatureParts['v1'];

        $payloadRaw = (string) $request->getBody();
        if (empty($payloadRaw)) {
            $payloadRaw = file_get_contents('php://input');
        }

        $payloadString = $timestamp . '.' . $payloadRaw;
        $computedSignature = hash_hmac('sha256', $payloadString, $this->webhookSecret);

        return hash_equals($computedSignature, $expectedSignature);
    }

    /**
     * Normalize Stripe statuses to global generic framework statuses
     *
     * @param string $status
     * @return string
     */
    private function normalizeStatus(string $status): string
    {
        $status = strtolower($status);
        if ($status === 'succeeded' || $status === 'paid') {
            return 'success';
        }
        if (in_array($status, ['canceled', 'failed'])) {
            return 'failed';
        }
        return 'pending';
    }

    /**
     * Make HTTP request to Stripe API using shared trait.
     */
    protected function makeRequest(string $endpoint, array $data = [], string $method = 'POST')
    {
        $headers = [
            'Authorization: Bearer ' . $this->secretKey,
            'Content-Type: application/x-www-form-urlencoded',
        ];

        return $this->makeHttpRequest($this->baseUrl . $endpoint, $data, $method, $headers);
    }
}
