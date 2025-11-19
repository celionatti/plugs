<?php

declare(strict_types=1);

namespace Plugs\TransactionHandler;

/*
| -----------------------------------------------------------------------
| PaymentAdapterInterface
| -----------------------------------------------------------------------
*/

interface PaymentAdapterInterface
{
    public function charge(array $data);
    public function createSubscription(array $data);
    public function cancelSubscription(string $subscriptionId);
    public function transfer(array $data);
    public function withdraw(array $data);
    public function refund(array $data);
    public function verify(string $reference);
    public function getTransaction(string $transactionId);
    public function listTransactions(array $filters);
    public function getBalance();
    public function createRecipient(array $data);
    public function verifyWebhookSignature(array $payload): bool;
    public function processWebhook(array $payload);
}