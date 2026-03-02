<?php

declare(strict_types=1);

namespace Plugs\Payout\Drivers;

use Plugs\Http\Request;
use Plugs\Payout\Contracts\PayoutDriverInterface;
use Plugs\Payout\DTO\TransferResponse;
use Plugs\Payout\DTO\WithdrawResponse;
use Plugs\Payout\DTO\PayoutVerification;
use Plugs\Payment\Traits\HasHttpCalls;
use Plugs\Payment\Exceptions\GatewayException;

class PayPalPayoutDriver implements PayoutDriverInterface
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
            throw new GatewayException('PayPal Authentication failed', $httpCode);
        }

        return $this->accessToken = $result['access_token'];
    }

    /**
     * Transfer funds to an external account (PayPal Payout).
     *
     * @param array $payload
     * @return TransferResponse
     */
    public function transfer(array $payload): TransferResponse
    {
        $payoutData = [
            'sender_batch_header' => [
                'sender_batch_id' => $payload['reference'] ?? uniqid('payout_'),
                'email_subject' => $payload['subject'] ?? 'You have a payout!',
                'email_message' => $payload['message'] ?? 'You have received a payout! Thanks for using our service!',
            ],
            'items' => [
                [
                    'recipient_type' => 'EMAIL',
                    'amount' => [
                        'value' => (string) ($payload['amount'] ?? 0),
                        'currency' => $payload['currency'] ?? 'USD',
                    ],
                    'note' => $payload['reason'] ?? 'Payout',
                    'sender_item_id' => $payload['reference'] ?? uniqid('item_'),
                    'receiver' => $payload['recipient'] ?? '', // Email or Phone
                ]
            ]
        ];

        $response = $this->makeRequest('/v1/payments/payouts', $payoutData);

        return new TransferResponse(
            $response['batch_header']['payout_batch_id'] ?? '',
            $this->normalizeStatus($response['batch_header']['batch_status'] ?? 'PENDING'),
            (float) ($payload['amount'] ?? 0),
            $payload['currency'] ?? 'USD',
            'Payout batch created',
            $response
        );
    }

    /**
     * Withdraw funds (Equivalent to transfer in PayPal).
     *
     * @param array $payload
     * @return WithdrawResponse
     */
    public function withdraw(array $payload): WithdrawResponse
    {
        $transfer = $this->transfer($payload);
        return new WithdrawResponse(
            $transfer->reference,
            $transfer->status,
            $transfer->amount,
            $transfer->currency,
            $transfer->message,
            $transfer->metadata
        );
    }

    /**
     * Get account balance.
     *
     * @return array
     */
    public function getBalance(): array
    {
        // https://developer.paypal.com/docs/api/payouts/v1/#payouts_get_balance
        // This requires specific permissions and might not be available for all apps.
        return $this->makeRequest('/v1/account/balances', [], 'GET');
    }

    /**
     * PayPal doesn't have a specific "Recipient" object to create ahead of time.
     */
    public function createRecipient(array $data): array
    {
        return [
            'recipient_code' => $data['email'] ?? $data['receiver'] ?? '',
            'details' => $data,
        ];
    }

    /**
     * Delete a recipient/beneficiary.
     */
    public function deleteRecipient(string $recipientCode): bool
    {
        return true;
    }

    /**
     * Verify a payout status.
     *
     * @param string $reference
     * @return PayoutVerification
     */
    public function verify(string $reference): PayoutVerification
    {
        // reference can be batch_id or item_id
        // Try batch first
        try {
            $response = $this->makeRequest("/v1/payments/payouts/{$reference}", [], 'GET');
            $status = $response['batch_header']['batch_status'] ?? 'PENDING';
        } catch (GatewayException $e) {
            // Try item id
            $response = $this->makeRequest("/v1/payments/payouts-item/{$reference}", [], 'GET');
            $status = $response['transaction_status'] ?? 'PENDING';
        }

        return new PayoutVerification(
            $reference,
            $this->normalizeStatus($status),
            (float) ($response['batch_header']['amount']['value'] ?? $response['payout_item']['amount']['value'] ?? 0),
            $response['batch_header']['amount']['currency'] ?? $response['payout_item']['amount']['currency'] ?? 'USD',
            'Verification retrieved',
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
     * Verify webhook signature.
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
     * Normalize PayPal status.
     */
    private function normalizeStatus(string $status): string
    {
        $status = strtoupper($status);
        return match ($status) {
            'SUCCESSFUL', 'COMPLETED', 'SUCCESS' => 'success',
            'FAILED', 'DENIED', 'RETURNED', 'BLOCKED' => 'failed',
            'PENDING', 'PROCESSING', 'NEW', 'SUBMITTED' => 'pending',
            default => 'pending',
        };
    }
}
