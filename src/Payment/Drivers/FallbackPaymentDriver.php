<?php

declare(strict_types=1);

namespace Plugs\Payment\Drivers;

use Plugs\Http\Request;
use Plugs\Payment\Contracts\PaymentDriverInterface;
use Plugs\Payment\DTO\PaymentResponse;
use Plugs\Payment\DTO\PaymentVerification;
use Plugs\Payment\DTO\RefundResponse;
use Plugs\Payment\PaymentManager;
use RuntimeException;
use Throwable;

class FallbackPaymentDriver implements PaymentDriverInterface
{
    /**
     * @var array
     */
    protected array $drivers;

    /**
     * @var PaymentManager
     */
    protected PaymentManager $manager;

    /**
     * @param PaymentManager $manager
     * @param array $drivers
     * @throws \InvalidArgumentException
     */
    public function __construct(PaymentManager $manager, array $drivers)
    {
        if (empty($drivers)) {
            throw new \InvalidArgumentException('Fallback driver configuration requires at least one gateway.');
        }

        $this->manager = $manager;
        $this->drivers = $drivers;
    }

    /**
     * @inheritDoc
     */
    public function initialize(array $payload): PaymentResponse
    {
        $exceptions = [];

        foreach ($this->drivers as $driverName) {
            try {
                $driver = $this->manager->driver($driverName);
                // Gateway specific failure statuses should ideally throw an Exception so we can catch it here
                return $driver->initialize($payload);
            } catch (Throwable $e) {
                $exceptions[] = "[{$driverName}]: " . $e->getMessage();
                // Continue to next driver
            }
        }

        throw new RuntimeException('All fallback gateways failed to initialize. Details: ' . implode(' | ', $exceptions));
    }

    /**
     * @inheritDoc
     */
    public function verify(string $reference): PaymentVerification
    {
        $exceptions = [];

        foreach ($this->drivers as $driverName) {
            try {
                $driver = $this->manager->driver($driverName);
                return $driver->verify($reference);
            } catch (Throwable $e) {
                $exceptions[] = "[{$driverName}]: " . $e->getMessage();
            }
        }

        throw new RuntimeException('All fallback gateways failed to verify. Details: ' . implode(' | ', $exceptions));
    }

    /**
     * @inheritDoc
     */
    public function refund(string $reference, float $amount): RefundResponse
    {
        $exceptions = [];

        foreach ($this->drivers as $driverName) {
            try {
                $driver = $this->manager->driver($driverName);
                return $driver->refund($reference, $amount);
            } catch (Throwable $e) {
                $exceptions[] = "[{$driverName}]: " . $e->getMessage();
            }
        }

        throw new RuntimeException('All fallback gateways failed to refund. Details: ' . implode(' | ', $exceptions));
    }

    /**
     * @inheritDoc
     */
    public function webhook(array $payload): void
    {
        throw new RuntimeException('Webhook handling is not supported directly by the Fallback Gateway. Point webhooks to the specific Gateway.');
    }

    /**
     * @inheritDoc
     */
    public function verifyWebhookSignature(Request $request): bool
    {
        throw new RuntimeException('Webhook handling is not supported directly by the Fallback Gateway.');
    }
}
