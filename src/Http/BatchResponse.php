<?php

declare(strict_types=1);

namespace Plugs\Http;

/*
|--------------------------------------------------------------------------
| BatchResponse Class
|--------------------------------------------------------------------------
|
| 
*/

class BatchResponse implements \Countable, \IteratorAggregate, \ArrayAccess
{
    private $responses = [];

    public function __construct(array $responses)
    {
        $this->responses = $responses;
    }

    public function get($key): ?HTTPResponse
    {
        return $this->responses[$key] ?? null;
    }

    public function all(): array
    {
        return $this->responses;
    }

    public function successful(): array
    {
        return array_filter($this->responses, function (HTTPResponse $response) {
            return $response->successful();
        });
    }

    public function failed(): array
    {
        return array_filter($this->responses, function (HTTPResponse $response) {
            return $response->failed();
        });
    }

    public function allSuccessful(): bool
    {
        foreach ($this->responses as $response) {
            if ($response->failed()) {
                return false;
            }
        }
        
        return true;
    }

    public function hasFailures(): bool
    {
        return !$this->allSuccessful();
    }

    public function first(): ?HTTPResponse
    {
        return reset($this->responses) ?: null;
    }

    public function last(): ?HTTPResponse
    {
        return end($this->responses) ?: null;
    }

    public function map(callable $callback): array
    {
        $results = [];
        
        foreach ($this->responses as $key => $response) {
            $results[$key] = $callback($response, $key);
        }
        
        return $results;
    }

    public function filter(callable $callback): array
    {
        return array_filter($this->responses, $callback);
    }

    public function each(callable $callback): self
    {
        foreach ($this->responses as $key => $response) {
            $callback($response, $key);
        }
        
        return $this;
    }

    public function toJson(): array
    {
        return $this->map(function (HTTPResponse $response) {
            return $response->json();
        });
    }

    public function toBodies(): array
    {
        return $this->map(function (HTTPResponse $response) {
            return $response->body();
        });
    }

    public function statuses(): array
    {
        return $this->map(function (HTTPResponse $response) {
            return $response->status();
        });
    }

    public function has($key): bool
    {
        return isset($this->responses[$key]);
    }

    public function keys(): array
    {
        return array_keys($this->responses);
    }

    public function count(): int
    {
        return count($this->responses);
    }

    public function getIterator(): \ArrayIterator
    {
        return new \ArrayIterator($this->responses);
    }

    public function offsetExists($offset): bool
    {
        return isset($this->responses[$offset]);
    }

    public function offsetGet($offset): ?HTTPResponse
    {
        return $this->responses[$offset] ?? null;
    }

    public function offsetSet($offset, $value): void
    {
        if ($offset === null) {
            $this->responses[] = $value;
        } else {
            $this->responses[$offset] = $value;
        }
    }

    public function offsetUnset($offset): void
    {
        unset($this->responses[$offset]);
    }

    public function toArray(): array
    {
        return $this->responses;
    }
}