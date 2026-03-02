<?php

declare(strict_types=1);

namespace Plugs\Payout\DTO;

class TransferResponse
{
    public string $reference;
    public string $status;
    public float $amount;
    public string $currency;
    public ?string $message;
    public array $metadata;

    /**
     * Create a new TransferResponse instance.
     *
     * @param string $reference
     * @param string $status
     * @param float $amount
     * @param string $currency
     * @param string|null $message
     * @param array $metadata
     */
    public function __construct(
        string $reference,
        string $status,
        float $amount,
        string $currency,
        ?string $message = null,
        array $metadata = []
    ) {
        $this->reference = $reference;
        $this->status = $status;
        $this->amount = $amount;
        $this->currency = $currency;
        $this->message = $message;
        $this->metadata = $metadata;
    }

    /**
     * Determine if the transfer is pending.
     *
     * @return bool
     */
    public function isPending(): bool
    {
        return $this->status === 'pending';
    }

    /**
     * Determine if the transfer was successful.
     *
     * @return bool
     */
    public function isSuccessful(): bool
    {
        return $this->status === 'success' || $this->status === 'successful';
    }

    /**
     * Determine if the transfer failed.
     *
     * @return bool
     */
    public function isFailed(): bool
    {
        return $this->status === 'failed';
    }
}
