<?php

declare(strict_types=1);

namespace Plugs\Queue\Drivers;

use Plugs\Queue\QueueDriverInterface;

class SyncQueueDriver implements QueueDriverInterface
{
    public function push($job, $data = '', $queue = null)
    {
        $this->resolveAndExecute($job, $data);

        return 0;
    }

    public function later(int $delay, $job, $data = '', $queue = null)
    {
        return $this->push($job, $data, $queue);
    }

    public function pop($queue = null)
    {
        return null;
    }

    public function size($queue = null): int
    {
        return 0;
    }

    protected function resolveAndExecute($job, $data)
    {
        if ($job instanceof \Closure) {
            $job($data);

            return;
        }

        if (is_object($job)) {
            $job->handle($data);

            return;
        }

        if (is_string($job) && class_exists($job)) {
            $instance = new $job();
            if (method_exists($instance, 'handle')) {
                $instance->handle($data);
            }
        }
    }
}
