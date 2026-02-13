<?php

declare(strict_types=1);

namespace Plugs\Exceptions;

/*
|--------------------------------------------------------------------------
| Encryption Exception
|--------------------------------------------------------------------------
|
| Thrown when an encryption or decryption operation fails. This covers
| invalid keys, corrupted payloads, and tampered data.
*/

class EncryptionException extends PlugsException
{
    /**
     * Create a new encryption exception.
     *
     * @param string $message
     * @param \Throwable|null $previous
     */
    public function __construct(string $message = 'Encryption operation failed.', ?\Throwable $previous = null)
    {
        parent::__construct($message, 0, $previous);
    }

    /**
     * Create an exception for an invalid encryption key.
     *
     * @param string $reason
     * @return static
     */
    public static function invalidKey(string $reason = 'Encryption key must be 32 bytes'): static
    {
        return new static($reason);
    }

    /**
     * Create an exception for a failed encryption attempt.
     *
     * @return static
     */
    public static function encryptionFailed(): static
    {
        return new static('Encryption failed');
    }

    /**
     * Create an exception for a failed decryption attempt.
     *
     * @param string $reason
     * @return static
     */
    public static function decryptionFailed(string $reason = 'Decryption failed — data may be tampered'): static
    {
        return new static($reason);
    }

    /**
     * Create an exception for invalid encrypted data.
     *
     * @param string $reason
     * @return static
     */
    public static function invalidPayload(string $reason = 'Invalid encrypted data'): static
    {
        return new static($reason);
    }
}
