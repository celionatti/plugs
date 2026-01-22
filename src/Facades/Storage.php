<?php

namespace Plugs\Facades;

use Plugs\Facade;

/**
 * @method static bool exists(string $path)
 * @method static string|null get(string $path)
 * @method static bool put(string $path, string $contents)
 * @method static bool delete(string $path)
 * @method static string url(string $path)
 * @method static int size(string $path)
 * @method static int lastModified(string $path)
 * @method static bool makeDirectory(string $path)
 * @method static bool deleteDirectory(string $path)
 * @method static \Plugs\Filesystem\FilesystemDriverInterface disk(string|null $name = null)
 */
class Storage extends Facade
{
    protected static function getFacadeAccessor(): string
    {
        return 'storage';
    }
}
