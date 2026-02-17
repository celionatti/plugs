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
     * Run a task in a Fiber.
     */
    public static function run(callable $callback): PromiseInterface
    {
        $promise = new \GuzzleHttp\Promise\Promise(function () use (&$promise) {
            // Valid wait strategy: run the task queue until resolved
            while ($promise->getState() === PromiseInterface::PENDING) {
                Utils::queue()->run();
            }
        });

        $fiber = new \Fiber(function () use ($callback, $promise) {
            try {
                $result = $callback();
                if ($result instanceof PromiseInterface) {
                    $result->then(
                        fn($v) => $promise->resolve($v),
                        fn($e) => $promise->reject($e)
                    );
                } else {
                    $promise->resolve($result);
                }
            } catch (\Throwable $e) {
                $promise->reject($e);
            }
        });

        $fiber->start();

        return $promise;
    }

    /**
     * Await a promise resolution.
     * Suspends the current Fiber until the promise is settled.
     */
    public static function await(PromiseInterface $promise): mixed
    {
        if (!\Fiber::getCurrent()) {
            return $promise->wait();
        }

        $fiber = \Fiber::getCurrent();
        $isSettled = false;
        $result = null;
        $error = null;

        $promise->then(
            function ($value) use ($fiber, &$isSettled, &$result) {
                $isSettled = true;
                $result = $value;
                // Resume logic: if we are not in the same tick as suspend (usually true for async)
                if ($fiber->isSuspended()) {
                    $fiber->resume();
                }
            },
            function ($reason) use ($fiber, &$isSettled, &$error) {
                $isSettled = true;
                $error = $reason;
                if ($fiber->isSuspended()) {
                    $fiber->resume();
                }
            }
        );

        if (!$isSettled) {
            \Fiber::suspend();
        }

        if ($error) {
            throw $error instanceof \Throwable ? $error : new \Exception((string) $error);
        }

        return $result;
    }

    /**
     * Run a task in the background using the event loop.
     */
    public static function background(callable $callback): void
    {
        $loop = app(LoopManager::class);
        $loop->futureTick($callback);
    }

    /**
     * Non-blocking delay.
     */
    public static function delay(float $seconds, ?callable $callback = null): ?PromiseInterface
    {
        $loop = app(LoopManager::class);

        if ($callback) {
            $loop->addTimer($seconds, $callback);
            return null;
        }

        $promise = new \GuzzleHttp\Promise\Promise();
        $loop->addTimer($seconds, fn() => $promise->resolve(true));

        return $promise;
    }

    /**
     * Non-blocking sleep (alias for delay when used with await).
     */
    public static function sleep(float $seconds): void
    {
        static::await(static::delay($seconds));
    }
}
