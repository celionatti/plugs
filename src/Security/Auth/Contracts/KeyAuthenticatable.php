<?php

declare(strict_types=1);

namespace Plugs\Security\Auth\Contracts;

use Plugs\Security\Auth\Authenticatable;

/**
 * Interface KeyAuthenticatable
 *
 * Extends Authenticatable for users that support key-based
 * (passwordless) identity authentication using Ed25519 keypairs.
 */
interface KeyAuthenticatable extends Authenticatable
{
    /**
     * Get the user's stored public key (base64-encoded).
     *
     * @return string|null
     */
    public function getPublicKey(): ?string;

    /**
     * Set the user's public key (base64-encoded).
     *
     * @param string $publicKey
     * @return void
     */
    public function setPublicKey(string $publicKey): void;

    /**
     * Get the prompt IDs used for key derivation.
     * Returns an array of prompt identifiers (not the answers).
     *
     * @return array
     */
    public function getPromptIds(): array;

    /**
     * Set the prompt IDs used for key derivation.
     *
     * @param array $promptIds
     * @return void
     */
    public function setPromptIds(array $promptIds): void;

    /**
     * Get the email address associated with the identity.
     *
     * @return string
     */
    public function getEmail(): string;
}
