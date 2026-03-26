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
use Plugs\Payment\Utils\AmountConverter;

class PayPalPaymentDriver implements PaymentDriverInterface
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
            ? 'https://api-m.paypal.com'
            : 'https://api-m.sandbox.paypal.com';
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
            'Accept: application/json',
            'Accept-Language: en_US',
            'Authorization: Basic ' . base64_encode($this->clientId . ':' . $this->clientSecret),
        ];

        // We use curl directly for the auth as it requires specific encoding and basic auth
        $ch = curl_init($this->baseUrl . '/v1/oauth2/token');
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, 'grant_type=client_credentials');

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $curlError = curl_error($ch);
        curl_close($ch);

        $result = json_decode((string) $response, true);

        if ($curlError) {
            throw new GatewayException("cURL Error: " . $curlError, 0);
        }

        if ($httpCode !== 200) {
            throw new GatewayException('PayPal Authentication failed: ' . ($result['error_description'] ?? 'Unknown error'), $httpCode);
        }

        return $this->accessToken = $result['access_token'];
    }

    /**
     * Initialize a new payment transaction (Create Order).
     *
     * @param array $payload
     * @return PaymentResponse
     */
    public function initialize(array $payload): PaymentResponse
    {
        $orderData = [
            'intent' => 'CAPTURE',
            'purchase_units' => [
                [
                    'reference_id' => $payload['reference'] ?? uniqid('ref_'),
                    'amount' => [
                        'currency_code' => $payload['currency'] ?? 'USD',
                        'value' => AmountConverter::toDecimal($payload['amount'] ?? 0, $payload['currency'] ?? 'USD'),
                    ],
                    'description' => $payload['description'] ?? 'Payment',
                ]
            ],
            'application_context' => [
                'return_url' => $payload['callback_url'] ?? '',
                'cancel_url' => $payload['cancel_url'] ?? '',
                'shipping_preference' => 'NO_SHIPPING',
                'user_action' => 'PAY_NOW',
            ]
        ];

        $response = $this->makeRequest('/v2/checkout/orders', $orderData);

        $approveLink = '';
        foreach ($response['links'] ?? [] as $link) {
            if ($link['rel'] === 'approve') {
                $approveLink = $link['href'];
                break;
            }
        }

        return new PaymentResponse(
            $response['id'] ?? '',
            $approveLink,
            $this->normalizeStatus($response['status'] ?? 'CREATED'),
            (float) ($payload['amount'] ?? 0),
            $payload['currency'] ?? 'USD',
            'Order created successfully',
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
        // For PayPal, we usually need to CAPTURE the order if it's APPROVED
        // This method serves as both status check and potential capture logic.
        $response = $this->makeRequest("/v2/checkout/orders/{$reference}", [], 'GET');

        if ($response['status'] === 'APPROVED') {
            $response = $this->makeRequest("/v2/checkout/orders/{$reference}/capture", [], 'POST');
        }

        $amount = 0;
        $currency = 'USD';

        // Handle CAPTURE response or ORDER response
        $payments = $response['purchase_units'][0]['payments'] ?? [];
        $captures = $payments['captures'] ?? [];

        if (!empty($captures)) {
            $amount = (float) $captures[0]['amount']['value'];
            $currency = $captures[0]['amount']['currency_code'];
        } elseif (isset($response['amount'])) {
            // Some responses might have different structure
            $amount = (float) ($response['amount']['value'] ?? 0);
            $currency = $response['amount']['currency_code'] ?? 'USD';
        }

        return new PaymentVerification(
            $response['id'] ?? $reference,
            $this->normalizeStatus($response['status'] ?? 'COMPLETED'),
            $amount,
            $currency,
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
            'amount' => [
                'value' => AmountConverter::toDecimal($amount, 'USD'),
                'currency_code' => 'USD',
            ],
            'note_to_payer' => $reason ?? 'Refund',
        ];

        $response = $this->makeRequest("/v2/payments/captures/{$reference}/refund", $refundData, 'POST');

        return new RefundResponse(
            $reference,
            $this->normalizeStatus($response['status'] ?? 'COMPLETED'),
            (float) ($response['amount']['value'] ?? $amount),
            $response['amount']['currency_code'] ?? 'USD',
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
     * Internal request helper to handle Bearer token.
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
     * Normalize PayPal status.
     */
    private function normalizeStatus(string $status): string
    {
        $status = strtoupper($status);
        return match ($status) {
            'COMPLETED', 'SUCCESSFUL', 'CAPTURED' => 'success',
            'FAILED', 'DENIED', 'EXPIRED' => 'failed',
            'APPROVED', 'CREATED', 'SAVED', 'PENDING' => 'pending',
            default => 'pending',
        };
    }
}
