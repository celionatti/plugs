<?php

declare(strict_types=1);

namespace Plugs\Security\Auth\Guards;

use Plugs\Event\DispatcherInterface;
use Plugs\Security\Auth\Authenticatable;
use Plugs\Security\Auth\Contracts\KeyAuthenticatable;
use Plugs\Security\Auth\Contracts\UserProviderInterface;
use Plugs\Security\Auth\Events\AuthFailed;
use Plugs\Security\Auth\Events\IdentityAuthenticated;
use Plugs\Security\Auth\GuardInterface;
use Plugs\Security\Identity\KeyDerivationService;
use Plugs\Security\Identity\NonceService;

/**
 * KeyGuard
 *
 * Passwordless guard using Ed25519 keypair challenge-response authentication.
 *
 * Login flow:
 * 1. User submits email + passphrase/answers
 * 2. Guard derives key pair from inputs using KeyDerivationService
 * 3. Server generates a nonce challenge
 * 4. Guard signs the nonce with the derived private key
 * 5. Server verifies the signature against the user's stored public key
 * 6. Fires IdentityAuthenticated on success or AuthFailed on failure
 *
 * No passwords are stored. No private keys are stored.
 */
class KeyGuard implements GuardInterface
{
    protected string $name;
    protected UserProviderInterface $provider;
    protected KeyDerivationService $keyService;
    protected NonceService $nonceService;
    protected ?DispatcherInterface $events;
    protected ?Authenticatable $user = null;

    public function __construct(
        string $name,
        UserProviderInterface $provider,
        KeyDerivationService $keyService,
        NonceService $nonceService,
        ?DispatcherInterface $events = null,
    ) {
        $this->name = $name;
        $this->provider = $provider;
        $this->keyService = $keyService;
        $this->nonceService = $nonceService;
        $this->events = $events;
    }

    // -------------------------------------------------------------------------
    // GuardInterface Implementation
    // -------------------------------------------------------------------------

    /**
     * {@inheritDoc}
     */
    public function check(): bool
    {
        return !is_null($this->user);
    }

    /**
     * {@inheritDoc}
     */
    public function guest(): bool
    {
        return !$this->check();
    }

    /**
     * {@inheritDoc}
     */
    public function user(): ?Authenticatable
    {
        return $this->user;
    }

    /**
     * {@inheritDoc}
     */
    public function id()
    {
        return $this->user?->getAuthIdentifier();
    }

    /**
     * For key-based auth, validate() performs the full challenge-response.
     *
     * Expected credentials:
     * - 'email'      => string
     * - 'passphrase' => string (user's secret answers/passphrase)
     *
     * @param array $credentials
     * @return bool
     */
    public function validate(array $credentials = []): bool
    {
        if (!isset($credentials['email'], $credentials['passphrase'])) {
            return false;
        }

        $user = $this->provider->retrieveByCredentials($credentials);

        if (!$user instanceof KeyAuthenticatable || !$user->getPublicKey()) {
            return false;
        }

        return $this->verifyIdentity($user, $credentials['email'], $credentials['passphrase']);
    }

    /**
     * Attempt to authenticate using key-based identity.
     *
     * Expected credentials:
     * - 'email'      => string
     * - 'passphrase' => string
     *
     * @param array $credentials
     * @param bool $remember (ignored for key-based auth)
     * @return bool
     */
    public function attempt(array $credentials = [], bool $remember = false): bool
    {
        if (!isset($credentials['email'], $credentials['passphrase'])) {
            $this->fireEvent(new AuthFailed($this->name, $credentials));
            return false;
        }

        $user = $this->provider->retrieveByCredentials($credentials);

        if (!$user instanceof KeyAuthenticatable || !$user->getPublicKey()) {
            $this->fireEvent(new AuthFailed($this->name, $credentials));
            return false;
        }

        if ($this->verifyIdentity($user, $credentials['email'], $credentials['passphrase'])) {
            $this->login($user);
            $this->fireEvent(new IdentityAuthenticated($user, $this->name));

            return true;
        }

        $this->fireEvent(new AuthFailed($this->name, ['email' => $credentials['email']]));

        return false;
    }

