<?php

declare(strict_types=1);

namespace Plugs\Facades;

use Plugs\Facade;

/**
 * @method static string encrypt($data)
 * @method static mixed decrypt(string $encrypted)
 *
 * @see \Plugs\Security\Encrypter
 */
class Crypt extends Facade
{
    /**
     * Get the registered name of the component.
     *
     * @return string
     */
    protected static function getFacadeAccessor(): string
    {
        return 'encrypter';
    }
}
