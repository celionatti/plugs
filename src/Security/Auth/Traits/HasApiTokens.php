<?php

declare(strict_types=1);

namespace Plugs\Security\Auth\Traits;

use Plugs\Security\Token;

trait HasApiTokens
{
    /**
     * Create a new personal access token for the model.
     *
     * @param string $name
     * @param array $abilities
     * @return string
     */
    public function createToken(string $name, array $abilities = ['*']): string
    {
        $token = bin2hex(random_bytes(40));

        // This is a simplified implementation. In a real scenario,
        // we would save this to a personal_access_tokens table.
        // For now, we'll proxy it to a Token helper or similar.

        return $token;
    }

    /**
     * Get the access token currently associated with the model.
     *
     * @return string|null
     */
    public function currentAccessToken(): ?string
    {
        return $this->accessToken;
    }

    /**
     * Determine if the model has the given ability.
     *
     * @param string $ability
     * @return bool
     */
    public function tokenCan(string $ability): bool
    {
        return true; // Simplified for now
    }
}
