<?php

declare(strict_types=1);

namespace Plugs\Http;

/*
|--------------------------------------------------------------------------
| Batch Class
|--------------------------------------------------------------------------
|
|
*/

use GuzzleHttp\Client;
use GuzzleHttp\Exception\RequestException;
use GuzzleHttp\Pool;
use GuzzleHttp\Promise\Utils;

class Batch
{
    private $client;
    private $requests = [];
    private $concurrency = 10;
    private $options = [];
    private $beforeSendCallback = null;
    private $afterResponseCallback = null;

    public function __construct(?Client $client = null)
    {
        $this->client = $client ?? new Client();
    }

    public static function make(?Client $client = null): self
    {
        return new self($client);
    }

    public function concurrency(int $limit): self
    {
        $this->concurrency = $limit;

        return $this;
    }

    public function withOptions(array $options): self
    {
        $this->options = $options;

        return $this;
    }

    public function get(string $url, array $options = [], ?string $key = null): self
    {
        return $this->add('GET', $url, $options, $key);
    }

    public function post(string $url, array $data = [], array $options = [], ?string $key = null): self
    {
        if (!empty($data)) {
            $options['json'] = $data;
        }

        return $this->add('POST', $url, $options, $key);
    }

    public function put(string $url, array $data = [], array $options = [], ?string $key = null): self
    {
        if (!empty($data)) {
            $options['json'] = $data;
        }

        return $this->add('PUT', $url, $options, $key);
    }

    public function patch(string $url, array $data = [], array $options = [], ?string $key = null): self
    {
        if (!empty($data)) {
            $options['json'] = $data;
        }

        return $this->add('PATCH', $url, $options, $key);
    }

    public function delete(string $url, array $options = [], ?string $key = null): self
    {
        return $this->add('DELETE', $url, $options, $key);
    }

    public function add(string $method, string $url, array $options = [], ?string $key = null): self
    {
        $key = $key ?? count($this->requests);

        $this->requests[$key] = [
            'method' => $method,
            'url' => $url,
            'options' => array_merge($this->options, $options),
        ];

        return $this;
    }

    public function addMultiple(array $requests): self
    {
        foreach ($requests as $key => $request) {
            $this->add(
                $request['method'] ?? 'GET',
                $request['url'],
                $request['options'] ?? [],
                is_string($key) ? $key : null
            );
        }

        return $this;
    }

    public function beforeSending(callable $callback): self
    {
        $this->beforeSendCallback = $callback;

        return $this;
    }

    public function afterResponse(callable $callback): self
    {
        $this->afterResponseCallback = $callback;

        return $this;
    }

    public function send(): BatchResponse
    {
        if (empty($this->requests)) {
            return new BatchResponse([]);
        }

        $promises = [];

        foreach ($this->requests as $key => $request) {
            if ($this->beforeSendCallback) {
                call_user_func($this->beforeSendCallback, $request, $key);
            }

            $promises[$key] = $this->client->requestAsync(
                $request['method'],
                $request['url'],
                $request['options']
            );
        }

        $results = Utils::settle($promises)->wait();

        $responses = [];

        foreach ($results as $key => $result) {
            if ($result['state'] === 'fulfilled') {
                $httpResponse = new HTTPResponse($result['value']);
            } else {
                $exception = $result['reason'];
                $httpResponse = new HTTPResponse(
                    $exception instanceof RequestException ? $exception->getResponse() : null,
                    $exception instanceof RequestException ? $exception : null
                );
            }

            if ($this->afterResponseCallback) {
                call_user_func($this->afterResponseCallback, $httpResponse, $key);
            }

            $responses[$key] = $httpResponse;
        }

        return new BatchResponse($responses);
    }

    public function pool(): BatchResponse
    {
        if (empty($this->requests)) {
            return new BatchResponse([]);
        }

        $responses = [];

        $requests = function () {
            foreach ($this->requests as $key => $request) {
                if ($this->beforeSendCallback) {
                    call_user_func($this->beforeSendCallback, $request, $key);
                }

                yield $key => function () use ($request) {
                    return $this->client->requestAsync(
                        $request['method'],
                        $request['url'],
                        $request['options']
                    );
                };
            }
        };

        $pool = new Pool($this->client, $requests(), [
            'concurrency' => $this->concurrency,
            'fulfilled' => function ($response, $key) use (&$responses) {
                $httpResponse = new HTTPResponse($response);

                if ($this->afterResponseCallback) {
                    call_user_func($this->afterResponseCallback, $httpResponse, $key);
                }

                $responses[$key] = $httpResponse;
            },
            'rejected' => function ($reason, $key) use (&$responses) {
                $httpResponse = new HTTPResponse(
                    $reason instanceof RequestException ? $reason->getResponse() : null,
                    $reason instanceof RequestException ? $reason : null
                );

                if ($this->afterResponseCallback) {
                    call_user_func($this->afterResponseCallback, $httpResponse, $key);
                }

                $responses[$key] = $httpResponse;
            },
        ]);

        $promise = $pool->promise();
        $promise->wait();

        return new BatchResponse($responses);
    }

    public function clear(): self
    {
        $this->requests = [];

        return $this;
    }

    public function count(): int
    {
        return count($this->requests);
    }
}
