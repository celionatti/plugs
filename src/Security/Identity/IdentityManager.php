<?php

declare(strict_types=1);

namespace Plugs\Security\Identity;

use Plugs\Event\DispatcherInterface;
use Plugs\Security\Auth\Authenticatable;
use Plugs\Security\Auth\Contracts\KeyAuthenticatable;
use Plugs\Security\Auth\Events\IdentityRecovered;
use Plugs\Security\Auth\Events\IdentityRegistered;
use RuntimeException;

/**
 * IdentityManager
 *
 * Orchestrates the registration, login, and recovery flows for the
 * key-based (passwordless) identity system.
 *
 * This manager coordinates between:
 * - KeyDerivationService (KDF + signing)
 * - User model (public key storage)
 * - Event dispatcher (lifecycle events)
 */
class IdentityManager
{
    protected KeyDerivationService $keyService;
    protected NonceService $nonceService;
    protected ?DispatcherInterface $events;

    /**
     * The model class for users.
     */
    protected string $userModel;

    public function __construct(
        KeyDerivationService $keyService,
        NonceService $nonceService,
        ?DispatcherInterface $events = null,
        ?string $userModel = null,
    ) {
        $this->keyService = $keyService;
        $this->nonceService = $nonceService;
        $this->events = $events;
        $this->userModel = $userModel ?? config('auth.identity.model', 'App\\Models\\User');
    }

    /**
     * Register a new key-based identity.
     *
     * 1. Validates passphrase entropy
     * 2. Derives Ed25519 keypair from email + passphrase
     * 3. Stores only the public key on the user record
     * 4. Fires IdentityRegistered event
     *
     * @param string $email
     * @param string $passphrase
     * @param array $promptIds Optional prompt identifiers (for UI reconstruction)
     * @param array $additionalData Extra user fields to store (e.g. name)
     * @return KeyAuthenticatable The created user
     * @throws RuntimeException If passphrase is too weak
     */
    public function register(
        string $email,
        string $passphrase,
        array $promptIds = [],
        array $additionalData = [],
    ): KeyAuthenticatable {
        // Validate passphrase entropy
        $validation = $this->keyService->validatePassphraseEntropy($passphrase);
        if (!$validation['valid']) {
            throw new RuntimeException(
                'Passphrase does not meet entropy requirements: ' . implode(' ', $validation['errors'])
            );
        }

        // Derive the public key (private key is derived and immediately wiped)
        $publicKey = $this->keyService->derivePublicKey($email, $passphrase);
        $publicKeyEncoded = base64_encode($publicKey);

        // Create the user record
        if (!class_exists($this->userModel)) {
            throw new RuntimeException("User model [{$this->userModel}] does not exist.");
        }

        $userData = array_merge($additionalData, [
            'email' => mb_strtolower(trim($email)),
            'public_key' => $publicKeyEncoded,
        ]);

        if (!empty($promptIds)) {
            $userData['prompt_ids'] = json_encode($promptIds);
        }

        /** @var KeyAuthenticatable $user */
        $user = null;

        if (method_exists($this->userModel, 'create')) {
            $user = $this->userModel::create($userData);
        } else {
            throw new RuntimeException("User model [{$this->userModel}] must support create().");
        }

        if (!$user instanceof KeyAuthenticatable) {
            throw new RuntimeException("User model [{$this->userModel}] must implement KeyAuthenticatable.");
        }

        // Fire event
        $this->fireEvent(new IdentityRegistered($user, $publicKeyEncoded));

        // Wipe public key material
        sodium_memzero($publicKey);

        return $user;
    }

    /**
     * Verify a user's identity (used internally by KeyGuard).
     *
     * @param string $email
     * @param string $passphrase
     * @return KeyAuthenticatable|null The authenticated user, or null on failure
     */
    public function verify(string $email, string $passphrase): ?KeyAuthenticatable
    {
        $email = mb_strtolower(trim($email));

        if (!class_exists($this->userModel) || !method_exists($this->userModel, 'where')) {
            return null;
        }

        $user = $this->userModel::where('email', '=', $email)->first();

        if (!$user instanceof KeyAuthenticatable || !$user->getPublicKey()) {
            return null;
        }

        // Derive keys and compare public key
        $keyPair = $this->keyService->deriveKeyPair($email, $passphrase);
        $derivedPublicKey = base64_encode($keyPair['publicKey']);

        $isValid = hash_equals($user->getPublicKey(), $derivedPublicKey);

        // Wipe sensitive material
        sodium_memzero($keyPair['privateKey']);
        sodium_memzero($keyPair['seed']);

        return $isValid ? $user : null;
    }

    /**
     * Recover an identity by regenerating the keypair.
     *
     * This should only be called after the user has proven their identity
     * through an alternative mechanism (e.g. email verification link).
     *
     * @param Authenticatable $user The user whose identity should be recovered
     * @param string $newPassphrase The new passphrase to derive keys from
     * @param array $newPromptIds Optional new prompt identifiers
     * @return void
     * @throws RuntimeException If passphrase is too weak
     */
    public function recover(
        Authenticatable $user,
        string $newPassphrase,
        array $newPromptIds = [],
    ): void {
        if (!$user instanceof KeyAuthenticatable) {
            throw new RuntimeException('User must implement KeyAuthenticatable for identity recovery.');
        }

        // Validate entropy
        $validation = $this->keyService->validatePassphraseEntropy($newPassphrase);
        if (!$validation['valid']) {
            throw new RuntimeException(
                'Passphrase does not meet entropy requirements: ' . implode(' ', $validation['errors'])
            );
        }

        $email = method_exists($user, 'getEmail')
            ? $user->getEmail()
            : (property_exists($user, 'email') ? $user->email : '');

        // Derive new public key
        $publicKey = $this->keyService->derivePublicKey($email, $newPassphrase);
        $publicKeyEncoded = base64_encode($publicKey);

        // Update user record
        $user->setPublicKey($publicKeyEncoded);

        if (!empty($newPromptIds)) {
            $user->setPromptIds($newPromptIds);
        }

        if (method_exists($user, 'save')) {
            $user->save();
        }

        // Fire event
        $this->fireEvent(new IdentityRecovered($user, $publicKeyEncoded));

        // Wipe key material
        sodium_memzero($publicKey);
    }

    /**
     * Get a nonce challenge for key-based authentication.
     */
    public function challenge(string $email): string
    {
        return $this->nonceService->generate(mb_strtolower(trim($email)));
    }

    /**
     * Get the KeyDerivationService.
     */
    public function getKeyService(): KeyDerivationService
    {
        return $this->keyService;
    }

    /**
     * Get the NonceService.
     */
    public function getNonceService(): NonceService
    {
        return $this->nonceService;
    }

    protected function fireEvent(object $event): void
    {
        $this->events?->dispatch($event);
    }
}
