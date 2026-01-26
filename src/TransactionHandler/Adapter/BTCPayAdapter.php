<?php

declare(strict_types=1);

namespace Plugs\TransactionHandler\Adapter;

use Exception;
use Plugs\TransactionHandler\PaymentAdapterInterface;

/*
| -----------------------------------------------------------------------
| BTCPay Adapter
| -----------------------------------------------------------------------
*/

class BTCPayAdapter implements PaymentAdapterInterface
{
    private $apiKey;
    private $storeId;
    private $baseUrl;
    private $webhookSecret;

    public function __construct(array $config)
    {
        $this->apiKey = $config['api_key'];
        $this->storeId = $config['store_id'];
        $this->baseUrl = rtrim($config['base_url'], '/');
        $this->webhookSecret = $config['webhook_secret'] ?? '';
    }

    /**
     * Create an invoice (charge)
     */
    public function charge(array $data): array
    {
        $invoiceData = [
            'amount' => (string) $data['amount'],
            'currency' => $data['currency'] ?? 'USD',
            'metadata' => [
                'buyerEmail' => $data['email'] ?? '',
                'orderId' => $data['reference'] ?? uniqid('ord_'),
                'itemDesc' => $data['description'] ?? 'Payment',
            ],
            'checkout' => [
                'redirectURL' => $data['callback_url'] ?? null,
                'speedPolicy' => $data['speed_policy'] ?? 'MediumSpeed',
                'paymentMethods' => $data['payment_methods'] ?? ['BTC', 'LTC', 'ETH'],
            ],
        ];

        if (isset($data['metadata'])) {
            $invoiceData['metadata'] = array_merge(
                $invoiceData['metadata'],
                $data['metadata']
            );
        }

        return $this->makeRequest(
            "/api/v1/stores/{$this->storeId}/invoices",
            $invoiceData,
            'POST'
        );
    }

    /**
     * Create subscription (recurring invoice)
     */
    public function createSubscription(array $data): array
    {
        // BTCPay doesn't have native subscriptions, but we can create recurring invoices
        $pullPaymentData = [
            'name' => $data['plan_name'] ?? 'Subscription',
            'amount' => (string) $data['amount'],
            'currency' => $data['currency'] ?? 'USD',
            'period' => $this->convertIntervalToSeconds($data['interval'] ?? 'monthly'),
            'BOLT11Expiration' => 30 * 24 * 60 * 60, // 30 days
            'autoApproveClaims' => false,
            'startsAt' => $data['start_date'] ?? null,
            'expiresAt' => $data['end_date'] ?? null,
        ];

        return $this->makeRequest(
            "/api/v1/stores/{$this->storeId}/pull-payments",
            $pullPaymentData,
            'POST'
        );
    }

    /**
     * Cancel subscription
     */
    public function cancelSubscription(string $subscriptionId): array
    {
        // Archive the pull payment (BTCPay's subscription equivalent)
        return $this->makeRequest(
            "/api/v1/stores/{$this->storeId}/pull-payments/{$subscriptionId}",
            ['archived' => true],
            'PUT'
        );
    }

    /**
     * Transfer funds (payout)
     */
    public function transfer(array $data): array
    {
        $payoutData = [
            'destination' => $data['recipient'], // Crypto address
            'amount' => (string) $data['amount'],
            'paymentMethod' => $data['payment_method'] ?? 'BTC',
            'metadata' => array_merge([
                'reason' => $data['reason'] ?? 'Transfer',
            ], $data['metadata'] ?? []),
        ];

        return $this->makeRequest(
            "/api/v1/stores/{$this->storeId}/payouts",
            $payoutData,
            'POST'
        );
    }

    /**
     * Withdraw to external wallet
     */
    public function withdraw(array $data): array
    {
        // Create an on-chain withdrawal
        $withdrawalData = [
            'destinations' => [
                [
                    'destination' => $data['address'],
                    'amount' => (string) $data['amount'],
                ],
            ],
            'paymentMethod' => $data['crypto_currency'] ?? 'BTC',
            'feerate' => $data['fee_rate'] ?? null,
            'metadata' => [
                'narration' => $data['narration'] ?? 'Withdrawal',
            ],
        ];

        return $this->makeRequest(
            "/api/v1/stores/{$this->storeId}/payouts",
            $withdrawalData,
            'POST'
        );
    }

