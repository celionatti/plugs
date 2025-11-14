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
use InvalidArgumentException;

class Response implements ResponseInterface
{
    private int $statusCode;
    private string $reasonPhrase;
    private array $headers = [];
    private StreamInterface $body;
    private string $protocol;
    private array $headerNames = []; // For case-insensitive lookup

    /** @var array HTTP status code reason phrases */
    private const REASON_PHRASES = [
        // 1xx Informational
        100 => 'Continue',
        101 => 'Switching Protocols',
        102 => 'Processing',
        103 => 'Early Hints',

        // 2xx Success
        200 => 'OK',
        201 => 'Created',
        202 => 'Accepted',
        203 => 'Non-Authoritative Information',
        204 => 'No Content',
        205 => 'Reset Content',
        206 => 'Partial Content',
        207 => 'Multi-Status',
        208 => 'Already Reported',
        226 => 'IM Used',

        // 3xx Redirection
        300 => 'Multiple Choices',
        301 => 'Moved Permanently',
        302 => 'Found',
        303 => 'See Other',
        304 => 'Not Modified',
        305 => 'Use Proxy',
        307 => 'Temporary Redirect',
        308 => 'Permanent Redirect',

        // 4xx Client Error
        400 => 'Bad Request',
        401 => 'Unauthorized',
        402 => 'Payment Required',
        403 => 'Forbidden',
        404 => 'Not Found',
        405 => 'Method Not Allowed',
        406 => 'Not Acceptable',
        407 => 'Proxy Authentication Required',
        408 => 'Request Timeout',
        409 => 'Conflict',
        410 => 'Gone',
        411 => 'Length Required',
        412 => 'Precondition Failed',
        413 => 'Payload Too Large',
        414 => 'URI Too Long',
        415 => 'Unsupported Media Type',
        416 => 'Range Not Satisfiable',
        417 => 'Expectation Failed',
        418 => 'I\'m a teapot',
        421 => 'Misdirected Request',
        422 => 'Unprocessable Entity',
        423 => 'Locked',
        424 => 'Failed Dependency',
        425 => 'Too Early',
        426 => 'Upgrade Required',
        428 => 'Precondition Required',
        429 => 'Too Many Requests',
        431 => 'Request Header Fields Too Large',
        451 => 'Unavailable For Legal Reasons',

        // 5xx Server Error
        500 => 'Internal Server Error',
        501 => 'Not Implemented',
        502 => 'Bad Gateway',
        503 => 'Service Unavailable',
        504 => 'Gateway Timeout',
        505 => 'HTTP Version Not Supported',
        506 => 'Variant Also Negotiates',
        507 => 'Insufficient Storage',
        508 => 'Loop Detected',
        510 => 'Not Extended',
        511 => 'Network Authentication Required',
    ];

    public function __construct(
        int $statusCode = 200,
        ?StreamInterface $body = null,
        array $headers = [],
        string $protocol = '1.1',
        string $reasonPhrase = ''
    ) {
        $this->validateStatusCode($statusCode);
        $this->statusCode = $statusCode;
        $this->body = $body ?? new Stream(fopen('php://temp', 'r+'));
        $this->protocol = $protocol;
        $this->reasonPhrase = $reasonPhrase;

        $this->setHeaders($headers);
    }

    /**
     * Validate status code
     */
    private function validateStatusCode(int $code): void
    {
        if ($code < 100 || $code > 599) {
            throw new InvalidArgumentException(
                "Invalid status code: {$code}. Must be between 100 and 599."
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
            $this->validateHeaderName($name);
            $value = $this->validateHeaderValue($value);

            $normalized = strtolower($name);
            $this->headerNames[$normalized] = $name;
            $this->headers[$name] = $value;
        }
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

            // Check for invalid characters
            if (
                preg_match("/(?:(?:(?<!\r)\n)|(?:\r(?!\n))|(?:\r\n(?![ \t])))/", $v) ||
                preg_match('/[^\x09\x0a\x0d\x20-\x7E\x80-\xFE]/', $v)
            ) {
                throw new InvalidArgumentException('Invalid header value');
            }
        }

        return array_map('strval', $value);
    }

    // PSR-7 ResponseInterface Implementation

    public function getStatusCode(): int
    {
        return $this->statusCode;
    }

    public function withStatus($code, $reasonPhrase = ''): ResponseInterface
    {
        if (!is_int($code)) {
            throw new InvalidArgumentException('Status code must be an integer');
        }

        $this->validateStatusCode($code);

        if ($code === $this->statusCode && $reasonPhrase === $this->reasonPhrase) {
            return $this;
        }

        $new = clone $this;
        $new->statusCode = $code;
        $new->reasonPhrase = (string) $reasonPhrase;

        return $new;
    }

