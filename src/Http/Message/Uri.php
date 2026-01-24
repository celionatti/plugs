<?php

declare(strict_types=1);

namespace Plugs\Http\Message;

/*
|--------------------------------------------------------------------------
| Uri Class
|--------------------------------------------------------------------------
|
| This class represents a URI (Uniform Resource Identifier) as defined in
| RFC 3986. It provides methods to retrieve and manipulate different parts.
*/

use Psr\Http\Message\UriInterface;

class Uri implements UriInterface
{
    private $scheme = '';
    private $userInfo = '';
    private $host = '';
    private $port;
    private $path = '';
    private $query = '';
    private $fragment = '';

    private const STANDARD_PORTS = [
        'http' => 80,
        'https' => 443,
    ];

    public function __construct(string $uri = '')
    {
        if ($uri !== '') {
            $parts = parse_url($uri);
            if ($parts === false) {
                throw new \InvalidArgumentException("Unable to parse URI: $uri");
            }

            $this->scheme = isset($parts['scheme']) ? strtolower($parts['scheme']) : '';
            $this->userInfo = $parts['user'] ?? '';
            $this->host = isset($parts['host']) ? strtolower($parts['host']) : '';
            $this->port = $parts['port'] ?? null;
            $this->path = $parts['path'] ?? '';
            $this->query = $parts['query'] ?? '';
            $this->fragment = $parts['fragment'] ?? '';

            if (isset($parts['pass'])) {
                $this->userInfo .= ':' . $parts['pass'];
            }

            // Remove standard ports
            $this->filterPort();
        }
    }

    public function getScheme(): string
    {
        return $this->scheme;
    }

    public function getAuthority(): string
    {
        if ($this->host === '') {
            return '';
        }

        $authority = $this->host;

        if ($this->userInfo !== '') {
            $authority = $this->userInfo . '@' . $authority;
        }

        if ($this->port !== null) {
            $authority .= ':' . $this->port;
        }

        return $authority;
    }

    public function getUserInfo(): string
    {
        return $this->userInfo;
    }

    public function getHost(): string
    {
        return $this->host;
    }

    public function getPort(): ?int
    {
        return $this->port;
    }

    public function getPath(): string
    {
        return $this->path;
    }

    public function getQuery(): string
    {
        return $this->query;
    }

    public function getFragment(): string
    {
        return $this->fragment;
    }

    public function withScheme($scheme): UriInterface
    {
        $scheme = strtolower((string) $scheme);

        if ($scheme === $this->scheme) {
            return $this;
        }

        $new = clone $this;
        $new->scheme = $scheme;
        $new->filterPort();

        return $new;
    }

    public function withUserInfo($user, $password = null): UriInterface
    {
        $userInfo = (string) $user;
        if ($password !== null && $password !== '') {
            $userInfo .= ':' . $password;
        }

        if ($userInfo === $this->userInfo) {
            return $this;
        }

        $new = clone $this;
        $new->userInfo = $userInfo;

        return $new;
    }

    public function withHost($host): UriInterface
    {
        $host = strtolower((string) $host);

        if ($host === $this->host) {
            return $this;
        }

        $new = clone $this;
        $new->host = $host;

        return $new;
    }

    public function withPort($port): UriInterface
    {
        if ($port !== null) {
            $port = (int) $port;

            if ($port < 1 || $port > 65535) {
                throw new \InvalidArgumentException(
                    'Invalid port: ' . $port . '. Must be between 1 and 65535'
                );
            }
        }

        if ($port === $this->port) {
            return $this;
        }

        $new = clone $this;
        $new->port = $port;
        $new->filterPort();

        return $new;
    }

    public function withPath($path): UriInterface
    {
        $path = (string) $path;

        if ($path === $this->path) {
            return $this;
        }

        $new = clone $this;
        $new->path = $path;

        return $new;
    }

    public function withQuery($query): UriInterface
    {
        $query = (string) $query;

        // Strip leading '?' if present
        if (strpos($query, '?') === 0) {
            $query = substr($query, 1);
        }

        if ($query === $this->query) {
            return $this;
        }

        $new = clone $this;
        $new->query = $query;

        return $new;
    }

    public function withFragment($fragment): UriInterface
    {
        $fragment = (string) $fragment;

        // Strip leading '#' if present
        if (strpos($fragment, '#') === 0) {
            $fragment = substr($fragment, 1);
        }

        if ($fragment === $this->fragment) {
            return $this;
        }

        $new = clone $this;
        $new->fragment = $fragment;

        return $new;
    }

    public function __toString(): string
    {
        $uri = '';

        if ($this->scheme !== '') {
            $uri .= $this->scheme . ':';
        }

        $authority = $this->getAuthority();
        if ($authority !== '') {
            $uri .= '//' . $authority;
        }

        $path = $this->path;

        // Add leading slash if there's an authority and path doesn't start with /
        if ($authority !== '' && $path !== '' && strpos($path, '/') !== 0) {
            $path = '/' . $path;
        }

        $uri .= $path;

        if ($this->query !== '') {
            $uri .= '?' . $this->query;
        }

        if ($this->fragment !== '') {
            $uri .= '#' . $this->fragment;
        }

        return $uri;
    }

    /**
     * Filter the port to return null for standard ports
     */
    private function filterPort(): void
    {
        if ($this->port !== null &&
            isset(self::STANDARD_PORTS[$this->scheme]) &&
            $this->port === self::STANDARD_PORTS[$this->scheme]) {
            $this->port = null;
        }
    }
}
