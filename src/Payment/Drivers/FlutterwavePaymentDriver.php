<?php

declare(strict_types=1);

namespace Plugs\Payment\Drivers;

use Exception;
use Plugs\Http\Request;
use Plugs\Payment\Contracts\PaymentDriverInterface;
use Plugs\Payment\DTO\PaymentResponse;
use Plugs\Payment\DTO\PaymentVerification;
use Plugs\Payment\DTO\RefundResponse;

class FlutterwavePaymentDriver implements PaymentDriverInterface
{
    private string $secretKey;
    private string $baseUrl = 'https://api.flutterwave.com/v3';

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
        $paymentData = [
            'tx_ref' => $payload['reference'] ?? $this->generateReference(),
            'amount' => $payload['amount'] ?? 0,
            'currency' => $payload['currency'] ?? 'NGN',
            'redirect_url' => $payload['callback_url'] ?? '',
            'customer' => [
                'email' => $payload['email'] ?? '',
                'name' => $payload['customer_name'] ?? '',
                'phonenumber' => $payload['phone'] ?? '',
            ],
            'customizations' => [
                'title' => $payload['title'] ?? 'Payment',
                'description' => $payload['description'] ?? 'Payment',
                'logo' => $payload['logo'] ?? '',
            ],
            'meta' => $payload['metadata'] ?? [],
        ];

        $response = $this->makeRequest('/payments', $paymentData);

        return new PaymentResponse(
            $response['tx_ref'] ?? $paymentData['tx_ref'],
            $response['link'] ?? '',
            'pending', // Flutterwave returns a link for the customer to pay
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
        $response = $this->makeRequest("/transactions/verify_by_reference?tx_ref={$reference}", [], 'GET');

        return new PaymentVerification(
            $response['tx_ref'] ?? $reference,
            $this->normalizeStatus($response['status'] ?? 'failed'),
            (float) ($response['amount'] ?? 0),
            $response['currency'] ?? 'NGN',
            $response['processor_response'] ?? 'Verification successful',
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
        // First we need the transaction ID from the reference
        $verification = $this->verify($reference);
        $transactionId = $verification->metadata['id'] ?? $reference;

        $payload = ['amount' => $amount];
        if ($reason) {
            $payload['comments'] = $reason;
        }

        $response = $this->makeRequest("/transactions/{$transactionId}/refund", $payload);

        return new RefundResponse(
            $reference,
            $response['status'] ?? 'pending',
            (float) ($response['amount_refunded'] ?? $amount),
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
        // Handled by WebhookRouter
    }

    /**
     * Verify the incoming webhook signature cryptographically.
     *
     * @param Request $request
     * @return bool
     */
    public function verifyWebhookSignature(Request $request): bool
    {
        $signature = $request->getHeaderLine('Verif-Hash');
        if (!$signature && isset($_SERVER['HTTP_VERIF_HASH'])) {
            $signature = $_SERVER['HTTP_VERIF_HASH'];
        }

        if (!$signature) {
            return false;
        }

        return hash_equals($this->secretKey, (string) $signature);
    }

    /**
     * Normalize Flutterwave status.
     *
     * @param string $status
     * @return string
     */
    private function normalizeStatus(string $status): string
    {
        $status = strtolower($status);
        if ($status === 'successful' || $status === 'success') {
            return 'success';
        }
        if ($status === 'failed' || $status === 'error') {
            return 'failed';
        }
        return 'pending';
    }

    /**
     * Make HTTP request.
     *
     * @param string $endpoint
     * @param array $data
     * @param string $method
     * @return array
     * @throws Exception
     */
    private function makeRequest(string $endpoint, array $data = [], string $method = 'POST')
    {
        $url = $this->baseUrl . $endpoint;
        $ch = curl_init($url);

        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            'Authorization: Bearer ' . $this->secretKey,
            'Content-Type: application/json',
        ]);

        if ($method === 'POST' || $method === 'PUT') {
            curl_setopt($ch, CURLOPT_CUSTOMREQUEST, $method);
            if (!empty($data)) {
                curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
            }
        } elseif ($method === 'GET') {
            curl_setopt($ch, CURLOPT_HTTPGET, true);
        }

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        $result = json_decode((string) $response, true) ?? [];

        if ($httpCode !== 200 && $httpCode !== 201) {
            throw new Exception($result['message'] ?? 'Flutterwave Request failed: HTTP ' . $httpCode);
        }

        return $result['data'] ?? $result;
    }

    /**
     * Generate unique reference.
     */
    private function generateReference(): string
    {
        return 'FLW_' . time() . '_' . uniqid();
    }
}
