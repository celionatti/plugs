<?php

declare(strict_types=1);

namespace Plugs\TransactionHandler\Adapter;

use Plugs\TransactionHandler\PaymentAdapterInterface;

/*
| -----------------------------------------------------------------------
| PayPal Adapter
| -----------------------------------------------------------------------
*/

class PayPalAdapter implements PaymentAdapterInterface
{
    public function __construct()
    {
    }

    public function charge(array $data): array
    {
        return [];
    }

    public function createSubscription(array $data): array
    {
        return [];
    }

    public function cancelSubscription(string $subscriptionId): array
    {
        return [];
    }

    public function transfer(array $data): array
    {
        return [];
    }

    public function withdraw(array $data): array
    {
        return [];
    }

    public function refund(array $data): array
    {
        return [];
    }

    public function verify(string $reference): array
    {
        return [];
    }

    public function getTransaction(string $transactionId): array
    {
        return [];
    }

    public function listTransactions(array $filters): array
    {
        return [];
    }

    public function getBalance(): array
    {
        return [];
    }

    public function createRecipient(array $data): array
    {
        return [];
    }

    public function verifyWebhookSignature(array $payload): bool
    {
        return true;
    }

    public function processWebhook(array $payload): array
    {
        return $payload;
    }
}
