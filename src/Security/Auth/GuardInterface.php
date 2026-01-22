<?php

declare(strict_types=1);

namespace Plugs\Security\Auth;

interface GuardInterface
{
    /**
     * Determine if the current user is authenticated.
     *
     * @return bool
     */
    public function check(): bool;

    /**
     * Determine if the current user is a guest.
     *
     * @return bool
     */
    public function guest(): bool;

    /**
     * Get the currently authenticated user.
     *
     * @return \Plugs\Security\Auth\Authenticatable|null
     */
    public function user(): ?Authenticatable;

    /**
     * Get the ID for the currently authenticated user.
     *
     * @return mixed|null
     */
    public function id();

    /**
     * Validate a user's credentials.
     *
     * @param array $credentials
     * @return bool
     */
    public function validate(array $credentials = []): bool;

    /**
     * Attempt to authenticate a user using the given credentials.
     *
     * @param array $credentials
     * @param bool $remember
     * @return bool
     */
    public function attempt(array $credentials = [], bool $remember = false): bool;

    /**
     * Log a user into the application.
     *
     * @param \Plugs\Security\Auth\Authenticatable $user
     * @param bool $remember
     * @return void
     */
    public function login(Authenticatable $user, bool $remember = false): void;

    /**
     * Log the user out of the application.
     *
     * @return void
     */
    public function logout(): void;

    /**
     * Set the current user.
     *
     * @param \Plugs\Security\Auth\Authenticatable $user
     * @return void
     */
    public function setUser(Authenticatable $user): void;
}
