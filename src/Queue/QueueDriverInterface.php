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
     * Release a job back onto the queue.
     *
     * @param object $job
     * @param int $delay
     * @return void
     */
    public function release(object $job, int $delay = 0): void;

    /**
     * Delete a job from the queue.
     *
     * @param int|string $id
     * @return bool
     */
    public function delete($id): bool;
}
