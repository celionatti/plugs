<?php

declare(strict_types=1);

namespace Plugs\Payment\Drivers;

use Exception;
use Plugs\Http\Request;
use Plugs\Payment\Contracts\PaymentDriverInterface;
use Plugs\Payment\DTO\PaymentResponse;
use Plugs\Payment\DTO\PaymentVerification;
use Plugs\Payment\DTO\RefundResponse;

class BTCPayPaymentDriver implements PaymentDriverInterface
{
    private string $apiKey;
    private string $storeId;
    private string $baseUrl;
    private string $webhookSecret;

    public function __construct(array $config)
    {
        $this->apiKey = $config['api_key'] ?? '';
        $this->storeId = $config['store_id'] ?? '';
        $this->baseUrl = rtrim($config['base_url'] ?? '', '/');
        $this->webhookSecret = $config['webhook_secret'] ?? '';
    }

    public function initialize(array $payload): PaymentResponse
    {
        $invoiceData = [
            'amount' => (string) ($payload['amount'] ?? 0),
            'currency' => $payload['currency'] ?? 'USD',
            'metadata' => [
                'buyerEmail' => $payload['email'] ?? '',
                'orderId' => $payload['reference'] ?? uniqid('ord_'),
                'itemDesc' => $payload['description'] ?? 'Payment',
            ],
            'checkout' => [
                'redirectURL' => $payload['callback_url'] ?? null,
                'speedPolicy' => $payload['speed_policy'] ?? 'MediumSpeed',
                'paymentMethods' => $payload['payment_methods'] ?? ['BTC', 'LTC', 'ETH'],
            ],
        ];

        if (isset($payload['metadata'])) {
            $invoiceData['metadata'] = array_merge($invoiceData['metadata'], $payload['metadata']);
        }

        $response = $this->makeRequest("/api/v1/stores/{$this->storeId}/invoices", $invoiceData, 'POST');

        return new PaymentResponse(
            $response['id'] ?? '',
            $response['checkoutLink'] ?? '',
            'pending',
            (float) ($response['amount'] ?? 0),
            $response['currency'] ?? 'USD',
            'Invoice created',
            $response
        );
    }

    public function verify(string $reference): PaymentVerification
    {
        $response = $this->makeRequest("/api/v1/stores/{$this->storeId}/invoices/{$reference}", [], 'GET');

        return new PaymentVerification(
            $response['id'] ?? $reference,
            $this->normalizeStatus($response['status'] ?? 'pending'),
            (float) ($response['amount'] ?? 0),
            $response['currency'] ?? 'USD',
            'Verification retrieved',
            $response
        );
    }

    public function refund(string $reference, float $amount, ?string $reason = null): RefundResponse
    {
        $refundData = [
            'name' => 'Refund for ' . $reference,
            'description' => $reason ?? 'Refund',
            'refundVariant' => 'CurrentRate',
            'customAmount' => (string) $amount,
        ];

        $response = $this->makeRequest("/api/v1/stores/{$this->storeId}/invoices/{$reference}/refund", $refundData, 'POST');

        return new RefundResponse(
            $reference,
            $response['status'] ?? 'pending',
            (float) ($response['amount'] ?? $amount),
            $response['currency'] ?? 'USD',
            'Refund initiated',
            $response
        );
    }

    public function webhook(array $payload): void
    {
    }

    public function verifyWebhookSignature(Request $request): bool
    {
        if (empty($this->webhookSecret)) {
            return true;
        }

        $signature = $request->getHeaderLine('Btcpay-Sig');
        if (!$signature && isset($_SERVER['HTTP_BTCPAY_SIG'])) {
            $signature = $_SERVER['HTTP_BTCPAY_SIG'];
        }

        if (empty($signature)) {
            return false;
        }

        $payloadRaw = (string) $request->getBody();
        if (empty($payloadRaw)) {
            $payloadRaw = file_get_contents('php://input');
        }

        $expectedSignature = 'sha256=' . hash_hmac('sha256', (string) $payloadRaw, $this->webhookSecret);

        return hash_equals($expectedSignature, (string) $signature);
    }

    private function normalizeStatus(string $status): string
    {
        $status = strtolower($status);
        if (in_array($status, ['settled', 'paid'])) {
            return 'success';
        }
        if (in_array($status, ['invalid', 'expired'])) {
            return 'failed';
        }
        return 'pending';
    }

    private function makeRequest(string $endpoint, array $data = [], string $method = 'GET')
    {
        $url = $this->baseUrl . $endpoint;
        $ch = curl_init($url);

        $headers = [
            'Authorization: token ' . $this->apiKey,
            'Content-Type: application/json',
        ];

        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
        curl_setopt($ch, CURLOPT_CUSTOMREQUEST, $method);

        if (in_array($method, ['POST', 'PUT', 'PATCH']) && !empty($data)) {
            curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
        }

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        $result = json_decode((string) $response, true) ?? [];

        if ($httpCode >= 400) {
            $errorMsg = $result['message'] ?? $result['error'] ?? 'Request failed';
            throw new Exception("BTCPay Error ({$httpCode}): {$errorMsg}");
        }

        return $result;
    }
}
