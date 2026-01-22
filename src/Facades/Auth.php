<?php

declare(strict_types=1);

namespace Plugs\Facades;

use Plugs\Facade;

/**
 * @method static bool check()
 * @method static bool guest()
 * @method static \Plugs\Security\Auth\Authenticatable|null user()
 * @method static mixed|null id()
 * @method static bool validate(array $credentials = [])
 * @method static bool attempt(array $credentials = [], bool $remember = false)
 * @method static void login(\Plugs\Security\Auth\Authenticatable $user, bool $remember = false)
 * @method static void logout()
 * @method static void setUser(\Plugs\Security\Auth\Authenticatable $user)
 * @method static \Plugs\Security\Auth\GuardInterface guard(string|null $name = null)
 *
 * @see \Plugs\Security\Auth\AuthManager
 */
class Auth extends Facade
{
    /**
     * Get the registered name of the component.
     *
     * @return string
     */
    protected static function getFacadeAccessor(): string
    {
        return 'auth';
    }
}
