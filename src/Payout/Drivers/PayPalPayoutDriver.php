<?php

declare(strict_types=1);

namespace Plugs\Payout\Drivers;

use Plugs\Http\Request;
use Plugs\Payout\Contracts\PayoutDriverInterface;
use Plugs\Payout\DTO\TransferResponse;
use Plugs\Payout\DTO\WithdrawResponse;
use Plugs\Payout\DTO\PayoutVerification;

class PayPalPayoutDriver implements PayoutDriverInterface
{
    public function __construct(array $config = [])
    {
    }

    public function transfer(array $payload): TransferResponse
    {
        return new TransferResponse('', 'pending', 0, 'USD', 'Stubbed', []);
    }

    public function withdraw(array $payload): WithdrawResponse
    {
        return new WithdrawResponse('', 'pending', 0, 'USD', 'Stubbed', []);
    }

    public function getBalance(): array
    {
        return [];
    }

    public function createRecipient(array $data): array
    {
        return [];
    }

    public function verify(string $reference): PayoutVerification
    {
        return new PayoutVerification($reference, 'pending', 0, 'USD', 'Stubbed', []);
    }

    public function webhook(array $payload): void
    {
    }

    public function verifyWebhookSignature(Request $request): bool
    {
        return true;
    }
}
