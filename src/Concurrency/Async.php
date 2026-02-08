<?php

declare(strict_types=1);

namespace Plugs\Concurrency;

use GuzzleHttp\Promise\PromiseInterface;
use GuzzleHttp\Promise\Utils;

class Async
{
    /**
     * Run an array of tasks in parallel.
     *
     * @param array<callable|PromiseInterface> $tasks
     * @return array
     */
    public static function parallel(array $tasks): array
    {
        $promises = [];

        foreach ($tasks as $key => $task) {
            if ($task instanceof PromiseInterface) {
                $promises[$key] = $task;
            } elseif (is_callable($task)) { /** @phpstan-ignore function.alreadyNarrowedType */
                $result = $task();
                if ($result instanceof PromiseInterface) {
                    $promises[$key] = $result;
                } else {
                    $promises[$key] = \GuzzleHttp\Promise\Create::promiseFor($result);
                }
            } else {
                $promises[$key] = \GuzzleHttp\Promise\Create::promiseFor($task);
            }
        }

        return Utils::unwrap($promises);
    }

    /**
     * Run a task in a Fiber (Fire and forget, or managed by a scheduler if we had one running).
     * For now, this just starts a fiber.
     */
    public static function run(callable $callback): void
    {
        $fiber = new \Fiber($callback);
        $fiber->start();
    }
}
