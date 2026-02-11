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

use InvalidArgumentException;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Message\StreamInterface;
use Psr\Http\Message\UriInterface;

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
    private array $headerNames = [];
    private static array $trustedProxies = [];
    private static array $trustedHosts = [];

    private const MAX_JSON_BODY_SIZE = 10485760;

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
     * Create ServerRequest from PHP globals
     */
    public static function fromGlobals(): self
    {
        $method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
        $uri = self::buildUriFromGlobals();
        $headers = self::extractHeadersFromGlobals();
        $body = new Stream(fopen('php://input', 'r'));
        $protocol = self::extractProtocolVersion();

        $request = new self($method, $uri, $headers, $body, $protocol, $_SERVER);

        /** @var self $request */
        $request = $request->withCookieParams($_COOKIE);
        /** @var self $request */
        $request = $request->withQueryParams($_GET);
        /** @var self $request */
        $request = self::parseBody($request);
        /** @var self $request */
        $request = $request->withUploadedFiles(self::normalizeUploadedFiles($_FILES));

        return $request;
    }

    private static function extractProtocolVersion(): string
    {
        $protocol = '1.1';

        if (isset($_SERVER['SERVER_PROTOCOL'])) {
            $serverProtocol = trim($_SERVER['SERVER_PROTOCOL']);

            if (stripos($serverProtocol, 'HTTP/') === 0) {
                $protocol = substr($serverProtocol, 5);
            } else {
                preg_match('/(\d+(?:\.\d+)?)/', $serverProtocol, $matches);
                if ($matches) {
                    $protocol = $matches[1];
                }
            }
        }

        return self::normalizeProtocolVersion($protocol);
    }

    private static function normalizeProtocolVersion(string $version): string
    {
        $version = trim($version);
        $version = preg_replace('/[^0-9.]/', '', $version);

        $versionMap = [
            '' => '1.1',
            '1' => '1.1',
            '2' => '2.0',
            '3' => '3.0',
        ];

        if (isset($versionMap[$version])) {
            return $versionMap[$version];
        }

        if (!preg_match('/^\d+(\.\d+)?$/', $version)) {
            return '1.1';
        }

        $major = explode('.', $version)[0];
        if ($major < 1 || $major > 3) {
            return '1.1';
        }

        return $version;
    }

    private static function buildUriFromGlobals(): UriInterface
    {
        $scheme = 'http';
        if (
            (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ||
            (!empty($_SERVER['HTTP_X_FORWARDED_PROTO']) && $_SERVER['HTTP_X_FORWARDED_PROTO'] === 'https') ||
            (!empty($_SERVER['HTTP_X_FORWARDED_SSL']) && $_SERVER['HTTP_X_FORWARDED_SSL'] === 'on') ||
            (isset($_SERVER['SERVER_PORT']) && (int) $_SERVER['SERVER_PORT'] === 443)
        ) {
            $scheme = 'https';
        }

        $host = self::getHost();
        $port = self::getPort($scheme);
        $path = self::getPathURL();
        $query = $_SERVER['QUERY_STRING'] ?? '';

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

    public static function setTrustedHosts(array $hosts): void
    {
        self::$trustedHosts = $hosts;
    }

    private static function getHost(): string
    {
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
        if (strpos($host, ':') !== false) {
            $host = preg_replace('/:\d+$/', '', $host);
        }
        $host = strtolower($host);
        // Validate against trusted hosts if configured
        if (!empty(self::$trustedHosts)) {
            $isTrusted = false;
            foreach (self::$trustedHosts as $trustedHost) {
                // Support wildcard subdomains like ".example.com"
                if (
                    $trustedHost === $host ||
                    (str_starts_with($trustedHost, '.') && str_ends_with($host, $trustedHost))
                ) {
                    $isTrusted = true;

                    break;
                }
            }

            if (!$isTrusted) {
                return 'localhost'; // or throw exception in strict mode
            }
        }
        if (!preg_match('/^[a-zA-Z0-9.-]+$/', $host)) {
            return 'localhost';
        }

        return $host;
    }

    private static function getPort(string $scheme): ?int
    {
        if (!isset($_SERVER['SERVER_PORT'])) {
            return null;
        }

        $port = (int) $_SERVER['SERVER_PORT'];

        if (($scheme === 'http' && $port === 80) || ($scheme === 'https' && $port === 443)) {
            return null;
        }

        return $port;
    }

    private static function getPathURL(): string
    {
        $path = $_SERVER['REQUEST_URI'] ?? '/';

        if (($pos = strpos($path, '?')) !== false) {
            $path = substr($path, 0, $pos);
        }

        $path = rawurldecode($path);
        $path = preg_replace('#/+#', '/', $path);

        return $path ?: '/';
    }

    private static function extractHeadersFromGlobals(): array
    {
        $headers = [];

        foreach ($_SERVER as $key => $value) {
            if (strpos($key, 'HTTP_') === 0) {
                $name = self::normalizeHeaderName(substr($key, 5));
                $headers[$name] = [(string) $value];
            } elseif (in_array($key, ['CONTENT_TYPE', 'CONTENT_LENGTH', 'CONTENT_MD5'], true)) {
                $name = self::normalizeHeaderName($key);
                $headers[$name] = [(string) $value];
            }
        }

        return $headers;
    }

    private static function normalizeHeaderName(string $name): string
    {
        return str_replace(' ', '-', ucwords(strtolower(str_replace('_', ' ', $name))));
    }

    private static function parseBody(ServerRequestInterface $request): ServerRequestInterface
    {
        $method = $request->getMethod();
        $contentType = $request->getHeaderLine('Content-Type');

        if (!in_array($method, ['POST', 'PUT', 'PATCH', 'DELETE'], true)) {
            return $request;
        }

        if (
            stripos($contentType, 'application/x-www-form-urlencoded') !== false ||
            stripos($contentType, 'multipart/form-data') !== false
        ) {
            return $request->withParsedBody($_POST);
        }

        if (stripos($contentType, 'application/json') !== false) {
            $body = (string) $request->getBody();
            $bodySize = strlen($body);

            if ($bodySize > self::MAX_JSON_BODY_SIZE) {
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

    private function validateMethod(string $method): void
    {
        if (!\Plugs\Support\Enums\HttpMethod::tryFrom(strtoupper($method))) {
            throw new InvalidArgumentException(
                sprintf('Invalid HTTP method: %s', $method)
            );
        }
    }

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

    // ============================================
    // CUSTOM HELPER METHODS
    // ============================================

    /**
     * Get input value from query or parsed body
     *
     * @param string $key
     * @param mixed $default
     * @return mixed
     */
    public function input(string $key, $default = null)
    {
        // Check query params first
        if (isset($this->queryParams[$key])) {
            return $this->queryParams[$key];
        }

        // Then check parsed body
        if (is_array($this->parsedBody) && isset($this->parsedBody[$key])) {
            return $this->parsedBody[$key];
        }

        return $default;
    }

    /**
     * Check if input key exists
     *
     * @param string $key
     * @return bool
     */
    public function has(string $key): bool
    {
        return isset($this->queryParams[$key]) ||
            (is_array($this->parsedBody) && isset($this->parsedBody[$key]));
    }

    /**
     * Get all input data (query + body)
     *
     * @return array
     */
    public function all(): array
    {
        return array_merge(
            $this->queryParams,
            is_array($this->parsedBody) ? $this->parsedBody : []
        );
    }

    /**
     * Get only specified keys from input
     *
     * @param array $keys
     * @return array
     */
    public function only(array $keys): array
    {
        $all = $this->all();
        $result = [];

        foreach ($keys as $key) {
            if (isset($all[$key])) {
                $result[$key] = $all[$key];
            }
        }

        return $result;
    }

    /**
     * Get all input except specified keys
     *
     * @param array $keys
     * @return array
     */
    public function except(array $keys): array
    {
        $all = $this->all();

        foreach ($keys as $key) {
            unset($all[$key]);
        }

        return $all;
    }

    /**
     * Get input value as integer
     *
     * @param string $key
     * @param int $default
     * @return int
     */
    public function integer(string $key, int $default = 0): int
    {
        return (int) $this->input($key, $default);
    }

    /**
     * Clamp input value between min and max
     *
     * @param string $key
     * @param int $min
     * @param int $max
     * @return int
     */
    public function clamp(string $key, int $min, int $max): int
    {
        $value = $this->integer($key);

        return max($min, min($max, $value));
    }

    /**
     * Get input value as string
     *
     * @param string $key
     * @param string $default
     * @return string
     */
    public function string(string $key, string $default = ''): string
    {
        return (string) $this->input($key, $default);
    }

    /**
     * Get input value as boolean
     *
     * @param string $key
     * @param bool $default
     * @return bool
     */
    public function boolean(string $key, bool $default = false): bool
    {
        $value = $this->input($key);

        if ($value === null) {
            return $default;
        }

        return filter_var($value, FILTER_VALIDATE_BOOLEAN);
    }

    /**
     * Get input value as float
     *
     * @param string $key
     * @param float $default
     * @return float
     */
    public function float(string $key, float $default = 0.0): float
    {
        return (float) $this->input($key, $default);
    }

    /**
     * Get input value as Date
     *
     * @param string $key
     * @param string|null $format
     * @param string|null $timezone
     * @return \DateTimeImmutable|null
     */
    public function date(string $key, ?string $format = null, ?string $timezone = null): ?\DateTimeImmutable
    {
        $value = $this->input($key);

        if ($value === null || $value === '') {
            return null;
        }

        try {
            $tz = $timezone ? new \DateTimeZone($timezone) : null;

            if ($format) {
                $date = \DateTimeImmutable::createFromFormat($format, (string) $value, $tz);
            } else {
                $date = new \DateTimeImmutable((string) $value, $tz);
            }

            return $date !== false ? $date : null;
        } catch (\Throwable $e) {
            return null;
        }
    }

    /**
     * Get input value as Enum
     *
     * @param string $key
     * @param string $enumClass
     * @param mixed $default
     * @return mixed
     */
    public function enum(string $key, string $enumClass, $default = null)
    {
        $value = $this->input($key);

        if ($value === null || !function_exists('enum_exists') || !enum_exists($enumClass)) {
            return $default;
        }

        try {
            if (method_exists($enumClass, 'tryFrom')) {
                return $enumClass::tryFrom($value) ?? $default;
            }

            // For non-backed enums, we can't easily map from value without reflection or convention
            // Assuming backed enum usage mainly for request inputs
            return $default;

        } catch (\Throwable $e) {
            return $default;
        }
    }




    /**
     * Get uploaded file by key (Alias for getUploadedFile)
     *
     * @param string $key
     * @return mixed|null
     */
    public function file(string $key)
    {
        return $this->getUploadedFile($key);
    }

    /**
     * Get uploaded file by key
     *
     * @param string $key
     * @return mixed|null
     */
    public function getUploadedFile(string $key)
    {
        if (!isset($this->uploadedFiles[$key])) {
            return null;
        }

        $file = $this->uploadedFiles[$key];

        // If it's already an UploadedFile instance, return it
        if ($file instanceof \Plugs\Upload\UploadedFile) {
            return $file;
        }

        // If it's an array (raw $_FILES format), create UploadedFile
        if (is_array($file) && isset($file['tmp_name']) && class_exists('\Plugs\Upload\UploadedFile')) {
            return new \Plugs\Upload\UploadedFile($file);
        }

        return $file;
    }

    /**
     * Check if file was uploaded successfully
     *
     * @param string $key
     * @return bool
     */
    public function hasFile(string $key): bool
    {
        $file = $this->getUploadedFile($key);

        if ($file === null) {
            return false;
        }

        // If it's an UploadedFile instance, check if valid
        if (method_exists($file, 'isValid')) {
            return $file->isValid();
        }

        // If it's a PSR-7 UploadedFileInterface, check error
        if ($file instanceof \Psr\Http\Message\UploadedFileInterface) {
            return $file->getError() === UPLOAD_ERR_OK;
        }

        return true;
    }

    /**
     * Check if request is AJAX
     *
     * @return bool
     */
    public function isAjax(): bool
    {
        return strtolower($this->getHeaderLine('X-Requested-With')) === 'xmlhttprequest';
    }

    /**
     * Check if request is JSON
     *
     * @return bool
     */
    public function isJson(): bool
    {
        return stripos($this->getHeaderLine('Content-Type'), 'application/json') !== false;
    }

    /**
     * Check if request expects JSON response
     *
     * @return bool
     */
    public function expectsJson(): bool
    {
        return $this->isJson() || $this->isAjax();
    }

    public static function setTrustedProxies(array $proxies): void
    {
        self::$trustedProxies = $proxies;
    }

    /**
     * Get client IP address
     *
     * @return string|null
     */
    public function getClientIp(): ?string
    {
        $remoteAddr = $this->serverParams['REMOTE_ADDR'] ?? null;
        // Only trust proxy headers if REMOTE_ADDR is in trusted proxies list
        if (empty(self::$trustedProxies)) {
            return $remoteAddr;
        }
        $isTrusted = false;
        foreach (self::$trustedProxies as $proxy) {
            if ($proxy === '*' || $proxy === $remoteAddr) {
                $isTrusted = true;

                break;
            }
        }
        if (!$isTrusted) {
            return $remoteAddr;
        }
        // Now safe to check proxy headers
        if (!empty($this->serverParams['HTTP_X_FORWARDED_FOR'])) {
            $ips = explode(',', $this->serverParams['HTTP_X_FORWARDED_FOR']);

            return trim($ips[0]);
        }

        return $remoteAddr;
    }

    /**
     * Get user agent
     *
     * @return string|null
     */
    public function getUserAgent(): ?string
    {
        return $this->getHeaderLine('User-Agent') ?: null;
    }

    /**
     * Get referer URL
     *
     * @return string|null
     */
    public function getReferer(): ?string
    {
        return $this->getHeaderLine('Referer') ?: null;
    }

    /**
     * Check if request is secure (HTTPS)
     *
     * @return bool
     */
    public function isSecure(): bool
    {
        return $this->uri->getScheme() === 'https';
    }

    /**
     * Get full URL
     *
     * @return string
     */
    public function getFullUrl(): string
    {
        return (string) $this->uri;
    }

    /**
     * Get URL path
     *
     * @return string
     */
    public function getPath(): string
    {
        return $this->uri->getPath();
    }

    /**
     * Check if the request path matches a pattern
     *
     * @param mixed ...$patterns
     * @return bool
     */
    public function is(...$patterns): bool
    {
        $path = $this->getPath();
        $path = rawurldecode($path);

        // Normalize path to ensure it starts with /
        if (!str_starts_with($path, '/')) {
            $path = '/' . $path;
        }

        foreach ($patterns as $pattern) {
            if (is_array($pattern)) {
                if ($this->is(...$pattern)) {
                    return true;
                }

                continue;
            }

            $pattern = (string) $pattern;

            // Normalize pattern
            if (!str_starts_with($pattern, '/')) {
                $pattern = '/' . $pattern;
            }

            // Exact match
            if ($pattern === $path) {
                return true;
            }

            // Convert wildcard pattern to regex
            $pattern = preg_quote($pattern, '#');
            $pattern = str_replace('\*', '.*', $pattern);

            if (preg_match('#^' . $pattern . '\z#u', $path)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Check if the route matches a pattern
     *
     * @param mixed ...$patterns
     * @return bool
     */
    public function routeIs(...$patterns): bool
    {
        // Try to get current route name from attributes
        // This relies on the framework's Router setting the _route attribute
        // The attribute might be a Route object or array or string
        $route = $this->getAttribute('_route');

        if (!$route) {
            return false;
        }

        // Get route name
        $routeName = null;
        if (is_object($route) && method_exists($route, 'getName')) {
            $routeName = $route->getName();
        } elseif (is_string($route)) {
            $routeName = $route; // If stored as string
        }

        if (!$routeName) {
            return false;
        }

        foreach ($patterns as $pattern) {
            if (is_array($pattern)) {
                if ($this->routeIs(...$pattern)) {
                    return true;
                }

                continue;
            }

            $pattern = (string) $pattern;

            if ($pattern === $routeName) {
                return true;
            }

            $pattern = preg_quote($pattern, '#');
            $pattern = str_replace('\*', '.*', $pattern);

            if (preg_match('#^' . $pattern . '\z#u', $routeName)) {
                return true;
            }
        }

        return false;
    }

    // ============================================
    // PSR-7 ServerRequestInterface Implementation
    // ============================================

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
        // @phpstan-ignore-next-line
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

    public function getProtocolVersion(): string
    {
        return $this->protocolVersion;
    }

    public function withProtocolVersion($version): ServerRequestInterface
    {
        $normalizedVersion = $this->normalizeProtocolVersion($version);

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
        if (preg_match('/\s/', $requestTarget)) {
            throw new InvalidArgumentException(
                'Invalid request target: must be a string without whitespace'
            );
        }

        $new = clone $this;

        return $new;
    }

    public function getMethod(): string
    {
        return $this->method;
    }

    public function withMethod($method): ServerRequestInterface
    {
        $this->validateMethod($method);

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

    public function withUri(UriInterface $uri, $preserveHost = false): static
    {
        $new = clone $this;
        $new->uri = $uri;

        if (!$preserveHost || !$this->hasHeader('Host')) {
            /** @var static $new */
            $new = $new->updateHostFromUri();
        }

        return $new;
    }

    private function updateHostFromUri(): static
    {
        $host = $this->uri->getHost();

        if ($host === '') {
            return $this;
        }

        if (($port = $this->uri->getPort()) !== null) {
            $host .= ':' . $port;
        }

        /** @var static $new */
        $new = $this->withHeader('Host', $host);

        return $new;
    }

    private function validateHeaderName($name): void
    {
        if (!is_string($name) || !preg_match('/^[a-zA-Z0-9\'`#$%&*+.^_|~!-]+$/', $name)) {
            throw new InvalidArgumentException('Invalid header name');
        }
    }

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
