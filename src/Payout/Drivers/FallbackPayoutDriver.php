<?php

declare(strict_types=1);

namespace Plugs\Payout\Drivers;

use Exception;
use Plugs\Http\Request;
use Plugs\Payout\Contracts\PayoutDriverInterface;
use Plugs\Payout\DTO\TransferResponse;
use Plugs\Payout\DTO\WithdrawResponse;
use Plugs\Payout\DTO\PayoutVerification;
use Plugs\Payout\PayoutManager;

class FallbackPayoutDriver implements PayoutDriverInterface
{
    protected PayoutManager $manager;
    protected array $drivers;

    public function __construct(PayoutManager $manager, array $drivers)
    {
        $this->manager = $manager;
        $this->drivers = $drivers;
    }

    public function transfer(array $payload): TransferResponse
    {
        return $this->runSequence('transfer', [$payload]);
    }

    public function withdraw(array $payload): WithdrawResponse
    {
        return $this->runSequence('withdraw', [$payload]);
    }

    public function getBalance(): array
    {
        return $this->runSequence('getBalance', []);
    }

    public function createRecipient(array $data): array
    {
        return $this->runSequence('createRecipient', [$data]);
    }

    public function deleteRecipient(string $recipientCode): bool
    {
        return $this->runSequence('deleteRecipient', [$recipientCode]);
    }

    public function verify(string $reference): PayoutVerification
    {
        return $this->runSequence('verify', [$reference]);
    }

    public function webhook(array $payload): void
    {
        // Webhooks are typically handled by a specific driver, not a fallback sequence.
        // However, we'll try the first driver for completeness.
        if (!empty($this->drivers)) {
            $this->manager->driver($this->drivers[0])->webhook($payload);
        }
    }

    public function verifyWebhookSignature(Request $request): bool
    {
        // Webhook signature verification is gateway-specific.
        // Fallback doesn't make much sense here, but we'll try the first driver.
        if (empty($this->drivers)) {
            return false;
        }

        return $this->manager->driver($this->drivers[0])->verifyWebhookSignature($request);
    }

    /**
     * Run a method across the driver sequence until one succeeds.
     */
    protected function runSequence(string $method, array $args)
    {
        $lastException = null;

        foreach ($this->drivers as $driverName) {
            try {
                return $this->manager->driver($driverName)->$method(...$args);
            } catch (Exception $e) {
                $lastException = $e;
            }
        }

        throw $lastException ?: new Exception("No drivers available in fallback sequence.");
    }
}
