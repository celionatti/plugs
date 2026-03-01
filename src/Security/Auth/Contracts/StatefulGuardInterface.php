<?php

declare(strict_types=1);

namespace Plugs\Security\Auth\Contracts;

use Plugs\Security\Auth\Authenticatable;
use Plugs\Security\Auth\GuardInterface;

/**
 * Interface StatefulGuardInterface
 *
 * Extends GuardInterface for guards that maintain server-side state
 * (e.g. session-based authentication with remember-me support).
 */
interface StatefulGuardInterface extends GuardInterface
{
    /**
     * Determine if the user was authenticated via "remember me" cookie.
     *
     * @return bool
     */
    public function viaRemember(): bool;

    /**
     * Log a user into the application using their ID.
     *
     * @param mixed $id
     * @param bool $remember
     * @return Authenticatable|null
     */
    public function loginUsingId(mixed $id, bool $remember = false): ?Authenticatable;

    /**
     * Log a user into the application without sessions or cookies.
     * (Useful for testing or one-time actions.)
     *
     * @param Authenticatable $user
     * @return void
     */
    public function setUserOnce(Authenticatable $user): void;
}
