<?php

declare(strict_types=1);

namespace Plugs\Concurrency;

use GuzzleHttp\Promise\PromiseInterface;
use Throwable;

class Promise
{
    public function __construct(
        private PromiseInterface $promise
    ) {
    }

    public function then(callable $onFulfilled = null, callable $onRejected = null): self
    {
        return new self($this->promise->then($onFulfilled, $onRejected));
    }

    public function otherwise(callable $onRejected): self
    {
        return new self($this->promise->otherwise($onRejected));
    }

    public function wait(): mixed
    {
        return $this->promise->wait();
    }

    public function resolve(): mixed
    {
        return Async::await($this->promise);
    }

    public function getInnerPromise(): PromiseInterface
    {
        return $this->promise;
    }
}
