<?php

declare(strict_types=1);

namespace Plugs\TransactionHandler\Adapter;

use Exception;
use Plugs\TransactionHandler\PaymentAdapterInterface;

/*
| -----------------------------------------------------------------------
| Paystack Adapter
| -----------------------------------------------------------------------
*/

class PaystackAdapter implements PaymentAdapterInterface
{
    private $secretKey;
    private $baseUrl = 'https://api.paystack.co';

    public function __construct(array $config)
    {
        $this->secretKey = $config['secret_key'];
    }

    public function charge(array $data)
    {
        return $this->makeRequest('/transaction/initialize', $data);
    }

    public function createSubscription(array $data)
    {
        return $this->makeRequest('/subscription', $data);
    }

    public function cancelSubscription(string $subscriptionId)
    {
        return $this->makeRequest("/subscription/disable", [
            'code' => $subscriptionId,
            'token' => $subscriptionId,
        ], 'POST');
    }

    public function transfer(array $data)
    {
        return $this->makeRequest('/transfer', $data);
    }

    public function withdraw(array $data)
    {
        return $this->makeRequest('/transfer', $data);
    }

    public function refund(array $data)
    {
        return $this->makeRequest('/refund', $data);
    }

    public function verify(string $reference)
    {
        return $this->makeRequest("/transaction/verify/{$reference}", [], 'GET');
    }

    public function getTransaction(string $transactionId)
    {
        return $this->makeRequest("/transaction/{$transactionId}", [], 'GET');
    }

    public function listTransactions(array $filters)
    {
        $query = http_build_query($filters);

        return $this->makeRequest("/transaction?{$query}", [], 'GET');
    }

    public function getBalance()
    {
        return $this->makeRequest('/balance', [], 'GET');
    }

    public function createRecipient(array $data)
    {
        return $this->makeRequest('/transferrecipient', $data);
    }

    public function verifyWebhookSignature(array $payload): bool
    {
        $signature = $_SERVER['HTTP_X_PAYSTACK_SIGNATURE'] ?? '';
        $hash = hash_hmac('sha512', json_encode($payload), $this->secretKey);

        return $signature === $hash;
    }

    public function processWebhook(array $payload)
    {
        return $payload;
    }

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

        $result = json_decode($response, true);

        if ($httpCode !== 200 && $httpCode !== 201) {
            throw new Exception($result['message'] ?? 'Request failed');
        }

        return $result['data'] ?? $result;
    }
}
