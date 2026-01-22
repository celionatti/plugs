<?php

declare(strict_types=1);

namespace Plugs\Facades;

use Plugs\Facade;

/**
 * @method static mixed push(string|object $job, mixed $data = '', string|null $queue = null)
 * @method static mixed later(int $delay, string|object $job, mixed $data = '', string|null $queue = null)
 * @method static object|null pop(string|null $queue = null)
 * @method static int size(string|null $queue = null)
 * @method static \Plugs\Queue\QueueDriverInterface driver(string|null $name = null)
 *
 * @see \Plugs\Queue\QueueManager
 */
class Queue extends Facade
{
    /**
     * Get the registered name of the component.
     *
     * @return string
     */
    protected static function getFacadeAccessor(): string
    {
        return 'queue';
    }
}
