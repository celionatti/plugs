<?php

declare(strict_types=1);

namespace Plugs\Payout\Contracts;

use Plugs\Http\Request;
use Plugs\Payout\DTO\TransferResponse;
use Plugs\Payout\DTO\WithdrawResponse;
use Plugs\Payout\DTO\PayoutVerification;

interface PayoutDriverInterface
{
    /**
     * Transfer funds to an external account or wallet.
     *
     * @param array $payload
     * @return TransferResponse
     */
    public function transfer(array $payload): TransferResponse;

    /**
     * Withdraw funds from the platform to the gateway/bank.
     *
     * @param array $payload
     * @return WithdrawResponse
     */
    public function withdraw(array $payload): WithdrawResponse;

    /**
     * Get the current balance of the gateway account.
     *
     * @return array
     */
    public function getBalance(): array;

    /**
     * Create a recipient/beneficiary for future transfers.
     *
     * @param array $data
     * @return array
     */
    public function createRecipient(array $data): array;

    /**
     * Delete a recipient/beneficiary.
     *
     * @param string $recipientCode
     * @return bool
     */
    public function deleteRecipient(string $recipientCode): bool;

    /**
     * Verify the status of an outgoing transfer/withdrawal.
     *
     * @param string $reference
     * @return PayoutVerification
     */
    public function verify(string $reference): PayoutVerification;

    /**
     * Handle incoming webhooks for payout status updates.
     *
     * @param array $payload
     * @return void
     */
    public function webhook(array $payload): void;

    /**
     * Verify the incoming webhook signature cryptographically.
     *
     * @param Request $request
     * @return bool
     * @throws \Plugs\Payment\Exceptions\InvalidSignatureException
     */
    public function verifyWebhookSignature(Request $request): bool;
}
