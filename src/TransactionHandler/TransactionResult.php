<?php

declare(strict_types=1);

namespace Plugs\TransactionHandler;

use JsonSerializable;

/**
 * Standardized result object for payment transaction operations
 */
class TransactionResult implements JsonSerializable
{
    private bool $success;
    private string $status;
    private string $type;
    private ?string $reference;
    private string $message;
    private array $data;
    private string $timestamp;
    private ?string $platform;

    public function __construct(array $attributes = [])
    {
        $this->success = $attributes['success'] ?? false;
        $this->status = $attributes['status'] ?? 'pending';
        $this->type = $attributes['type'] ?? 'one_time';
        $this->reference = $attributes['reference'] ?? null;
        $this->message = $attributes['message'] ?? '';
        $this->data = $attributes['data'] ?? [];
        $this->timestamp = $attributes['timestamp'] ?? date('Y-m-d H:i:s');
        $this->platform = $attributes['platform'] ?? null;
    }

    /**
     * Create a successful transaction result
     */
    public static function success(array $data, string $type, ?string $platform = null): self
    {
        return new self([
            'success' => true,
            'status' => 'success',
            'type' => $type,
            'data' => $data,
            'platform' => $platform,
            'reference' => $data['reference'] ?? $data['tx_ref'] ?? $data['id'] ?? null,
            'message' => 'Operation successful',
        ]);
    }

    /**
     * Create a failed transaction result
     */
    public static function failed(string $message, string $type, ?string $platform = null, array $data = []): self
    {
        return new self([
            'success' => false,
            'status' => 'failed',
            'type' => $type,
            'message' => $message,
            'data' => $data,
            'platform' => $platform,
        ]);
    }

    public function isSuccessful(): bool
    {
        return $this->success;
    }

    public function getStatus(): string
    {
        return $this->status;
    }

    public function getType(): string
    {
        return $this->type;
    }

    public function getReference(): ?string
    {
        return $this->reference;
    }

    public function getMessage(): string
    {
        return $this->message;
    }

    public function getData(): array
    {
        return $this->data;
    }

    public function getTimestamp(): string
    {
        return $this->timestamp;
    }

    public function getPlatform(): ?string
    {
        return $this->platform;
    }

    /**
     * @inheritDoc
     */
    public function jsonSerialize(): array
    {
        return [
            'success' => $this->success,
            'status' => $this->status,
            'type' => $this->type,
            'reference' => $this->reference,
            'message' => $this->message,
            'data' => $this->data,
            'timestamp' => $this->timestamp,
            'platform' => $this->platform,
        ];
    }

    public function toArray(): array
    {
        return $this->jsonSerialize();
    }
}
