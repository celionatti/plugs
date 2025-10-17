<?php

declare(strict_types=1);

namespace Plugs\Http\Message;

/*
|--------------------------------------------------------------------------
| Response Class
|--------------------------------------------------------------------------
|
| This class represents an HTTP response message. It can be used to
| construct and send HTTP responses to clients.
*/

use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\StreamInterface;

class Response implements ResponseInterface
{
    private $statusCode;
    private $reasonPhrase;
    private $headers;
    private $body;
    private $protocol;
    private $headerNames = [];

    public function __construct(
        int $statusCode = 200,
        ?StreamInterface $body = null,
        array $headers = [],
        string $protocol = '1.1',
        string $reasonPhrase = ''
    ) {
        $this->statusCode = $statusCode;
        $this->body = $body ?? new Stream(fopen('php://temp', 'r+'));
        $this->protocol = $protocol;
        $this->reasonPhrase = $reasonPhrase;
        
        // Set headers properly
        foreach ($headers as $header => $value) {
            if (!is_array($value)) {
                $value = [$value];
            }
            $this->headerNames[strtolower($header)] = $header;
            $this->headers[$header] = $value;
        }
    }

    public function getStatusCode(): int
    {
        return $this->statusCode;
    }

    public function withStatus($code, $reasonPhrase = ''): ResponseInterface
    {
        $new = clone $this;
        $new->statusCode = (int) $code;
        $new->reasonPhrase = $reasonPhrase;
        return $new;
    }

    public function getReasonPhrase(): string
    {
        if ($this->reasonPhrase === '' && isset(Response::$phrases[$this->statusCode])) {
            $this->reasonPhrase = Response::$phrases[$this->statusCode];
        }
        return $this->reasonPhrase;
    }

    public function getProtocolVersion(): string
    {
        return $this->protocol;
    }

    public function withProtocolVersion($version): ResponseInterface
    {
        $new = clone $this;
        $new->protocol = $version;
        return $new;
    }

    public function getHeaders(): array
    {
        return $this->headers;
    }

    public function hasHeader($name): bool
    {
        return isset($this->headerNames[strtolower($name)]);
    }

    public function getHeader($name): array
    {
        $name = strtolower($name);
        if (!isset($this->headerNames[$name])) {
            return [];
        }

        $header = $this->headerNames[$name];
        return $this->headers[$header];
    }

    public function getHeaderLine($name): string
    {
        return implode(', ', $this->getHeader($name));
    }

    public function withHeader($name, $value): ResponseInterface
    {
        $new = clone $this;
        $header = strtolower($name);
        $new->headerNames[$header] = $name;
        $new->headers[$name] = is_array($value) ? $value : [$value];
        return $new;
    }

    public function withAddedHeader($name, $value): ResponseInterface
    {
        $new = clone $this;
        $header = strtolower($name);
        
        if (isset($new->headerNames[$header])) {
            $name = $this->headerNames[$header];
            $new->headers[$name] = array_merge($this->headers[$name], is_array($value) ? $value : [$value]);
        } else {
            $new->headerNames[$header] = $name;
            $new->headers[$name] = is_array($value) ? $value : [$value];
        }
        
        return $new;
    }

    public function withoutHeader($name): ResponseInterface
    {
        $new = clone $this;
        $header = strtolower($name);
        
        if (isset($new->headerNames[$header])) {
            $name = $new->headerNames[$header];
            unset($new->headers[$name], $new->headerNames[$header]);
        }
        
        return $new;
    }

    public function getBody(): StreamInterface
    {
        return $this->body;
    }

    public function withBody(StreamInterface $body): ResponseInterface
    {
        $new = clone $this;
        $new->body = $body;
        return $new;
    }

    // Common HTTP status phrases
    private static $phrases = [
        200 => 'OK',
        201 => 'Created',
        202 => 'Accepted',
        204 => 'No Content',
        301 => 'Moved Permanently',
        302 => 'Found',
        304 => 'Not Modified',
        400 => 'Bad Request',
        401 => 'Unauthorized',
        403 => 'Forbidden',
        404 => 'Not Found',
        405 => 'Method Not Allowed',
        500 => 'Internal Server Error',
    ];
}