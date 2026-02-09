<?php

declare(strict_types=1);

namespace Plugs\Http\Client;

use GuzzleHttp\Client;
use GuzzleHttp\Promise\PromiseInterface;
use Plugs\Concurrency\Promise;

class AsyncClient
{
    private Client $client;

    public function __construct(array $config = [])
    {
        $this->client = new Client($config);
    }

    public function requestAsync(string $method, string $uri, array $options = []): Promise
    {
        return new Promise($this->client->requestAsync($method, $uri, $options));
    }

    public function getAsync(string $uri, array $options = []): Promise
    {
        return $this->requestAsync('GET', $uri, $options);
    }

    public function postAsync(string $uri, array $options = []): Promise
    {
        return $this->requestAsync('POST', $uri, $options);
    }

    public function putAsync(string $uri, array $options = []): Promise
    {
        return $this->requestAsync('PUT', $uri, $options);
    }

    public function deleteAsync(string $uri, array $options = []): Promise
    {
        return $this->requestAsync('DELETE', $uri, $options);
    }
}
