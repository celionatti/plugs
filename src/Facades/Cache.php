<?php

declare(strict_types=1);

namespace Plugs\Facades;

use Plugs\Facade;

/**
 * @method static mixed get(string $key, $default = null)
 * @method static bool set(string $key, $value, int|null $ttl = null)
 * @method static bool delete(string $key)
 * @method static bool clear()
 * @method static bool has(string $key)
 * @method static iterable getMultiple(iterable $keys, $default = null)
 * @method static bool setMultiple(iterable $values, int|null $ttl = null)
 * @method static bool deleteMultiple(iterable $keys)
 * @method static \Plugs\Cache\CacheDriverInterface driver(string $name = null)
 *
 * @see \Plugs\Cache\CacheManager
 * @see \Plugs\Cache\CacheDriverInterface
 */
class Cache extends Facade
{
    /**
     * Get the registered name of the component.
     *
     * @return string
     */
    protected static function getFacadeAccessor(): string
    {
        return 'cache';
    }
}
