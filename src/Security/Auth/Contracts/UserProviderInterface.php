<?php

declare(strict_types=1);

namespace Plugs\Security\Auth\Contracts;

use Plugs\Security\Auth\Authenticatable;

/**
 * Interface UserProviderInterface
 *
 * Defines the contract for retrieving and validating users.
 * Guards depend on this interface rather than specific model classes.
 */
interface UserProviderInterface
{
    /**
     * Retrieve a user by their unique identifier.
     *
     * @param mixed $identifier
     * @return Authenticatable|null
     */
    public function retrieveById(mixed $identifier): ?Authenticatable;

    /**
     * Retrieve a user by their unique identifier and "remember me" token.
     *
     * @param mixed $identifier
     * @param string $token
     * @return Authenticatable|null
     */
    public function retrieveByToken(mixed $identifier, string $token): ?Authenticatable;

    /**
     * Update the "remember me" token for the given user.
     *
     * @param Authenticatable $user
     * @param string $token
     * @return void
     */
    public function updateRememberToken(Authenticatable $user, string $token): void;

    /**
     * Retrieve a user by the given credentials (e.g. email, username).
     * Should NOT check the password — that is done by validateCredentials().
     *
     * @param array $credentials
     * @return Authenticatable|null
     */
    public function retrieveByCredentials(array $credentials): ?Authenticatable;

    /**
     * Validate a user against the given credentials (e.g. verify password hash).
     *
     * @param Authenticatable $user
     * @param array $credentials
     * @return bool
     */
    public function validateCredentials(Authenticatable $user, array $credentials): bool;
}
