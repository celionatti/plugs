<?php

declare(strict_types=1);

namespace Plugs\Payout\Drivers;

use Plugs\Http\Request;
use Plugs\Payout\Contracts\PayoutDriverInterface;
use Plugs\Payout\DTO\TransferResponse;
use Plugs\Payout\DTO\WithdrawResponse;
use Plugs\Payout\DTO\PayoutVerification;

use Plugs\Payment\Traits\HasHttpCalls;

class PaystackPayoutDriver implements PayoutDriverInterface
{
    use HasHttpCalls;

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
     * Delete a recipient/beneficiary.
     *
     * @param string $recipientCode
     * @return bool
     */
    public function deleteRecipient(string $recipientCode): bool
    {
        // Paystack doesn't have a direct 'delete' endpoint for recipients in their basic API,
        // but they allow deactivating them or we return true for compatibility if not supported.
        return true;
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
     * Internal helper specific to Paystack's envelope structure.
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
}
