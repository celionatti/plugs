<?php

declare(strict_types=1);

namespace Plugs\Support\Facades;

use Plugs\Facade;
use Plugs\Container\Container;

/**
 * @method static mixed make(string $abstract, array $parameters = [])
 * @method static bool bound(string $abstract)
 * @method static void singleton(string $abstract, $concrete = null)
 * @method static void bind(string $abstract, $concrete = null, bool $shared = false)
 * @method static void instance(string $abstract, $instance)
 *
 * @see \Plugs\Container\Container
 */
class App extends Facade
{
    /**
     * Get the registered name of the component.
     *
     * @return string
     */
    protected static function getFacadeAccessor(): string
    {
        return 'app';
    }

    /**
     * Get the container instance.
     *
     * @return \Plugs\Container\Container
     */
    public static function getContainer(): Container
    {
        return Container::getInstance();
    }
}
