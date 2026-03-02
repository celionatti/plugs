<?php

declare(strict_types=1);

namespace Plugs\Payout\Drivers;

use Exception;
use Plugs\Http\Request;
use Plugs\Payout\Contracts\PayoutDriverInterface;
use Plugs\Payout\DTO\TransferResponse;
use Plugs\Payout\DTO\WithdrawResponse;
use Plugs\Payout\DTO\PayoutVerification;

class BTCPayPayoutDriver implements PayoutDriverInterface
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

    public function transfer(array $payload): TransferResponse
    {
        $payoutData = [
            'destination' => $payload['recipient'] ?? '',
            'amount' => (string) ($payload['amount'] ?? 0),
            'paymentMethod' => $payload['payment_method'] ?? 'BTC',
            'metadata' => array_merge([
                'reason' => $payload['reason'] ?? 'Transfer',
            ], $payload['metadata'] ?? []),
        ];

        $response = $this->makeRequest("/api/v1/stores/{$this->storeId}/payouts", $payoutData, 'POST');

        return new TransferResponse(
            $response['id'] ?? '',
            $this->normalizeStatus($response['status'] ?? 'pending'),
            (float) ($response['amount'] ?? 0),
            $response['currency'] ?? 'USD',
            'Transfer initiated',
            $response
        );
    }

    public function withdraw(array $payload): WithdrawResponse
    {
        $withdrawalData = [
            'destinations' => [
                [
                    'destination' => $payload['address'] ?? '',
                    'amount' => (string) ($payload['amount'] ?? 0),
                ],
            ],
            'paymentMethod' => $payload['crypto_currency'] ?? 'BTC',
            'feerate' => $payload['fee_rate'] ?? null,
            'metadata' => [
                'narration' => $payload['narration'] ?? 'Withdrawal',
            ],
        ];

        $response = $this->makeRequest("/api/v1/stores/{$this->storeId}/payouts", $withdrawalData, 'POST');

        return new WithdrawResponse(
            $response['id'] ?? '',
            $this->normalizeStatus($response['status'] ?? 'pending'),
            (float) ($response['amount'] ?? 0),
            $response['currency'] ?? 'USD',
            'Withdrawal initiated',
            $response
        );
    }

    public function getBalance(): array
    {
        $wallets = $this->makeRequest("/api/v1/stores/{$this->storeId}/payment-methods/onchain", [], 'GET');

        $balances = [];
        foreach ($wallets as $wallet) {
            $balance = $this->makeRequest("/api/v1/stores/{$this->storeId}/payment-methods/onchain/{$wallet['cryptoCode']}/wallet", [], 'GET');
            $balances[$wallet['cryptoCode']] = $balance;
        }

        return $balances;
    }

    public function createRecipient(array $data): array
    {
        return [
            'recipient_code' => $data['address'] ?? '',
            'type' => $data['type'] ?? 'crypto',
            'name' => $data['name'] ?? 'Crypto Recipient',
            'crypto_currency' => $data['crypto_currency'] ?? 'BTC',
            'address' => $data['address'] ?? '',
        ];
    }

    public function verify(string $reference): PayoutVerification
    {
        return new PayoutVerification($reference, 'pending', 0, 'USD', 'Stubbed', []);
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
        if (in_array($status, ['completed', 'settled'])) {
            return 'success';
        }
        if (in_array($status, ['cancelled', 'failed'])) {
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
