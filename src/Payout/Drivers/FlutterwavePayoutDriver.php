<?php

declare(strict_types=1);

namespace Plugs\Payout\Drivers;

use Exception;
use Plugs\Http\Request;
use Plugs\Payout\Contracts\PayoutDriverInterface;
use Plugs\Payout\DTO\TransferResponse;
use Plugs\Payout\DTO\WithdrawResponse;
use Plugs\Payout\DTO\PayoutVerification;

class FlutterwavePayoutDriver implements PayoutDriverInterface
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
     * Transfer funds to an external account or wallet.
     *
     * @param array $payload
     * @return TransferResponse
     */
    public function transfer(array $payload): TransferResponse
    {
        $transferData = [
            'account_bank' => $payload['bank_code'] ?? '',
            'account_number' => $payload['account_number'] ?? '',
            'amount' => $payload['amount'] ?? 0,
            'currency' => $payload['currency'] ?? 'NGN',
            'narration' => $payload['reason'] ?? 'Transfer',
            'reference' => $payload['reference'] ?? $this->generateReference(),
            'callback_url' => $payload['callback_url'] ?? '',
            'debit_currency' => $payload['currency'] ?? 'NGN',
        ];

        if (isset($payload['recipient'])) {
            $transferData['account_bank'] = $payload['recipient'];
        }

        $response = $this->makeRequest('/transfers', $transferData);

        return new TransferResponse(
            $response['reference'] ?? $transferData['reference'],
            $this->normalizeStatus($response['status'] ?? 'pending'),
            (float) ($response['amount'] ?? $payload['amount'] ?? 0),
            $response['currency'] ?? $payload['currency'] ?? 'NGN',
            $response['complete_message'] ?? 'Transfer initiated via Flutterwave',
            $response
        );
    }

    /**
     * Withdraw funds from the platform to the gateway/bank.
     *
     * @param array $payload
     * @return WithdrawResponse
     */
    public function withdraw(array $payload): WithdrawResponse
    {
        $withdrawalData = [
            'account_bank' => $payload['bank_code'] ?? '',
            'account_number' => $payload['account_number'] ?? '',
            'amount' => $payload['amount'] ?? 0,
            'currency' => $payload['currency'] ?? 'NGN',
            'narration' => $payload['narration'] ?? 'Withdrawal',
            'reference' => $payload['reference'] ?? $this->generateReference(),
            'callback_url' => $payload['callback_url'] ?? '',
            'debit_currency' => $payload['currency'] ?? 'NGN',
        ];

        $response = $this->makeRequest('/transfers', $withdrawalData);

        return new WithdrawResponse(
            $response['reference'] ?? $withdrawalData['reference'],
            $this->normalizeStatus($response['status'] ?? 'pending'),
            (float) ($response['amount'] ?? $payload['amount'] ?? 0),
            $response['currency'] ?? $payload['currency'] ?? 'NGN',
            $response['complete_message'] ?? 'Withdrawal initiated via Flutterwave',
            $response
        );
    }

    /**
     * Get the current balance.
     *
     * @return array
     */
    public function getBalance(): array
    {
        return $this->makeRequest('/balances', [], 'GET');
    }

    /**
     * Create a transfer recipient.
     *
     * @param array $data
     * @return array
     */
    public function createRecipient(array $data): array
    {
        $recipientData = [
            'type' => $data['type'] ?? 'nuban',
            'name' => $data['name'] ?? '',
            'account_number' => $data['account_number'] ?? '',
            'bank_code' => $data['bank_code'] ?? '',
            'currency' => $data['currency'] ?? 'NGN',
            'email' => $data['email'] ?? '',
            'mobile_number' => $data['phone'] ?? '',
            'meta' => $data['metadata'] ?? [],
        ];

        return $this->makeRequest('/beneficiaries', $recipientData);
    }

    /**
     * Verify the status of an outgoing transfer/withdrawal.
     *
     * @param string $reference
     * @return PayoutVerification
     */
    public function verify(string $reference): PayoutVerification
    {
        // Flutterwave verify transfer by ID usually, but has reference too
        // We might need to list transfers or use the ID if we have it in metadata.
        // For now, let's assume we can get it by ID or reference if mapped.
        // Legacy didn't have a direct verify for transfers by reference in a simple way
        // without listing. Let's list by reference if possible or just return pending.

        return new PayoutVerification(
            $reference,
            'pending',
            0,
            'NGN',
            'Verification pending for Flutterwave transfers',
            []
        );
    }

    /**
     * Handle incoming webhooks for payout status updates.
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
