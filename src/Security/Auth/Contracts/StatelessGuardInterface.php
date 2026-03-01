<?php

declare(strict_types=1);

namespace Plugs\Security\Auth\Contracts;

use Plugs\Security\Auth\Authenticatable;
use Plugs\Security\Auth\GuardInterface;

/**
 * Interface StatelessGuardInterface
 *
 * Extends GuardInterface for stateless guards that authenticate via
 * tokens (JWT, personal access tokens, API keys).
 */
interface StatelessGuardInterface extends GuardInterface
{
    /**
     * Issue a new authentication token for the given user.
     *
     * @param Authenticatable $user
     * @param array $options Additional options (e.g. abilities, expiry)
     * @return string The issued token
     */
    public function issueToken(Authenticatable $user, array $options = []): string;

    /**
     * Revoke a specific token.
     *
     * @param string $token
     * @return bool Whether the token was successfully revoked
     */
    public function revokeToken(string $token): bool;

    /**
     * Revoke all tokens for the given user.
     *
     * @param Authenticatable $user
     * @return int Number of tokens revoked
     */
    public function revokeAllTokens(Authenticatable $user): int;
}
