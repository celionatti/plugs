<?php

declare(strict_types=1);

namespace Plugs\Http;

/*
|--------------------------------------------------------------------------
| HTTP Class
|--------------------------------------------------------------------------
|
| This class provides a fluent interface for making HTTP requests using
| GuzzleHttp. It supports synchronous and asynchronous requests with
| chainable methods for configuration.
*/

use GuzzleHttp\Client;
use GuzzleHttp\Exception\RequestException;
use GuzzleHttp\Promise\PromiseInterface;
use Psr\Http\Message\ResponseInterface;

class HTTP
{
    private $client;
    private $options = [];
    private $baseUri = '';
    private $headers = [];
    private $queryParams = [];
    private $formParams = [];
    private $jsonData = null;
    private $timeout = 30;
    private $connectTimeout = 10;
    private $allowRedirects = true;
    private $maxRedirects = 5;
    private $verify = true;
    private $retries = 0;
    private $retryDelay = 1000;

    public function __construct(?Client $client = null)
    {
        $this->client = $client ?? new Client();
    }

    public static function make(?Client $client = null): self
    {
        return new self($client);
    }

    public function baseUri(string $uri): self
    {
        $this->baseUri = rtrim($uri, '/');
        return $this;
    }

    public function header(string $name, string $value): self
    {
        $this->headers[$name] = $value;
        return $this;
    }

    public function headers(array $headers): self
    {
        $this->headers = array_merge($this->headers, $headers);
        return $this;
    }

    public function bearerToken(string $token): self
    {
        return $this->header('Authorization', 'Bearer ' . $token);
    }

    public function basicAuth(string $username, string $password): self
    {
        $credentials = base64_encode($username . ':' . $password);
        return $this->header('Authorization', 'Basic ' . $credentials);
    }

    public function acceptJson(): self
    {
        return $this->header('Accept', 'application/json');
    }

    public function contentType(string $type): self
    {
        return $this->header('Content-Type', $type);
    }

    public function query(array $params): self
    {
        $this->queryParams = array_merge($this->queryParams, $params);
        return $this;
    }

    public function form(array $params): self
    {
        $this->formParams = array_merge($this->formParams, $params);
        return $this;
    }

    public function json(array $data): self
    {
        $this->jsonData = $data;
        return $this->header('Content-Type', 'application/json');
    }

    public function timeout(int $seconds): self
    {
        $this->timeout = $seconds;
        return $this;
    }

    public function connectTimeout(int $seconds): self
    {
        $this->connectTimeout = $seconds;
        return $this;
    }

    public function retry(int $times, int $delayMs = 1000): self
    {
        $this->retries = $times;
        $this->retryDelay = $delayMs;
        return $this;
    }

    public function allowRedirects(bool $allow = true, int $max = 5): self
    {
        $this->allowRedirects = $allow;
        $this->maxRedirects = $max;
        return $this;
    }

    public function verify(bool $verify = true): self
    {
        $this->verify = $verify;
        return $this;
    }

    public function withOptions(array $options): self
    {
        $this->options = array_merge($this->options, $options);
        return $this;
    }

    public function get(string $url, array $query = []): HTTPResponse
    {
        if (!empty($query)) {
            $this->query($query);
        }
        return $this->send('GET', $url);
    }

    public function post(string $url, array $data = []): HTTPResponse
    {
        if (!empty($data) && $this->jsonData === null) {
            $this->json($data);
        }
        return $this->send('POST', $url);
    }

    public function put(string $url, array $data = []): HTTPResponse
    {
        if (!empty($data) && $this->jsonData === null) {
            $this->json($data);
        }
        return $this->send('PUT', $url);
    }

    public function patch(string $url, array $data = []): HTTPResponse
    {
        if (!empty($data) && $this->jsonData === null) {
            $this->json($data);
        }
        return $this->send('PATCH', $url);
    }

    public function delete(string $url, array $data = []): HTTPResponse
    {
        if (!empty($data) && $this->jsonData === null) {
            $this->json($data);
        }
        return $this->send('DELETE', $url);
    }

    public function getAsync(string $url, array $query = []): PromiseInterface
    {
        if (!empty($query)) {
            $this->query($query);
        }
        return $this->sendAsync('GET', $url);
    }

    public function postAsync(string $url, array $data = []): PromiseInterface
    {
        if (!empty($data) && $this->jsonData === null) {
            $this->json($data);
        }
        return $this->sendAsync('POST', $url);
    }

    private function send(string $method, string $url): HTTPResponse
    {
        $options = $this->buildOptions();
        $fullUrl = $this->buildUrl($url);
        
        $attempts = 0;
        $maxAttempts = $this->retries + 1;
        
        while ($attempts < $maxAttempts) {
            try {
                $response = $this->client->request($method, $fullUrl, $options);
                return new HTTPResponse($response);
            } catch (RequestException $e) {
                $attempts++;
                
                if ($attempts >= $maxAttempts) {
                    return new HTTPResponse($e->getResponse(), $e);
                }
                
                usleep($this->retryDelay * 1000);
            }
        }
        
        throw new \RuntimeException('Failed to send request');
    }

    private function sendAsync(string $method, string $url): PromiseInterface
    {
        $options = $this->buildOptions();
        $fullUrl = $this->buildUrl($url);
        
        return $this->client->requestAsync($method, $fullUrl, $options);
    }

    public function buildUrl(string $url): string
    {
        if (filter_var($url, FILTER_VALIDATE_URL)) {
            return $url;
        }
        
        return $this->baseUri . '/' . ltrim($url, '/');
    }

    private function buildOptions(): array
    {
        $options = $this->options;
        
        if (!empty($this->headers)) {
            $options['headers'] = $this->headers;
        }
        
        if (!empty($this->queryParams)) {
            $options['query'] = $this->queryParams;
        }
        
        if ($this->jsonData !== null) {
            $options['json'] = $this->jsonData;
        } elseif (!empty($this->formParams)) {
            $options['form_params'] = $this->formParams;
        }
        
        $options['timeout'] = $this->timeout;
        $options['connect_timeout'] = $this->connectTimeout;
        $options['verify'] = $this->verify;
        
        $options['allow_redirects'] = $this->allowRedirects ? [
            'max' => $this->maxRedirects,
            'strict' => true,
            'referer' => true,
        ] : false;
        
        return $options;
    }

    public function reset(): self
    {
        $this->headers = [];
        $this->queryParams = [];
        $this->formParams = [];
        $this->jsonData = null;
        $this->timeout = 30;
        $this->connectTimeout = 10;
        $this->allowRedirects = true;
        $this->maxRedirects = 5;
        $this->verify = true;
        $this->retries = 0;
        $this->retryDelay = 1000;
        $this->options = [];
        
        return $this;
    }
}