    public function getReasonPhrase(): string
    {
        if ($this->reasonPhrase !== '') {
            return $this->reasonPhrase;
        }

        return self::REASON_PHRASES[$this->statusCode] ?? '';
    }

    // PSR-7 MessageInterface Implementation

    public function getProtocolVersion(): string
    {
        return $this->protocol;
    }

    public function withProtocolVersion($version): ResponseInterface
    {
        if (!is_string($version)) {
            $version = (string) $version;
        }

        if (!preg_match('/^\d+\.\d+$/', $version)) {
            throw new InvalidArgumentException('Invalid protocol version');
        }

        if ($version === $this->protocol) {
            return $this;
        }

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

    public function withHeader($name, $value): ResponseInterface
    {
        $this->validateHeaderName($name);
        $value = $this->validateHeaderValue($value);

        $normalized = strtolower($name);
        $new = clone $this;

        // Remove old header if exists
        if (isset($new->headerNames[$normalized])) {
            $oldName = $new->headerNames[$normalized];
            unset($new->headers[$oldName]);
        }

        $new->headerNames[$normalized] = $name;
        $new->headers[$name] = $value;

        return $new;
    }

    public function withAddedHeader($name, $value): ResponseInterface
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

    public function withoutHeader($name): ResponseInterface
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

    public function withBody(StreamInterface $body): ResponseInterface
    {
        if ($body === $this->body) {
            return $this;
        }

        $new = clone $this;
        $new->body = $body;
        return $new;
    }

    // Helper Methods

    /**
     * Send the response to the client
     */
    public function send(): void
    {
        // Don't send if headers already sent
        if (headers_sent()) {
            return;
        }

        // Send status line
        $this->sendStatusLine();

        // Send headers
        $this->sendHeaders();

        // Send body
        $this->sendBody();
    }

    /**
     * Send HTTP status line
     */
    private function sendStatusLine(): void
    {
        $protocol = 'HTTP/' . $this->protocol;
        $statusCode = $this->statusCode;
        $reasonPhrase = $this->getReasonPhrase();

        header("{$protocol} {$statusCode} {$reasonPhrase}", true, $statusCode);
    }

    /**
     * Send HTTP headers
     */
    private function sendHeaders(): void
    {
        foreach ($this->headers as $name => $values) {
            $first = true;
            foreach ($values as $value) {
                header("{$name}: {$value}", $first);
                $first = false;
            }
        }
    }

    /**
     * Send response body
     */
    private function sendBody(): void
    {
        $body = $this->body;

        if ($body->isSeekable()) {
            $body->rewind();
        }

        while (!$body->eof()) {
            echo $body->read(8192);

            if (connection_status() !== CONNECTION_NORMAL) {
                break;
            }
        }
    }

    /**
     * Check if response is successful (2xx)
     */
    public function isSuccessful(): bool
    {
        return $this->statusCode >= 200 && $this->statusCode < 300;
    }

    /**
     * Check if response is a redirect (3xx)
     */
    public function isRedirection(): bool
    {
        return $this->statusCode >= 300 && $this->statusCode < 400;
    }

    /**
     * Check if response is a client error (4xx)
     */
    public function isClientError(): bool
    {
        return $this->statusCode >= 400 && $this->statusCode < 500;
    }

    /**
     * Check if response is a server error (5xx)
     */
    public function isServerError(): bool
    {
        return $this->statusCode >= 500 && $this->statusCode < 600;
    }

    /**
     * Check if response is OK (200)
     */
    public function isOk(): bool
    {
        return $this->statusCode === 200;
    }

    /**
     * Check if response is not found (404)
     */
    public function isNotFound(): bool
    {
        return $this->statusCode === 404;
    }

    /**
     * Check if response is forbidden (403)
     */
    public function isForbidden(): bool
    {
        return $this->statusCode === 403;
    }

    /**
     * Get response as string (for debugging)
     */
    public function __toString(): string
    {
        $output = sprintf(
            "HTTP/%s %d %s\r\n",
            $this->protocol,
            $this->statusCode,
            $this->getReasonPhrase()
        );

        foreach ($this->headers as $name => $values) {
            foreach ($values as $value) {
                $output .= "{$name}: {$value}\r\n";
            }
        }

        $output .= "\r\n";
        $output .= (string) $this->body;

        return $output;
    }

    /**
     * Clone the response (deep copy body stream)
     */
    public function __clone()
    {
        // Create a new body stream with the same content
        $bodyContent = (string) $this->body;
        $newBody = new Stream(fopen('php://temp', 'r+'));
        $newBody->write($bodyContent);
        $newBody->rewind();
        $this->body = $newBody;
    }
}