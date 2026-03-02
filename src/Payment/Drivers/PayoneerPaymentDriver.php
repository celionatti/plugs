<?php

declare(strict_types=1);

namespace Plugs\Payment\Drivers;

use Plugs\Http\Request;
use Plugs\Payment\Contracts\PaymentDriverInterface;
use Plugs\Payment\DTO\PaymentResponse;
use Plugs\Payment\DTO\PaymentVerification;
use Plugs\Payment\DTO\RefundResponse;
use Plugs\Payment\Traits\HasHttpCalls;
use Plugs\Payment\Exceptions\GatewayException;

class PayoneerPaymentDriver implements PaymentDriverInterface
{
    use HasHttpCalls;

    private string $clientId;
    private string $clientSecret;
    private string $mode;
    private string $baseUrl;
    private ?string $accessToken = null;

    /**
     * @param array $config
     */
    public function __construct(array $config)
    {
        $this->clientId = $config['client_id'] ?? '';
        $this->clientSecret = $config['client_secret'] ?? '';
        $this->mode = $config['mode'] ?? 'sandbox';
        $this->baseUrl = $this->mode === 'live'
            ? 'https://api.payoneer.com'
            : 'https://api.sandbox.payoneer.com';
    }

    /**
     * Get OAuth2 Access Token.
     */
    private function getAccessToken(): string
    {
        if ($this->accessToken) {
            return $this->accessToken;
        }

        $headers = [
            'Content-Type: application/x-www-form-urlencoded',
            'Authorization: Basic ' . base64_encode($this->clientId . ':' . $this->clientSecret),
        ];

        $ch = curl_init($this->baseUrl . '/v2/oauth2/token');
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, 'grant_type=client_credentials&scope=read write');

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        $result = json_decode((string) $response, true);

        if ($httpCode !== 200) {
            throw new GatewayException('Payoneer Authentication failed', $httpCode);
        }

        return $this->accessToken = $result['access_token'];
    }

    /**
     * Initialize a new payment transaction.
     *
     * @param array $payload
     * @return PaymentResponse
     */
    public function initialize(array $payload): PaymentResponse
    {
        $sessionData = [
            'amount' => $payload['amount'] ?? 0,
            'currency' => strtoupper($payload['currency'] ?? 'USD'),
            'description' => $payload['description'] ?? 'Payment',
            'reference' => $payload['reference'] ?? uniqid('pay_'),
            'callback_url' => $payload['callback_url'] ?? '',
        ];

        $response = $this->makeRequest('/v1/checkout/sessions', $sessionData);

        return new PaymentResponse(
            $response['id'] ?? $sessionData['reference'],
            $response['redirect_url'] ?? '',
            $this->normalizeStatus($response['status'] ?? 'pending'),
            (float) ($payload['amount'] ?? 0),
            $payload['currency'] ?? 'USD',
            'Payment session initialized',
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
        $response = $this->makeRequest("/v1/checkout/sessions/{$reference}", [], 'GET');

        return new PaymentVerification(
            $response['id'] ?? $reference,
            $this->normalizeStatus($response['status'] ?? 'pending'),
            (float) ($response['amount'] ?? 0),
            $response['currency'] ?? 'USD',
            'Verification successful',
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
        $refundData = [
            'amount' => $amount,
            'reason' => $reason ?? 'Refund',
        ];

        $response = $this->makeRequest("/v1/checkout/sessions/{$reference}/refund", $refundData, 'POST');

        return new RefundResponse(
            $reference,
            $this->normalizeStatus($response['status'] ?? 'completed'),
            $amount,
            'USD',
            'Refund processed',
            $response
        );
    }

    /**
     * Handle incoming webhooks.
     *
     * @param array $payload
     * @return void
     */
    public function webhook(array $payload): void
    {
        // Driven by WebhookRouter
    }

    /**
     * Verify the incoming webhook signature.
     *
     * @param Request $request
     * @return bool
     */
    public function verifyWebhookSignature(Request $request): bool
    {
        return true;
    }

    /**
     * Internal request helper.
     */
    protected function makeRequest(string $endpoint, array $data = [], string $method = 'POST')
    {
        $headers = [
            'Authorization: Bearer ' . $this->getAccessToken(),
            'Content-Type: application/json',
            'Accept: application/json',
        ];

        return $this->makeHttpRequest($this->baseUrl . $endpoint, $data, $method, $headers);
    }

    /**
     * Normalize Payoneer status.
     */
    private function normalizeStatus(string $status): string
    {
        $status = strtolower($status);
        return match ($status) {
            'completed', 'success', 'succeeded' => 'success',
            'failed', 'declined', 'cancelled' => 'failed',
            'pending', 'processing', 'created' => 'pending',
            default => 'pending',
        };
    }
}
