<?php

declare(strict_types=1);

namespace Plugs\Payout\Drivers;

use Plugs\Http\Request;
use Plugs\Payout\Contracts\PayoutDriverInterface;
use Plugs\Payout\DTO\TransferResponse;
use Plugs\Payout\DTO\WithdrawResponse;
use Plugs\Payout\DTO\PayoutVerification;
use Psr\Log\LoggerInterface;

class LoggingPayoutDriver implements PayoutDriverInterface
{
    protected PayoutDriverInterface $driver;
    protected LoggerInterface $logger;

    public function __construct(PayoutDriverInterface $driver, LoggerInterface $logger)
    {
        $this->driver = $driver;
        $this->logger = $logger;
    }

    public function transfer(array $payload): TransferResponse
    {
        $this->log('Initiating transfer', $payload);
        $response = $this->driver->transfer($payload);
        $this->log('Transfer response', (array) $response);
        return $response;
    }

    public function withdraw(array $payload): WithdrawResponse
    {
        $this->log('Initiating withdrawal', $payload);
        $response = $this->driver->withdraw($payload);
        $this->log('Withdrawal response', (array) $response);
        return $response;
    }

    public function getBalance(): array
    {
        return $this->driver->getBalance();
    }

    public function createRecipient(array $data): array
    {
        return $this->driver->createRecipient($data);
    }

    public function deleteRecipient(string $recipientCode): bool
    {
        return $this->driver->deleteRecipient($recipientCode);
    }

    public function verify(string $reference): PayoutVerification
    {
        return $this->driver->verify($reference);
    }

    public function webhook(array $payload): void
    {
        $this->log('Incoming payout webhook', $payload);
        $this->driver->webhook($payload);
    }

    public function verifyWebhookSignature(Request $request): bool
    {
        return $this->driver->verifyWebhookSignature($request);
    }

    protected function log(string $message, array $context): void
    {
        $this->logger->info("[Payout] {$message}", $context);
    }
}
