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
use InvalidArgumentException;

class ServerRequest implements ServerRequestInterface
{
    private string $method;
    private UriInterface $uri;
    private array $headers = [];
    private StreamInterface $body;
    private string $protocolVersion;
    private array $serverParams;
    private array $cookieParams = [];
    private array $queryParams = [];
    private array $uploadedFiles = [];
    private $parsedBody = null;
    private array $attributes = [];
    private array $headerNames = []; // For case-insensitive header lookup

    /** @var int Maximum body size for JSON parsing (10MB) */
    private const MAX_JSON_BODY_SIZE = 10485760;

    /** @var array Valid HTTP methods */
    private const VALID_HTTP_METHODS = [
        'GET',
        'POST',
        'PUT',
        'DELETE',
        'PATCH',
        'HEAD',
        'OPTIONS',
        'TRACE',
        'CONNECT'
    ];

    /** @var array Valid HTTP protocol versions */
    private const VALID_PROTOCOL_VERSIONS = [
        '1.0',
        '1.1',
        '2',
        '2.0',
        '3',
        '3.0'
    ];

    public function __construct(
        string $method,
        $uri,
        array $headers = [],
        ?StreamInterface $body = null,
        string $protocolVersion = '1.1',
        array $serverParams = []
    ) {
        $this->validateMethod($method);
        $this->method = strtoupper($method);
        $this->uri = $uri instanceof UriInterface ? $uri : new Uri((string) $uri);
        $this->setHeaders($headers);
        $this->body = $body ?? new Stream(fopen('php://temp', 'r+'));
        $this->protocolVersion = $this->normalizeProtocolVersion($protocolVersion);
        $this->serverParams = $serverParams;
    }

    /**
     * Create ServerRequest from PHP globals with enhanced security
     */
    public static function fromGlobals(): self
    {
        $method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
        $uri = self::buildUriFromGlobals();
        $headers = self::extractHeadersFromGlobals();
        $body = new Stream(fopen('php://input', 'r'));

        // Get protocol version - more robust parsing
        $protocol = self::extractProtocolVersion();

        $request = new self($method, $uri, $headers, $body, $protocol, $_SERVER);

        // Set cookies
        $request = $request->withCookieParams($_COOKIE ?? []);

        // Set query parameters
        $request = $request->withQueryParams($_GET ?? []);

        // Parse body based on content type
        $request = self::parseBody($request);

        // Normalize uploaded files
        $request = $request->withUploadedFiles(self::normalizeUploadedFiles($_FILES ?? []));

        return $request;
    }

    /**
     * Extract and normalize protocol version from server variables
     */
    private static function extractProtocolVersion(): string
    {
        $protocol = '1.1'; // Default fallback

        if (isset($_SERVER['SERVER_PROTOCOL'])) {
            $serverProtocol = trim($_SERVER['SERVER_PROTOCOL']);

            // Handle common formats: "HTTP/1.1", "HTTP/2", etc.
            if (stripos($serverProtocol, 'HTTP/') === 0) {
                $protocol = substr($serverProtocol, 5);
            } else {
                // Try to extract any version number
                preg_match('/(\d+(?:\.\d+)?)/', $serverProtocol, $matches);
                if ($matches) {
                    $protocol = $matches[1];
                }
            }
        }

        return self::normalizeProtocolVersion($protocol);
    }

    /**
     * Normalize protocol version to standard format
     */
    private static function normalizeProtocolVersion(string $version): string
    {
        // Clean up the version string
        $version = trim($version);

        // Remove any non-numeric characters except dots
        $version = preg_replace('/[^0-9.]/', '', $version);

        // Handle common cases
        $versionMap = [
            '' => '1.1',
            '1' => '1.1',
            '2' => '2.0',
            '3' => '3.0',
        ];

        if (isset($versionMap[$version])) {
            return $versionMap[$version];
        }

        // Validate the version format
        if (!preg_match('/^\d+(\.\d+)?$/', $version)) {
            // If still invalid, fall back to default
            return '1.1';
        }

        // Ensure we have a valid version
        $major = explode('.', $version)[0];
        if ($major < 1 || $major > 3) {
            return '1.1';
        }

        return $version;
    }

