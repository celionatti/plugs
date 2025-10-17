<?php

declare(strict_types=1);

namespace Plugs\Http\Message;

/*
|--------------------------------------------------------------------------
| ServerRequest Class
|--------------------------------------------------------------------------
|
| This class represents a server-side HTTP request. It extends the base
| request class to include server-specific information such as headers,
| cookies, query parameters, and uploaded files.
*/

use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Message\UriInterface;
use Psr\Http\Message\StreamInterface;

class ServerRequest implements ServerRequestInterface
{
    private $method;
    private $uri;
    private $headers;
    private $body;
    private $protocolVersion;
    private $serverParams;
    private $cookieParams;
    private $queryParams;
    private $uploadedFiles;
    private $parsedBody;
    private $attributes;

    public function __construct(
        string $method,
        $uri,
        array $headers = [],
        ?StreamInterface $body = null,
        string $protocolVersion = '1.1',
        array $serverParams = []
    ) {
        $this->method = $method;
        $this->uri = $uri instanceof UriInterface ? $uri : new Uri($uri);
        $this->headers = $this->filterHeaders($headers);
        $this->body = $body ?? new Stream(fopen('php://temp', 'r+'));
        $this->protocolVersion = $protocolVersion;
        $this->serverParams = $serverParams;
        $this->cookieParams = [];
        $this->queryParams = [];
        $this->uploadedFiles = [];
        $this->parsedBody = null;
        $this->attributes = [];
    }

    public function getServerParams(): array
    {
        return $this->serverParams;
    }

    public function getCookieParams(): array
    {
        return $this->cookieParams;
    }

    public function withCookieParams(array $cookies): ServerRequestInterface
    {
        $new = clone $this;
        $new->cookieParams = $cookies;
        return $new;
    }

    public function getQueryParams(): array
    {
        return $this->queryParams;
    }

    public function withQueryParams(array $query): ServerRequestInterface
    {
        $new = clone $this;
        $new->queryParams = $query;
        return $new;
    }

    public function getUploadedFiles(): array
    {
        return $this->uploadedFiles;
    }

    public function withUploadedFiles(array $uploadedFiles): ServerRequestInterface
    {
        $new = clone $this;
        $new->uploadedFiles = $uploadedFiles;
        return $new;
    }

    /**
     * Get uploaded file
     */
    public function getUploadedFile(string $key): ?\Plugs\Upload\UploadedFile
    {
        $files = $this->getUploadedFiles();

        if (!isset($files[$key])) {
            return null;
        }

        $file = $files[$key];

        if (is_array($file) && !($file instanceof \Plugs\Upload\UploadedFile)) {
            return new \Plugs\Upload\UploadedFile($file);
        }

        return $file instanceof \Plugs\Upload\UploadedFile ? $file : null;
    }

    /**
     * Check if file was uploaded
     */
    public function hasFile(string $key): bool
    {
        $file = $this->getUploadedFile($key);
        return $file !== null && $file->isValid();
    }

    public function getParsedBody()
    {
        return $this->parsedBody;
    }

    public function withParsedBody($data): ServerRequestInterface
    {
        $new = clone $this;
        $new->parsedBody = $data;
        return $new;
    }

    public function getAttributes(): array
    {
        return $this->attributes;
    }

    public function getAttribute($name, $default = null)
    {
        return $this->attributes[$name] ?? $default;
    }

    public function withAttribute($name, $value): ServerRequestInterface
    {
        $new = clone $this;
        $new->attributes[$name] = $value;
        return $new;
    }

    public function withoutAttribute($name): ServerRequestInterface
    {
        $new = clone $this;
        unset($new->attributes[$name]);
        return $new;
    }

    // Implement the remaining PSR-7 Message methods...
    public function getProtocolVersion(): string
    {
        return $this->protocolVersion;
    }

    public function withProtocolVersion($version): ServerRequestInterface
    {
        $new = clone $this;
        $new->protocolVersion = $version;
        return $new;
    }

    public function getHeaders(): array
    {
        return $this->headers;
    }

    public function hasHeader($name): bool
    {
        return isset($this->headers[strtolower($name)]);
    }

    public function getHeader($name): array
    {
        $name = strtolower($name);
        return $this->headers[$name] ?? [];
    }

    public function getHeaderLine($name): string
    {
        return implode(', ', $this->getHeader($name));
    }

    public function withHeader($name, $value): ServerRequestInterface
    {
        $new = clone $this;
        $name = strtolower($name);
        $new->headers[$name] = is_array($value) ? $value : [$value];
        return $new;
    }

    public function withAddedHeader($name, $value): ServerRequestInterface
    {
        $new = clone $this;
        $name = strtolower($name);
        $new->headers[$name] = array_merge($this->getHeader($name), is_array($value) ? $value : [$value]);
        return $new;
    }

    public function withoutHeader($name): ServerRequestInterface
    {
        $new = clone $this;
        $name = strtolower($name);
        unset($new->headers[$name]);
        return $new;
    }

    public function getBody(): StreamInterface
    {
        return $this->body;
    }

    public function withBody(StreamInterface $body): ServerRequestInterface
    {
        $new = clone $this;
        $new->body = $body;
        return $new;
    }

    public function getRequestTarget(): string
    {
        $target = $this->uri->getPath();
        if ($query = $this->uri->getQuery()) {
            $target .= '?' . $query;
        }
        return $target ?: '/';
    }

    public function withRequestTarget($requestTarget): ServerRequestInterface
    {
        // This would require parsing the request target
        $new = clone $this;
        // Simplified implementation
        return $new;
    }

    public function getMethod(): string
    {
        return $this->method;
    }

    public function withMethod($method): ServerRequestInterface
    {
        $new = clone $this;
        $new->method = $method;
        return $new;
    }

    public function getUri(): UriInterface
    {
        return $this->uri;
    }

    public function withUri(UriInterface $uri, $preserveHost = false): ServerRequestInterface
    {
        $new = clone $this;
        $new->uri = $uri;
        return $new;
    }

    private function filterHeaders(array $headers): array
    {
        $normalized = [];
        foreach ($headers as $name => $value) {
            $name = strtolower($name);
            $normalized[$name] = is_array($value) ? $value : [$value];
        }
        return $normalized;
    }
}