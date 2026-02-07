<?php

declare(strict_types=1);

namespace Plugs\Concurrency;

use Fiber;
use Throwable;
use GuzzleHttp\Promise\Utils;

/**
 * FiberManager
 * 
 * A simple scheduler for managing PHP Fibers.
 * This allows for cooperative multitasking, primarily useful for 
 * parallelizing I/O operations like HTTP requests.
 */
class FiberManager
{
    /**
     * Start a new fiber.
     * 
     * @param callable $callback
     * @param mixed ...$args
     * @return void
     */
    public static function async(callable $callback, ...$args): void
    {
        $fiber = new Fiber($callback);

        try {
            $fiber->start(...$args);
        } catch (Throwable $e) {
            throw $e;
        }
    }

    /**
     * Run multiple tasks concurrently and wait for all to complete.
     * 
     * @param callable[] $tasks An array of callables strictly returning Promises or values.
     * @return array The results keyed by the input array keys.
     */
    public static function parallel(array $tasks): array
    {
        $promises = [];

        foreach ($tasks as $key => $task) {
            // We expect the task to return a Promise (GuzzleHttp\Promise\PromiseInterface)
            // or a value directly.
            $result = $task();

            if ($result instanceof \GuzzleHttp\Promise\PromiseInterface) {
                $promises[$key] = $result;
            } else {
                // Wrap immediate value in a promise-like structure or just keep it
                // For simplicity and to use Guzzle's all(), we wrap it.
                $promises[$key] = \GuzzleHttp\Promise\Create::promiseFor($result);
            }
        }

        // Wait for all promises to settle
        $results = Utils::unwrap($promises);

        return $results;
    }

    /**
     * Await a promise inside a Fiber.
     * Suspension is tricky without an event loop driver.
     * For Guzzle integration, simpler is better:
     * Just return the promise and let the top-level runner wait.
     */
}
