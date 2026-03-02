<?php

declare(strict_types=1);

namespace Plugs\Payout\Drivers;

use Exception;
use Plugs\Http\Request;
use Plugs\Payout\Contracts\PayoutDriverInterface;
use Plugs\Payout\DTO\TransferResponse;
use Plugs\Payout\DTO\WithdrawResponse;
use Plugs\Payout\DTO\PayoutVerification;

class PaystackPayoutDriver implements PayoutDriverInterface
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
     * Transfer funds to an external account or wallet.
     *
     * @param array $payload
     * @return TransferResponse
     */
    public function transfer(array $payload): TransferResponse
    {
        $response = $this->makeRequest('/transfer', $payload);

        return new TransferResponse(
            $response['reference'] ?? $payload['reference'] ?? '',
            $response['status'] ?? 'pending',
            (float) ($response['amount'] ?? $payload['amount'] ?? 0),
            $response['currency'] ?? 'NGN',
            $response['message'] ?? 'Transfer initiated via Paystack',
            $response
        );
    }

    /**
     * Withdraw funds from the platform to the gateway/bank.
     * Paystack usually uses /transfer for both, so we alias it.
     *
     * @param array $payload
     * @return WithdrawResponse
     */
    public function withdraw(array $payload): WithdrawResponse
    {
        $response = $this->makeRequest('/transfer', $payload);

        return new WithdrawResponse(
            $response['reference'] ?? $payload['reference'] ?? '',
            $response['status'] ?? 'pending',
            (float) ($response['amount'] ?? $payload['amount'] ?? 0),
            $response['currency'] ?? 'NGN',
            $response['message'] ?? 'Withdrawal initiated via Paystack',
            $response
        );
    }

    /**
     * Get the current balance of the gateway account.
     *
     * @return array
     */
    public function getBalance(): array
    {
        return $this->makeRequest('/balance', [], 'GET');
    }

    /**
     * Create a recipient/beneficiary for future transfers.
     *
     * @param array $data
     * @return array
     */
    public function createRecipient(array $data): array
    {
        return $this->makeRequest('/transferrecipient', $data);
    }

    /**
     * Verify the status of an outgoing transfer/withdrawal.
     *
     * @param string $reference
     * @return PayoutVerification
     */
    public function verify(string $reference): PayoutVerification
    {
        $response = $this->makeRequest("/transfer/verify/{$reference}", [], 'GET');

        return new PayoutVerification(
            $response['reference'] ?? $reference,
            $response['status'] ?? 'pending',
            (float) ($response['amount'] ?? 0),
            $response['currency'] ?? 'NGN',
            $response['message'] ?? 'Verification retrieved',
            $response
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
        // Event processing logic will be handled by WebhookRouter
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