    /**
     * Build URI from globals with security considerations
     */
    private static function buildUriFromGlobals(): UriInterface
    {
        // Determine scheme with proxy support
        $scheme = 'http';
        if (
            (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ||
            (!empty($_SERVER['HTTP_X_FORWARDED_PROTO']) && $_SERVER['HTTP_X_FORWARDED_PROTO'] === 'https') ||
            (!empty($_SERVER['HTTP_X_FORWARDED_SSL']) && $_SERVER['HTTP_X_FORWARDED_SSL'] === 'on') ||
            (isset($_SERVER['SERVER_PORT']) && (int) $_SERVER['SERVER_PORT'] === 443)
        ) {
            $scheme = 'https';
        }

        // Get host with security validation
        $host = self::getHost();

        // Get port
        $port = self::getPort($scheme);

        // Get path (decode and sanitize)
        $path = self::getPath();

        // Get query string
        $query = $_SERVER['QUERY_STRING'] ?? '';

        // Build URI
        $uriString = sprintf('%s://%s', $scheme, $host);

        if ($port !== null) {
            $uriString .= ':' . $port;
        }

        $uriString .= $path;

        if ($query !== '') {
            $uriString .= '?' . $query;
        }

        return new Uri($uriString);
    }

    /**
     * Get validated host from server variables
     */
    private static function getHost(): string
    {
        // Priority: HTTP_HOST > SERVER_NAME > SERVER_ADDR > localhost
        $host = '';

        if (!empty($_SERVER['HTTP_HOST'])) {
            $host = $_SERVER['HTTP_HOST'];
        } elseif (!empty($_SERVER['SERVER_NAME'])) {
            $host = $_SERVER['SERVER_NAME'];
        } elseif (!empty($_SERVER['SERVER_ADDR'])) {
            $host = $_SERVER['SERVER_ADDR'];
        } else {
            $host = 'localhost';
        }

        // Strip port from HTTP_HOST if present
        if (strpos($host, ':') !== false) {
            $host = preg_replace('/:\d+$/', '', $host);
        }

        // Validate host to prevent header injection
        if (!preg_match('/^[a-zA-Z0-9.-]+$/', $host)) {
            return 'localhost';
        }

        return strtolower($host);
    }

    /**
     * Get port if non-standard
     */
    private static function getPort(string $scheme): ?int
    {
        if (!isset($_SERVER['SERVER_PORT'])) {
            return null;
        }

        $port = (int) $_SERVER['SERVER_PORT'];

        // Omit standard ports
        if (($scheme === 'http' && $port === 80) || ($scheme === 'https' && $port === 443)) {
            return null;
        }

        return $port;
    }

    /**
     * Get sanitized request path
     */
    private static function getPath(): string
    {
        $path = $_SERVER['REQUEST_URI'] ?? '/';

        // Remove query string
        if (($pos = strpos($path, '?')) !== false) {
            $path = substr($path, 0, $pos);
        }

        // Decode and normalize path
        $path = rawurldecode($path);

        // Remove multiple slashes
        $path = preg_replace('#/+#', '/', $path);

        return $path ?: '/';
    }

    /**
     * Extract headers from $_SERVER with proper normalization
     */
    private static function extractHeadersFromGlobals(): array
    {
        $headers = [];

        foreach ($_SERVER as $key => $value) {
            // HTTP_ prefixed headers
            if (strpos($key, 'HTTP_') === 0) {
                $name = self::normalizeHeaderName(substr($key, 5));
                $headers[$name] = [(string) $value];
            }
            // Special case headers
            elseif (in_array($key, ['CONTENT_TYPE', 'CONTENT_LENGTH', 'CONTENT_MD5'], true)) {
                $name = self::normalizeHeaderName($key);
                $headers[$name] = [(string) $value];
            }
        }

        return $headers;
    }

    /**
     * Normalize header name from SERVER format
     */
    private static function normalizeHeaderName(string $name): string
    {
        return str_replace(' ', '-', ucwords(strtolower(str_replace('_', ' ', $name))));
    }

    /**
     * Parse request body based on content type
     */
    private static function parseBody(ServerRequestInterface $request): ServerRequestInterface
    {
        $method = $request->getMethod();
        $contentType = $request->getHeaderLine('Content-Type');

        if (!in_array($method, ['POST', 'PUT', 'PATCH', 'DELETE'], true)) {
            return $request;
        }

        // Form data
        if (
            stripos($contentType, 'application/x-www-form-urlencoded') !== false ||
            stripos($contentType, 'multipart/form-data') !== false
        ) {
            return $request->withParsedBody($_POST ?? []);
        }

        // JSON data with size limit
        if (stripos($contentType, 'application/json') !== false) {
            $body = (string) $request->getBody();
            $bodySize = strlen($body);

            if ($bodySize > self::MAX_JSON_BODY_SIZE) {
                // Don't parse oversized bodies
                return $request;
            }

            if ($body !== '') {
                $parsed = json_decode($body, true);
                if (json_last_error() === JSON_ERROR_NONE && is_array($parsed)) {
                    return $request->withParsedBody($parsed);
                }
            }
        }

        return $request;
    }

    /**
     * Normalize uploaded files to PSR-7 structure
     */
    private static function normalizeUploadedFiles(array $files): array
    {
        $normalized = [];

        foreach ($files as $key => $value) {
            if ($value instanceof \Psr\Http\Message\UploadedFileInterface) {
                $normalized[$key] = $value;
                continue;
            }

            if (is_array($value) && isset($value['tmp_name'])) {
                $normalized[$key] = self::createUploadedFile($value);
                continue;
            }

            if (is_array($value)) {
                $normalized[$key] = self::normalizeUploadedFiles($value);
            }
        }

        return $normalized;
    }

    /**
     * Create uploaded file from array
     */
    private static function createUploadedFile(array $value)
    {
        if (is_array($value['tmp_name'])) {
            return self::normalizeNestedFileArray($value);
        }

        if (class_exists('\Plugs\Upload\UploadedFile')) {
            return new \Plugs\Upload\UploadedFile($value);
        }

        return $value;
    }

    /**
     * Normalize nested file upload arrays
     */
    private static function normalizeNestedFileArray(array $files): array
    {
        $normalized = [];

        foreach (array_keys($files['tmp_name']) as $key) {
            $normalized[$key] = [
                'name' => $files['name'][$key],
                'type' => $files['type'][$key],
                'tmp_name' => $files['tmp_name'][$key],
                'error' => $files['error'][$key],
                'size' => $files['size'][$key],
            ];

            if (class_exists('\Plugs\Upload\UploadedFile')) {
                $normalized[$key] = new \Plugs\Upload\UploadedFile($normalized[$key]);
            }
        }

        return $normalized;
    }

    /**
     * Validate HTTP method
     */
    private function validateMethod(string $method): void
    {
        if (!in_array(strtoupper($method), self::VALID_HTTP_METHODS, true)) {
            throw new InvalidArgumentException(
                sprintf('Invalid HTTP method: %s', $method)
            );
        }
    }

    /**
     * Set headers with case-insensitive mapping
     */
    private function setHeaders(array $headers): void
    {
        $this->headers = [];
        $this->headerNames = [];

        foreach ($headers as $name => $value) {
            $normalized = strtolower($name);
            $this->headerNames[$normalized] = $name;
            $this->headers[$name] = is_array($value) ? $value : [$value];
        }
    }

    // PSR-7 ServerRequestInterface Implementation

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

    public function getParsedBody()
    {
        return $this->parsedBody;
    }

    public function withParsedBody($data): ServerRequestInterface
    {
        if ($data !== null && !is_array($data) && !is_object($data)) {
            throw new InvalidArgumentException(
                'Parsed body must be an array, object, or null'
            );
        }

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
        if (!is_string($name)) {
            throw new InvalidArgumentException('Attribute name must be a string');
        }

        return $this->attributes[$name] ?? $default;
    }

    public function withAttribute($name, $value): ServerRequestInterface
    {
        if (!is_string($name)) {
            throw new InvalidArgumentException('Attribute name must be a string');
        }

        $new = clone $this;
        $new->attributes[$name] = $value;
        return $new;
    }

    public function withoutAttribute($name): ServerRequestInterface
    {
        if (!is_string($name)) {
            throw new InvalidArgumentException('Attribute name must be a string');
        }

        $new = clone $this;
        unset($new->attributes[$name]);
        return $new;
    }

    // PSR-7 MessageInterface Implementation

    public function getProtocolVersion(): string
    {
        return $this->protocolVersion;
    }

    public function withProtocolVersion($version): ServerRequestInterface
    {
        if (!is_string($version)) {
            $version = (string) $version;
        }

        $normalizedVersion = $this->normalizeProtocolVersion($version);

        if ($normalizedVersion === $this->protocolVersion) {
            return $this;
        }

        $new = clone $this;
        $new->protocolVersion = $normalizedVersion;
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
        $normalized = strtolower($name);

        if (!isset($this->headerNames[$normalized])) {
            return [];
        }

        $originalName = $this->headerNames[$normalized];
        return $this->headers[$originalName];
    }

    public function getHeaderLine($name): string
    {
        return implode(', ', $this->getHeader($name));
    }

    public function withHeader($name, $value): ServerRequestInterface
    {
        $this->validateHeaderName($name);
        $value = $this->validateHeaderValue($value);

        $normalized = strtolower($name);
        $new = clone $this;

        if (isset($new->headerNames[$normalized])) {
            unset($new->headers[$new->headerNames[$normalized]]);
        }

        $new->headerNames[$normalized] = $name;
        $new->headers[$name] = $value;

        return $new;
    }

    public function withAddedHeader($name, $value): ServerRequestInterface
    {
        $this->validateHeaderName($name);
        $value = $this->validateHeaderValue($value);

        $normalized = strtolower($name);
        $new = clone $this;

        if (isset($new->headerNames[$normalized])) {
            $originalName = $new->headerNames[$normalized];
            $new->headers[$originalName] = array_merge($new->headers[$originalName], $value);
        } else {
            $new->headerNames[$normalized] = $name;
            $new->headers[$name] = $value;
        }

        return $new;
    }

    public function withoutHeader($name): ServerRequestInterface
    {
        $normalized = strtolower($name);

        if (!isset($this->headerNames[$normalized])) {
            return $this;
        }

        $originalName = $this->headerNames[$normalized];
        $new = clone $this;
        unset($new->headers[$originalName], $new->headerNames[$normalized]);

        return $new;
    }

    public function getBody(): StreamInterface
    {
        return $this->body;
    }

    public function withBody(StreamInterface $body): ServerRequestInterface
    {
        if ($body === $this->body) {
            return $this;
        }

        $new = clone $this;
        $new->body = $body;
        return $new;
    }

    // PSR-7 RequestInterface Implementation

    public function getRequestTarget(): string
    {
        $target = $this->uri->getPath();

        if ($target === '') {
            $target = '/';
        }

        if ($query = $this->uri->getQuery()) {
            $target .= '?' . $query;
        }

        return $target;
    }

    public function withRequestTarget($requestTarget): ServerRequestInterface
    {
        if (!is_string($requestTarget) || preg_match('/\s/', $requestTarget)) {
            throw new InvalidArgumentException(
                'Invalid request target: must be a string without whitespace'
            );
        }

        $new = clone $this;
        // Note: PSR-7 doesn't require parsing the request target
        return $new;
    }

    public function getMethod(): string
    {
        return $this->method;
    }

    public function withMethod($method): ServerRequestInterface
    {
        if (!is_string($method)) {
            throw new InvalidArgumentException('Method must be a string');
        }

        $this->validateMethod($method);
        $method = strtoupper($method);

        if ($method === $this->method) {
            return $this;
        }

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

        if (!$preserveHost || !$this->hasHeader('Host')) {
            $new = $new->updateHostFromUri();
        }

        return $new;
    }

    /**
     * Update Host header from URI
     */
    private function updateHostFromUri(): self
    {
        $host = $this->uri->getHost();

        if ($host === '') {
            return $this;
        }

        if (($port = $this->uri->getPort()) !== null) {
            $host .= ':' . $port;
        }

        return $this->withHeader('Host', $host);
    }

    // Helper Methods

    /**
     * Get uploaded file by key
     */
    public function getUploadedFile(string $key)
    {
        $files = $this->getUploadedFiles();

        if (!isset($files[$key])) {
            return null;
        }

        $file = $files[$key];

        if (is_array($file) && class_exists('\Plugs\Upload\UploadedFile')) {
            return new \Plugs\Upload\UploadedFile($file);
        }

        return $file;
    }

    /**
     * Check if file was uploaded successfully
     */
    public function hasFile(string $key): bool
    {
        $file = $this->getUploadedFile($key);

        if ($file === null) {
            return false;
        }

        if (method_exists($file, 'isValid')) {
            return $file->isValid();
        }

        return true;
    }

    /**
     * Get input value from query or parsed body
     */
    public function input(string $key, $default = null)
    {
        $params = array_merge($this->queryParams, (array) $this->parsedBody);
        return $params[$key] ?? $default;
    }

    /**
     * Check if input key exists
     */
    public function has(string $key): bool
    {
        $params = array_merge($this->queryParams, (array) $this->parsedBody);
        return array_key_exists($key, $params);
    }

    /**
     * Validate header name
     */
    private function validateHeaderName($name): void
    {
        if (!is_string($name) || !preg_match('/^[a-zA-Z0-9\'`#$%&*+.^_|~!-]+$/', $name)) {
            throw new InvalidArgumentException('Invalid header name');
        }
    }

    /**
     * Validate and normalize header value
     */
    private function validateHeaderValue($value): array
    {
        if (!is_array($value)) {
            $value = [$value];
        }

        if (empty($value)) {
            throw new InvalidArgumentException('Header value cannot be empty');
        }

        foreach ($value as $v) {
            if (!is_string($v) && !is_numeric($v)) {
                throw new InvalidArgumentException('Header value must be string or numeric');
            }

            $v = (string) $v;

            if (
                preg_match("/(?:(?:(?<!\r)\n)|(?:\r(?!\n))|(?:\r\n(?![ \t])))/", $v) ||
                preg_match('/[^\x09\x0a\x0d\x20-\x7E\x80-\xFE]/', $v)
            ) {
                throw new InvalidArgumentException('Invalid header value');
            }
        }

        return array_map('strval', $value);
    }
}