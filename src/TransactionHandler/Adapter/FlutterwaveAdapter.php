<?php

declare(strict_types=1);

namespace Plugs\TransactionHandler\Adapter;

use Exception;
use Plugs\TransactionHandler\PaymentAdapterInterface;

/*
| -----------------------------------------------------------------------
| Flutterwave Adapter
| -----------------------------------------------------------------------
| Complete implementation of Flutterwave payment gateway integration
| Supports payments, transfers, subscriptions, refunds, and webhooks
*/

class FlutterwaveAdapter implements PaymentAdapterInterface
{
    private $secretKey;
    private $publicKey;
    private $encryptionKey;
    private $baseUrl = 'https://api.flutterwave.com/v3';

    public function __construct(array $config)
    {
        $this->secretKey = $config['secret_key'];
        $this->publicKey = $config['public_key'];
        $this->encryptionKey = $config['encryption_key'] ?? '';
    }

    /**
     * Initialize a payment charge
     */
    public function charge(array $data)
    {
        $paymentData = [
            'tx_ref' => $data['reference'] ?? $this->generateReference(),
            'amount' => $data['amount'],
            'currency' => $data['currency'] ?? 'NGN',
            'redirect_url' => $data['callback_url'] ?? '',
            'customer' => [
                'email' => $data['email'],
                'name' => $data['customer_name'] ?? '',
                'phonenumber' => $data['phone'] ?? '',
            ],
            'customizations' => [
                'title' => $data['title'] ?? 'Payment',
                'description' => $data['description'] ?? 'Payment',
                'logo' => $data['logo'] ?? '',
            ],
            'meta' => $data['metadata'] ?? [],
        ];

        return $this->makeRequest('/payments', $paymentData);
    }

    /**
     * Create a subscription plan
     */
    public function createSubscription(array $data)
    {
        // First, create or use existing payment plan
        $planData = [
            'amount' => $data['amount'],
            'name' => $data['plan_name'] ?? 'Subscription Plan',
            'interval' => $data['interval'] ?? 'monthly',
            'duration' => $data['duration'] ?? 0, // 0 means indefinite
            'currency' => $data['currency'] ?? 'NGN',
        ];

        // Create plan
        $plan = $this->makeRequest('/payment-plans', $planData);

        // Subscribe customer to plan
        if (isset($plan['id'])) {
            $subscriptionData = [
                'email' => $data['email'],
                'tx_ref' => $data['reference'] ?? $this->generateReference(),
                'amount' => $data['amount'],
                'currency' => $data['currency'] ?? 'NGN',
                'payment_plan' => $plan['id'],
                'redirect_url' => $data['callback_url'] ?? '',
            ];

            return $this->makeRequest('/payments', $subscriptionData);
        }

        return $plan;
    }

    /**
     * Cancel a subscription
     */
    public function cancelSubscription(string $subscriptionId)
    {
        return $this->makeRequest("/subscriptions/{$subscriptionId}/cancel", [], 'PUT');
    }

    /**
     * Transfer funds to a recipient
     */
    public function transfer(array $data)
    {
        $transferData = [
            'account_bank' => $data['bank_code'] ?? '',
            'account_number' => $data['account_number'] ?? '',
            'amount' => $data['amount'],
            'currency' => $data['currency'] ?? 'NGN',
            'narration' => $data['reason'] ?? 'Transfer',
            'reference' => $data['reference'] ?? $this->generateReference(),
            'callback_url' => $data['callback_url'] ?? '',
            'debit_currency' => $data['currency'] ?? 'NGN',
        ];

        // If recipient code is provided, use it directly
        if (isset($data['recipient'])) {
            $transferData = [
                'account_bank' => $data['recipient'],
                'amount' => $data['amount'],
                'currency' => $data['currency'] ?? 'NGN',
                'narration' => $data['reason'] ?? 'Transfer',
                'reference' => $data['reference'] ?? $this->generateReference(),
            ];
        }

        return $this->makeRequest('/transfers', $transferData);
    }