    /**
     * Refund transaction
     */
    public function refund(array $data): array
    {
        $refundData = [
            'name' => 'Refund for ' . $data['transaction_id'],
            'description' => $data['reason'] ?? 'Refund',
            'refundVariant' => 'CurrentRate', // or 'RateThen', 'Fiat'
        ];

        if (isset($data['amount'])) {
            $refundData['customAmount'] = (string) $data['amount'];
        }

        return $this->makeRequest(
            "/api/v1/stores/{$this->storeId}/invoices/{$data['transaction_id']}/refund",
            $refundData,
            'POST'
        );
    }

    /**
     * Verify transaction
     */
    public function verify(string $reference): array
    {
        return $this->getTransaction($reference);
    }

    /**
     * Get transaction details
     */
    public function getTransaction(string $transactionId): array
    {
        return $this->makeRequest(
            "/api/v1/stores/{$this->storeId}/invoices/{$transactionId}",
            [],
            'GET'
        );
    }

    /**
     * List transactions
     */
    public function listTransactions(array $filters): array
    {
        $params = [
            'skip' => $filters['skip'] ?? 0,
            'take' => $filters['limit'] ?? 50,
            'status' => $filters['status'] ?? null,
            'startDate' => $filters['start_date'] ?? null,
            'endDate' => $filters['end_date'] ?? null,
            'orderId' => $filters['order_id'] ?? null,
        ];

        $query = http_build_query(array_filter($params));

        return $this->makeRequest(
            "/api/v1/stores/{$this->storeId}/invoices?{$query}",
            [],
            'GET'
        );
    }

    /**
     * Get wallet balance
     */
    public function getBalance(): array
    {
        $wallets = $this->makeRequest(
            "/api/v1/stores/{$this->storeId}/payment-methods/onchain",
            [],
            'GET'
        );

        $balances = [];
        foreach ($wallets as $wallet) {
            $balance = $this->makeRequest(
                "/api/v1/stores/{$this->storeId}/payment-methods/onchain/{$wallet['cryptoCode']}/wallet",
                [],
                'GET'
            );
            $balances[$wallet['cryptoCode']] = $balance;
        }

        return $balances;
    }

    /**
     * Create recipient (not applicable for crypto, returns address validation)
     */
    public function createRecipient(array $data): array
    {
        // For crypto, we just validate the address format
        // BTCPay doesn't have a recipient management system
        return [
            'recipient_code' => $data['address'],
            'type' => $data['type'] ?? 'crypto',
            'name' => $data['name'] ?? 'Crypto Recipient',
            'crypto_currency' => $data['crypto_currency'] ?? 'BTC',
            'address' => $data['address'],
        ];
    }

    /**
     * Verify webhook signature
     */
    public function verifyWebhookSignature(array $payload): bool
    {
        if (empty($this->webhookSecret)) {
            return true; // If no secret is configured, skip verification
        }

        $signature = $_SERVER['HTTP_BTCPAY_SIG'] ?? '';

        if (empty($signature)) {
            return false;
        }

        // BTCPay uses HMAC SHA256
        $expectedSignature = 'sha256=' . hash_hmac(
            'sha256',
            json_encode($payload),
            $this->webhookSecret
        );

        return hash_equals($expectedSignature, $signature);
    }

    /**
     * Process webhook
     */
    public function processWebhook(array $payload): array
    {
        $event = $payload['type'] ?? '';
        $invoiceId = $payload['invoiceId'] ?? '';

        // Map BTCPay webhook events
        $eventMap = [
            'InvoiceCreated' => 'created',
            'InvoiceReceivedPayment' => 'payment_received',
            'InvoicePaidInFull' => 'paid',
            'InvoiceExpired' => 'expired',
            'InvoiceSettled' => 'settled',
            'InvoiceInvalid' => 'invalid',
        ];

        return [
            'event' => $eventMap[$event] ?? $event,
            'invoice_id' => $invoiceId,
            'data' => $payload,
        ];
    }

    /**
     * Make HTTP request to BTCPay API
     */
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
        $error = curl_error($ch);
        curl_close($ch);

        if ($error) {
            throw new Exception("cURL Error: {$error}");
        }

        $result = json_decode($response, true);

        if ($httpCode >= 400) {
            $errorMsg = $result['message'] ?? $result['error'] ?? 'Request failed';

            throw new Exception("BTCPay Error ({$httpCode}): {$errorMsg}");
        }

        return $result;
    }

    /**
     * Convert interval string to seconds
     */
    private function convertIntervalToSeconds(string $interval): int
    {
        $intervals = [
            'daily' => 86400,
            'weekly' => 604800,
            'monthly' => 2592000,
            'yearly' => 31536000,
        ];

        return $intervals[strtolower($interval)] ?? 2592000; // Default to monthly
    }
}
