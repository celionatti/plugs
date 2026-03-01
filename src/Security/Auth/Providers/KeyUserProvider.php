<?php

declare(strict_types=1);

namespace Plugs\Security\Auth\Providers;

use Plugs\Security\Auth\Authenticatable;
use Plugs\Security\Auth\Contracts\KeyAuthenticatable;
use Plugs\Security\Auth\Contracts\UserProviderInterface;

/**
 * KeyUserProvider
 *
 * User provider for the key-based identity system.
 * Retrieves users by email and verifies via public key — no passwords.
 */
class KeyUserProvider implements UserProviderInterface
{
    /**
     * The model class used for user retrieval.
     *
     * @var string
     */
    protected string $model;

    /**
     * Create a new key user provider.
     *
     * @param string $model Fully-qualified model class name (must implement KeyAuthenticatable)
     */
    public function __construct(string $model)
    {
        $this->model = $model;
    }

    /**
     * {@inheritDoc}
     */
    public function retrieveById(mixed $identifier): ?Authenticatable
    {
        $model = $this->createModel();

        $user = $model::find($identifier);

        return ($user instanceof KeyAuthenticatable) ? $user : null;
    }

    /**
     * {@inheritDoc}
     */
    public function retrieveByToken(mixed $identifier, string $token): ?Authenticatable
    {
        // Key-based auth does not use remember tokens
        return null;
    }

    /**
     * {@inheritDoc}
     */
    public function updateRememberToken(Authenticatable $user, string $token): void
    {
        // Key-based auth does not use remember tokens
    }

    /**
     * {@inheritDoc}
     */
    public function retrieveByCredentials(array $credentials): ?Authenticatable
    {
        if (!isset($credentials['email'])) {
            return null;
        }

        $model = $this->createModel();
        $user = $model::where('email', '=', $credentials['email'])->first();

        return ($user instanceof KeyAuthenticatable) ? $user : null;
    }

    /**
     * Validate credentials for key-based auth.
     *
     * For key-based auth, credential validation is handled by the KeyGuard
     * via signature verification, not by the provider. This method always
     * returns false — the guard should use its own challenge-response flow.
     *
     * @param Authenticatable $user
     * @param array $credentials
     * @return bool
     */
    public function validateCredentials(Authenticatable $user, array $credentials): bool
    {
        // Key-based authentication does not validate passwords.
        // The KeyGuard handles challenge-response verification directly.
        return false;
    }

    /**
     * Create a new instance of the model.
     *
     * @return KeyAuthenticatable
     */
    protected function createModel(): KeyAuthenticatable
    {
        $class = $this->model;

        return new $class;
    }
}