    /**
     * Withdraw to bank account
     */
    public function withdraw(array $data)
    {
        $withdrawalData = [
            'account_bank' => $data['bank_code'],
            'account_number' => $data['account_number'],
            'amount' => $data['amount'],
            'currency' => $data['currency'] ?? 'NGN',
            'narration' => $data['narration'] ?? 'Withdrawal',
            'reference' => $data['reference'] ?? $this->generateReference(),
            'callback_url' => $data['callback_url'] ?? '',
            'debit_currency' => $data['currency'] ?? 'NGN',
        ];

        return $this->makeRequest('/transfers', $withdrawalData);
    }

    /**
     * Refund a transaction
     */
    public function refund(array $data)
    {
        $refundData = [
            'id' => $data['transaction_id'],
        ];

        if (isset($data['amount'])) {
            $refundData['amount'] = $data['amount'];
        }

        return $this->makeRequest('/transactions/' . $data['transaction_id'] . '/refund', $refundData);
    }

    /**
     * Verify a transaction
     */
    public function verify(string $reference)
    {
        return $this->makeRequest("/transactions/verify_by_reference?tx_ref={$reference}", [], 'GET');
    }

    /**
     * Get transaction details
     */
    public function getTransaction(string $transactionId)
    {
        return $this->makeRequest("/transactions/{$transactionId}/verify", [], 'GET');
    }

    /**
     * List all transactions
     */
    public function listTransactions(array $filters)
    {
        $params = [
            'from' => $filters['start_date'] ?? '',
            'to' => $filters['end_date'] ?? '',
            'page' => $filters['page'] ?? 1,
            'currency' => $filters['currency'] ?? '',
            'status' => $filters['status'] ?? '',
        ];

        $query = http_build_query(array_filter($params));

        return $this->makeRequest("/transactions?{$query}", [], 'GET');
    }

    /**
     * Get account balance
     */
    public function getBalance()
    {
        return $this->makeRequest('/balances', [], 'GET');
    }

    /**
     * Create a transfer recipient
     */
    public function createRecipient(array $data)
    {
        $recipientData = [
            'type' => $data['type'] ?? 'nuban',
            'name' => $data['name'],
            'account_number' => $data['account_number'],
            'bank_code' => $data['bank_code'],
            'currency' => $data['currency'] ?? 'NGN',
            'email' => $data['email'] ?? '',
            'mobile_number' => $data['phone'] ?? '',
            'meta' => $data['metadata'] ?? [],
        ];

        return $this->makeRequest('/beneficiaries', $recipientData);
    }

    /**
     * Verify webhook signature
     */
    public function verifyWebhookSignature(array $payload): bool
    {
        $secretHash = $this->secretKey;
        $signature = $_SERVER['HTTP_VERIF_HASH'] ?? '';

        if (empty($signature)) {
            return false;
        }

        // Flutterwave sends the secret hash in the header
        return hash_equals($secretHash, $signature);
    }

    /**
     * Process webhook payload
     */
    public function processWebhook(array $payload)
    {
        $event = $payload['event'] ?? '';
        $data = $payload['data'] ?? [];

        // Map Flutterwave events
        $eventMap = [
            'charge.completed' => 'payment_successful',
            'transfer.completed' => 'transfer_successful',
            'transfer.failed' => 'transfer_failed',
            'refund.completed' => 'refund_successful',
        ];

        return [
            'event' => $eventMap[$event] ?? $event,
            'transaction_id' => $data['id'] ?? '',
            'reference' => $data['tx_ref'] ?? '',
            'status' => $data['status'] ?? '',
            'data' => $data,
        ];
    }

    /**
     * Make HTTP request to Flutterwave API
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
        $error = curl_error($ch);
        curl_close($ch);

        if ($error) {
            throw new Exception("cURL Error: {$error}");
        }

        $result = json_decode($response, true);

        if ($httpCode !== 200 && $httpCode !== 201) {
            $errorMsg = $result['message'] ?? 'Request failed';

            throw new Exception("Flutterwave Error ({$httpCode}): {$errorMsg}");
        }

        return $result['data'] ?? $result;
    }

    /**
     * Generate unique reference
     */
    private function generateReference(): string
    {
        return 'FLW_' . time() . '_' . uniqid();
    }
}
