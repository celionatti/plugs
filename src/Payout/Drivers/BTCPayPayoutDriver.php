<?php

declare(strict_types=1);

namespace Plugs\Payout\Drivers;

use Plugs\Http\Request;
use Plugs\Payout\Contracts\PayoutDriverInterface;
use Plugs\Payout\DTO\TransferResponse;
use Plugs\Payout\DTO\WithdrawResponse;
use Plugs\Payout\DTO\PayoutVerification;

use Plugs\Payment\Traits\HasHttpCalls;

class BTCPayPayoutDriver implements PayoutDriverInterface
{
    use HasHttpCalls;

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

    public function deleteRecipient(string $recipientCode): bool
    {
        // BTCPay payouts don't have a 'recipient' entity in the same way as bank gateways
        return true;
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

    protected function makeRequest(string $endpoint, array $data = [], string $method = 'GET')
    {
        $headers = [
            'Authorization: token ' . $this->apiKey,
            'Content-Type: application/json',
        ];

        return $this->makeHttpRequest($this->baseUrl . $endpoint, $data, $method, $headers);
    }
}
