<?php

declare(strict_types=1);

namespace Plugs\Http;

/*
|--------------------------------------------------------------------------
| HTTPResponse Class
|--------------------------------------------------------------------------
|
| 
*/

use GuzzleHttp\Exception\RequestException;
use Psr\Http\Message\ResponseInterface;

class HTTPResponse
{
    private $response;
    private $exception;
    private $cachedJson = null;

    public function __construct(?ResponseInterface $response = null, ?RequestException $exception = null)
    {
        $this->response = $response;
        $this->exception = $exception;
    }

    public function status(): int
    {
        return $this->response ? $this->response->getStatusCode() : 0;
    }

    public function successful(): bool
    {
        return $this->status() >= 200 && $this->status() < 300;
    }

    public function failed(): bool
    {
        return !$this->successful();
    }

    public function ok(): bool
    {
        return $this->status() === 200;
    }

    public function created(): bool
    {
        return $this->status() === 201;
    }

    public function noContent(): bool
    {
        return $this->status() === 204;
    }

    public function redirect(): bool
    {
        return $this->status() >= 300 && $this->status() < 400;
    }

    public function unauthorized(): bool
    {
        return $this->status() === 401;
    }

    public function forbidden(): bool
    {
        return $this->status() === 403;
    }

    public function notFound(): bool
    {
        return $this->status() === 404;
    }

    public function unprocessable(): bool
    {
        return $this->status() === 422;
    }

    public function clientError(): bool
    {
        return $this->status() >= 400 && $this->status() < 500;
    }

    public function serverError(): bool
    {
        return $this->status() >= 500;
    }

    public function body(): string
    {
        if (!$this->response) {
            return '';
        }
        
        return (string) $this->response->getBody();
    }

    public function json(?string $key = null, $default = null)
    {
        if ($this->cachedJson === null) {
            $this->cachedJson = json_decode($this->body(), true);
        }
        
        if ($key === null) {
            return $this->cachedJson;
        }
        
        return $this->cachedJson[$key] ?? $default;
    }

    public function object(): ?\stdClass
    {
        return json_decode($this->body());
    }

    public function header(string $name): string
    {
        if (!$this->response) {
            return '';
        }
        
        return $this->response->getHeaderLine($name);
    }

    public function headers(): array
    {
        if (!$this->response) {
            return [];
        }
        
        return $this->response->getHeaders();
    }

    public function toPsrResponse(): ?ResponseInterface
    {
        return $this->response;
    }

    public function exception(): ?RequestException
    {
        return $this->exception;
    }

    public function throw(): self
    {
        if ($this->failed() && $this->exception) {
            throw $this->exception;
        }
        
        return $this;
    }

    public function onSuccess(callable $callback): self
    {
        if ($this->successful()) {
            $callback($this);
        }
        
        return $this;
    }

    public function onError(callable $callback): self
    {
        if ($this->failed()) {
            $callback($this);
        }
        
        return $this;
    }

    public function collect(?string $key = null)
    {
        $data = $this->json();
        
        if ($key === null) {
            return $data;
        }
        
        $keys = explode('.', $key);
        $value = $data;
        
        foreach ($keys as $segment) {
            if (is_array($value) && isset($value[$segment])) {
                $value = $value[$segment];
            } else {
                return null;
            }
        }
        
        return $value;
    }

    public function __toString(): string
    {
        return $this->body();
    }
}