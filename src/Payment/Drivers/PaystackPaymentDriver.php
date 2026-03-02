<?php

declare(strict_types=1);

namespace Plugs\Payment\Drivers;

use Exception;
use Plugs\Http\Request;
use Plugs\Payment\Contracts\PaymentDriverInterface;
use Plugs\Payment\DTO\PaymentResponse;
use Plugs\Payment\DTO\PaymentVerification;
use Plugs\Payment\DTO\RefundResponse;

class PaystackPaymentDriver implements PaymentDriverInterface
{
    private string $secretKey;
    private string $baseUrl = 'https://api.paystack.co';

    /**
     * @param array $config
     */
    public function __construct(array $config)
    {
        $this->secretKey = $config['secret_key'] ?? '';
    }

    /**
     * Initialize a new payment transaction.
     *
     * @param array $payload
     * @return PaymentResponse
     */
    public function initialize(array $payload): PaymentResponse
    {
        $response = $this->makeRequest('/transaction/initialize', $payload);

        return new PaymentResponse(
            $response['reference'] ?? $payload['reference'] ?? '',
            $response['authorization_url'] ?? '',
            'pending', // Paystack initial state is usually pending
            (float) ($payload['amount'] ?? 0),
            $payload['currency'] ?? 'NGN',
            'Transaction initialized',
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
        $response = $this->makeRequest("/transaction/verify/{$reference}", [], 'GET');

        // Paystack returns 'success', 'failed', 'abandoned', etc.
        $status = $response['status'] ?? 'failed';

        return new PaymentVerification(
            $response['reference'] ?? $reference,
            $status,
            (float) ($response['amount'] ?? 0),
            $response['currency'] ?? 'NGN',
            $response['gateway_response'] ?? 'Verification successful',
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
        $payload = [
            'transaction' => $reference,
            'amount' => $amount,
        ];

        if ($reason) {
            $payload['customer_note'] = $reason;
        }

        $response = $this->makeRequest('/refund', $payload);

        return new RefundResponse(
            $response['transaction']['reference'] ?? $reference,
            $response['status'] ?? 'pending',
            (float) ($response['amount'] ?? $amount),
            $response['currency'] ?? 'NGN',
            $response['message'] ?? 'Refund initiated',
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
        // Event processing logic will be handled by WebhookRouter
        // This method can do driver-specific pre-processing if necessary.
    }

    /**
     * Verify the incoming webhook signature cryptographically.
     *
     * @param Request $request
     * @return bool
     */
    public function verifyWebhookSignature(Request $request): bool
    {
        $signature = $request->getHeaderLine('X-Paystack-Signature');
        if (!$signature && isset($_SERVER['HTTP_X_PAYSTACK_SIGNATURE'])) {
            $signature = $_SERVER['HTTP_X_PAYSTACK_SIGNATURE'];
        }

        $payloadRaw = (string) $request->getBody();
        if (empty($payloadRaw)) {
            $payloadRaw = file_get_contents('php://input');
        }

        $hash = hash_hmac('sha512', (string) $payloadRaw, $this->secretKey);

        return hash_equals($hash, (string) $signature);
    }

    /**
     * Helper to make API requests to Paystack.
     *
     * @param string $endpoint
     * @param array $data
     * @param string $method
     * @return array
     * @throws Exception
     */
    private function makeRequest(string $endpoint, array $data = [], string $method = 'POST')
    {
        $ch = curl_init($this->baseUrl . $endpoint);

        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            'Authorization: Bearer ' . $this->secretKey,
            'Content-Type: application/json',
        ]);

        if ($method === 'POST') {
            curl_setopt($ch, CURLOPT_POST, true);
            curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
        }

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        $result = json_decode((string) $response, true) ?? [];

        if ($httpCode !== 200 && $httpCode !== 201 && $httpCode !== 202) {
            throw new Exception($result['message'] ?? 'Paystack Request failed: HTTP ' . $httpCode);
        }

        return $result['data'] ?? $result;
    }
}