    /**
     * {@inheritDoc}
     */
    public function login(Authenticatable $user, bool $remember = false): void
    {
        $this->user = $user;
    }

    /**
     * {@inheritDoc}
     */
    public function logout(): void
    {
        $this->user = null;
    }

    /**
     * {@inheritDoc}
     */
    public function setUser(Authenticatable $user): void
    {
        $this->user = $user;
    }

    // -------------------------------------------------------------------------
    // Key-Based Identity Methods
    // -------------------------------------------------------------------------

    /**
     * Generate a nonce challenge for the given identifier.
     *
     * @param string $identifier (e.g. email)
     * @return string The nonce
     */
    public function challenge(string $identifier): string
    {
        return $this->nonceService->generate($identifier);
    }

    /**
     * Authenticate using a pre-signed nonce (advanced/manual flow).
     *
     * The client has already derived the private key client-side and signed the nonce.
     *
     * @param string $email
     * @param string $signature Base64-encoded Ed25519 signature
     * @param string $nonce The challenge nonce
     * @return bool
     */
    public function authenticateWithSignature(string $email, string $signature, string $nonce): bool
    {
        // Validate nonce
        if (!$this->nonceService->validate($email, $nonce)) {
            return false;
        }

        // Find user
        $user = $this->provider->retrieveByCredentials(['email' => $email]);

        if (!$user instanceof KeyAuthenticatable || !$user->getPublicKey()) {
            return false;
        }

        // Verify the signature
        $publicKeyBin = base64_decode($user->getPublicKey());

        if ($this->keyService->verifySignature($publicKeyBin, $signature, $nonce)) {
            $this->login($user);
            $this->fireEvent(new IdentityAuthenticated($user, $this->name));

            return true;
        }

        $this->fireEvent(new AuthFailed($this->name, ['email' => $email]));

        return false;
    }

    /**
     * Get the guard name.
     */
    public function getGuardName(): string
    {
        return $this->name;
    }

    /**
     * Get the user provider.
     */
    public function getProvider(): UserProviderInterface
    {
        return $this->provider;
    }

    // -------------------------------------------------------------------------
    // Internal Helpers
    // -------------------------------------------------------------------------

    /**
     * Perform the full server-side challenge-response verification.
     *
     * 1. Derive keypair from email + passphrase
     * 2. Generate a nonce
     * 3. Sign the nonce with the derived private key
     * 4. Verify the signature against the stored public key
     *
     * This method is used when the server has access to the passphrase
     * (e.g. form-based login). For client-side key derivation, use
     * authenticateWithSignature() instead.
     */
    protected function verifyIdentity(KeyAuthenticatable $user, string $email, string $passphrase): bool
    {
        // Derive keypair from the user's inputs
        $keyPair = $this->keyService->deriveKeyPair($email, $passphrase);

        $derivedPublicKey = base64_encode($keyPair['publicKey']);
        $storedPublicKey = $user->getPublicKey();

        // Fast-path: compare derived public key to stored public key
        if (!hash_equals($storedPublicKey, $derivedPublicKey)) {
            // Wipe sensitive material
            sodium_memzero($keyPair['privateKey']);
            return false;
        }

        // Full verification: sign a nonce and verify
        $nonce = $this->nonceService->generate($email);
        $signature = $this->keyService->signChallenge($keyPair['privateKey'], $nonce);

        // Wipe the private key from memory immediately
        sodium_memzero($keyPair['privateKey']);

        return $this->keyService->verifySignature($keyPair['publicKey'], $signature, $nonce);
    }

    protected function fireEvent(object $event): void
    {
        $this->events?->dispatch($event);
    }
}
