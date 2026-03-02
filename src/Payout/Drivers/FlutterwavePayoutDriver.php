<?php

declare(strict_types=1);

namespace Plugs\Payout\Drivers;

use Plugs\Http\Request;
use Plugs\Payout\Contracts\PayoutDriverInterface;
use Plugs\Payout\DTO\TransferResponse;
use Plugs\Payout\DTO\WithdrawResponse;
use Plugs\Payout\DTO\PayoutVerification;

use Plugs\Payment\Traits\HasHttpCalls;

class FlutterwavePayoutDriver implements PayoutDriverInterface
{
    use HasHttpCalls;

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
     * Delete a recipient/beneficiary.
     *
     * @param string $recipientCode
     * @return bool
     */
    public function deleteRecipient(string $recipientCode): bool
    {
        $result = $this->makeRequest("/beneficiaries/{$recipientCode}", [], 'DELETE');
        return true; // If makeRequest didn't throw, it's successful
    }

    /**
     * Verify the status of an outgoing transfer/withdrawal.
     *
     * @param string $reference
     * @return PayoutVerification
     */
    public function verify(string $reference): PayoutVerification
    {
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
     * Make HTTP request using the shared trait.
     */
    protected function makeRequest(string $endpoint, array $data = [], string $method = 'POST')
    {
        $headers = [
            'Authorization: Bearer ' . $this->secretKey,
            'Content-Type: application/json',
        ];

        $result = $this->makeHttpRequest($this->baseUrl . $endpoint, $data, $method, $headers);

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
