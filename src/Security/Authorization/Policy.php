<?php

declare(strict_types=1);

namespace Plugs\Security\Authorization;

/**
 * Base Policy class.
 * Policies are classes that organize authorization logic around a particular model or resource.
 */
abstract class Policy
{
    /**
     * Determine if any permissions should be granted before any other checks.
     * Useful for "Super Admin" logic within a specific policy.
     *
     * @param \Plugs\Security\Auth\Authenticatable $user
     * @param string $ability
     * @return bool|null
     */
    public function before($user, $ability): ?bool
    {
        return null;
    }
}
