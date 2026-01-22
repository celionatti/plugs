<?php

declare(strict_types=1);

namespace Plugs\Queue;

interface QueueDriverInterface
{
    /**
     * Push a new job onto the queue.
     *
     * @param string|object $job
     * @param mixed $data
     * @param string|null $queue
     * @return mixed
     */
    public function push($job, $data = '', $queue = null);

    /**
     * Push a new job onto the queue after a delay.
     *
     * @param int $delay
     * @param string|object $job
     * @param mixed $data
     * @param string|null $queue
     * @return mixed
     */
    public function later(int $delay, $job, $data = '', $queue = null);

    /**
     * Pop the next job off of the queue.
     *
     * @param string|null $queue
     * @return object|null
     */
    public function pop($queue = null);

    /**
     * Get the size of the queue.
     *
     * @param string|null $queue
     * @return int
     */
    public function size($queue = null): int;
}
