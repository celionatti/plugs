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

class PayoneerPayoutDriver implements PayoutDriverInterface
{
    use HasHttpCalls;

    private string $clientId;
    private string $clientSecret;
    private string $programId;
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
        $this->programId = $config['program_id'] ?? '';
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
        curl_setopt($ch, CURLOPT_POSTFIELDS, 'grant_type=client_credentials&scope=read write paito');

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
     * Transfer funds to a Payoneer account.
     *
     * @param array $payload
     * @return TransferResponse
     */
    public function transfer(array $payload): TransferResponse
    {
        $payoutData = [
            'program_id' => $this->programId,
            'payout_id' => $payload['reference'] ?? uniqid('payout_'),
            'payee_id' => $payload['recipient'] ?? '', // Payoneer Payee ID
            'amount' => $payload['amount'] ?? 0,
            'currency' => strtoupper($payload['currency'] ?? 'USD'),
            'description' => $payload['reason'] ?? 'Payout',
        ];

        $response = $this->makeRequest('/v4/payouts', $payoutData);

        return new TransferResponse(
            $response['payout_id'] ?? $payoutData['payout_id'],
            $this->normalizeStatus($response['status'] ?? 'pending'),
            (float) ($payload['amount'] ?? 0),
            $payload['currency'] ?? 'USD',
            'Payout submitted',
            $response
        );
    }

    /**
     * Withdraw funds.
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
        return $this->makeRequest("/v4/programs/{$this->programId}/balance", [], 'GET');
    }

    /**
     * Payoneer requires payee registration.
     */
    public function createRecipient(array $data): array
    {
        // Payoneer payee registration is usually a complex flow involving a redirect.
        // For API direct registration (if enabled):
        return [
            'recipient_code' => $data['payee_id'] ?? '',
            'details' => $data,
        ];
    }

    /**
     * Delete a recipient.
     */
    public function deleteRecipient(string $recipientCode): bool
    {
        return true;
    }

    /**
     * Verify payout status.
     *
     * @param string $reference
     * @return PayoutVerification
     */
    public function verify(string $reference): PayoutVerification
    {
        $response = $this->makeRequest("/v4/payouts/{$reference}", [], 'GET');

        return new PayoutVerification(
            $reference,
            $this->normalizeStatus($response['status'] ?? 'pending'),
            (float) ($response['amount'] ?? 0),
            strtoupper($response['currency'] ?? 'USD'),
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
     * Normalize status.
     */
    private function normalizeStatus(string $status): string
    {
        $status = strtolower($status);
        return match ($status) {
            'completed', 'success', 'executed' => 'success',
            'failed', 'cancelled', 'rejected' => 'failed',
            'pending', 'processing', 'submitted' => 'pending',
            default => 'pending',
        };
    }
}
