<?php

declare(strict_types=1);

namespace Plugs\Payout\Drivers;

use Exception;
use Plugs\Http\Request;
use Plugs\Payout\Contracts\PayoutDriverInterface;
use Plugs\Payout\DTO\TransferResponse;
use Plugs\Payout\DTO\WithdrawResponse;
use Plugs\Payout\DTO\PayoutVerification;

class StripePayoutDriver implements PayoutDriverInterface
{
    private string $secretKey;
    private string $webhookSecret;
    private string $baseUrl = 'https://api.stripe.com/v1';

    /**
     * @param array $config
     */
    public function __construct(array $config)
    {
        $this->secretKey = $config['secret_key'] ?? '';
        $this->webhookSecret = $config['webhook_secret'] ?? '';
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
            'amount' => $payload['amount'] ?? 0,
            'currency' => strtolower($payload['currency'] ?? 'usd'),
            'destination' => $payload['recipient'] ?? '', // Connected account ID
            'description' => $payload['reason'] ?? 'Transfer',
            'metadata' => $payload['metadata'] ?? [],
        ];

        $response = $this->makeRequest('/transfers', $transferData);

        return new TransferResponse(
            $response['id'] ?? $payload['reference'] ?? '',
            $this->normalizeStatus($response['status'] ?? 'pending'),
            (float) ($response['amount'] ?? $transferData['amount']),
            strtoupper($response['currency'] ?? $transferData['currency']),
            'Transfer initiated via Stripe',
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
        $payoutData = [
            'amount' => $payload['amount'] ?? 0,
            'currency' => strtolower($payload['currency'] ?? 'usd'),
            'description' => $payload['narration'] ?? 'Withdrawal',
            'metadata' => [
                'bank_account' => $payload['account_number'] ?? '',
                'bank_code' => $payload['bank_code'] ?? '',
            ],
        ];

        if (isset($payload['destination'])) {
            $payoutData['destination'] = $payload['destination'];
        }

        $response = $this->makeRequest('/payouts', $payoutData);

        return new WithdrawResponse(
            $response['id'] ?? $payload['reference'] ?? '',
            $this->normalizeStatus($response['status'] ?? 'pending'),
            (float) ($response['amount'] ?? $payoutData['amount']),
            strtoupper($response['currency'] ?? $payoutData['currency']),
            'Withdrawal initiated via Stripe',
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
        $accountData = [
            'object' => 'bank_account',
            'country' => $data['country'] ?? 'US',
            'currency' => strtolower($data['currency'] ?? 'usd'),
            'account_holder_name' => $data['name'] ?? '',
            'account_holder_type' => $data['type'] ?? 'individual',
            'routing_number' => $data['routing_number'] ?? '',
            'account_number' => $data['account_number'] ?? '',
        ];

        // For connected external accounts on Stripe.
        // Usually attached to a custom account or directly.
        return [
            'recipient_code' => 'ba_' . uniqid(),
            'details' => $accountData,
        ];
    }

    /**
     * Verify the status of an outgoing transfer/withdrawal.
     *
     * @param string $reference
     * @return PayoutVerification
     */
    public function verify(string $reference): PayoutVerification
    {
        // Try transfer first
        try {
            $response = $this->makeRequest("/transfers/{$reference}", [], 'GET');
        } catch (Exception $e) {
            // Fallback to payout if transfer not found
            $response = $this->makeRequest("/payouts/{$reference}", [], 'GET');
        }

        return new PayoutVerification(
            $response['id'] ?? $reference,
            $this->normalizeStatus($response['status'] ?? 'pending'),
            (float) ($response['amount'] ?? 0),
            strtoupper($response['currency'] ?? 'USD'),
            'Verification retrieved',
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
        if (empty($this->webhookSecret)) {
            return true;
        }

        $signature = $request->getHeaderLine('Stripe-Signature');
        if (!$signature && isset($_SERVER['HTTP_STRIPE_SIGNATURE'])) {
            $signature = $_SERVER['HTTP_STRIPE_SIGNATURE'];
        }

        if (empty($signature)) {
            return false;
        }

        $signatureParts = [];
        foreach (explode(',', (string) $signature) as $part) {
            $split = explode('=', $part, 2);
            if (count($split) === 2) {
                $signatureParts[$split[0]] = $split[1];
            }
        }

        if (!isset($signatureParts['t']) || !isset($signatureParts['v1'])) {
            return false;
        }

        $timestamp = $signatureParts['t'];
        $expectedSignature = $signatureParts['v1'];

        $payloadRaw = (string) $request->getBody();
        if (empty($payloadRaw)) {
            $payloadRaw = file_get_contents('php://input');
        }

        $payloadString = $timestamp . '.' . $payloadRaw;
        $computedSignature = hash_hmac('sha256', $payloadString, $this->webhookSecret);

        return hash_equals($computedSignature, $expectedSignature);
    }

    /**
     * Normalize Stripe statuses to global generic framework statuses
     *
     * @param string $status
     * @return string
     */
    private function normalizeStatus(string $status): string
    {
        $status = strtolower($status);
        if ($status === 'paid' || $status === 'succeeded') {
            return 'success';
        }
        if (in_array($status, ['canceled', 'failed'])) {
            return 'failed';
        }
        return 'pending';
    }

    /**
     * Make HTTP request to Stripe API
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

        $headers = [
            'Authorization: Bearer ' . $this->secretKey,
            'Content-Type: application/x-www-form-urlencoded',
        ];

        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);

        if ($method === 'POST') {
            curl_setopt($ch, CURLOPT_POST, true);
            if (!empty($data)) {
                curl_setopt($ch, CURLOPT_POSTFIELDS, $this->buildQuery($data));
            }
        } elseif ($method === 'DELETE') {
            curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'DELETE');
        } elseif ($method === 'GET') {
            curl_setopt($ch, CURLOPT_HTTPGET, true);
        }

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $error = curl_error($ch);
        curl_close($ch);

        if ($error) {
            throw new Exception("cURL Error: {$error}");
        }

        $result = json_decode((string) $response, true) ?? [];

        if ($httpCode >= 400) {
            $errorMsg = $result['error']['message'] ?? 'Request failed';
            throw new Exception("Stripe Error ({$httpCode}): {$errorMsg}");
        }

        return $result;
    }

    /**
     * Build URL-encoded query string for nested arrays (Stripe format)
     *
     * @param array $data
     * @param string $prefix
     * @return string
     */
    private function buildQuery(array $data, string $prefix = ''): string
    {
        $query = [];

        foreach ($data as $key => $value) {
            $fullKey = $prefix ? "{$prefix}[{$key}]" : (string) $key;

            if (is_array($value)) {
                $query[] = $this->buildQuery($value, $fullKey);
            } else {
                $query[] = urlencode($fullKey) . '=' . urlencode((string) $value);
            }
        }

        return implode('&', $query);
    }
}
