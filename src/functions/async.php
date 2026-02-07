<?php

declare(strict_types=1);

use Plugs\Concurrency\Async;

if (!function_exists('parallel')) {
    /**
     * Run tasks in parallel.
     *
     * @param  array  $tasks
     * @return array
     */
    function parallel(array $tasks): array
    {
        return Async::parallel($tasks);
    }
}

if (!function_exists('async')) {
    /**
     * Run a task asynchronously (in a Fiber).
     *
     * @param  callable  $callback
     * @return void
     */
    function async(callable $callback): void
    {
        Async::run($callback);
    }
}
