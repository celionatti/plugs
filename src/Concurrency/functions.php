<?php

declare(strict_types=1);

use Plugs\Concurrency\Async;
use Plugs\Concurrency\Promise;
use GuzzleHttp\Promise\PromiseInterface;

if (!function_exists('async')) {
    function async(callable $callback): void
    {
        Async::run($callback);
    }
}

if (!function_exists('await')) {
    function await(PromiseInterface|Promise $promise): mixed
    {
        if ($promise instanceof Promise) {
            $promise = $promise->getInnerPromise();
        }
        return Async::await($promise);
    }
}
