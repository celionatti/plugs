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
    /**
     * @param array $data
     * @return array
     */
    public function charge(array $data): array;

    /**
     * @param array $data
     * @return array
     */
    public function createSubscription(array $data): array;

    /**
     * @param string $subscriptionId
     * @return array
     */
    public function cancelSubscription(string $subscriptionId): array;

    /**
     * @param array $data
     * @return array
     */
    public function transfer(array $data): array;

    /**
     * @param array $data
     * @return array
     */
    public function withdraw(array $data): array;

    /**
     * @param array $data
     * @return array
     */
    public function refund(array $data): array;

    /**
     * @param string $reference
     * @return array
     */
    public function verify(string $reference): array;

    /**
     * @param string $transactionId
     * @return array
     */
    public function getTransaction(string $transactionId): array;

    /**
     * @param array $filters
     * @return array
     */
    public function listTransactions(array $filters): array;

    /**
     * @return array
     */
    public function getBalance(): array;

    /**
     * @param array $data
     * @return array
     */
    public function createRecipient(array $data): array;

    /**
     * @param array $payload
     * @return bool
     */
    public function verifyWebhookSignature(array $payload): bool;

    /**
     * @param array $payload
     * @return array
     */
    public function processWebhook(array $payload): array;
}
