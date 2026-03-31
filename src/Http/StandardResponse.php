<?php

declare(strict_types=1);

namespace Plugs\Http;

use JsonSerializable;
use Plugs\Plugs;

/**
 * StandardResponse
 *
 * Provides a standardized structure for API responses.
 * Especially useful in production for a consistent developer experience.
 */
class StandardResponse implements JsonSerializable
{
    protected bool $success;
    protected mixed $data;
    protected array $meta = [];
    protected array $links = [];
    protected array $headers = ['Content-Type' => 'application/json'];
    protected int $status;
    protected ?string $message;
    protected string $timestamp;
    protected ?string $wrapKey = null;

    public function __construct(
        mixed $data = null,
        bool $success = true,
        int $status = 200,
        ?string $message = null
    ) {
        $this->data = $data;
        $this->success = $success;
        $this->status = $status;
        $this->message = $message;
        $this->timestamp = date('c'); // ISO 8601

        // Auto-detect pagination if data is a Paginator
        if ($data instanceof \Plugs\Paginator\Paginator) {
            $this->withPagination($data);
        }
    }

    public static function success(mixed $data = null, int $status = 200, ?string $message = 'Success'): self
    {
        return new self($data, true, $status, $message);
    }

    public static function error(string $message = 'Error', int $status = 400, mixed $data = null): self
    {
        return new self($data, false, $status, $message);
    }

    /**
     * Set the HTTP status code.
     */
    public function setStatus(int $status): self
    {
        $this->status = $status;

        return $this;
    }

    /**
     * Set the response message.
     */
    public function withMessage(string $message): self
    {
        $this->message = $message;

        return $this;
    }

    /**
     * Wrap the data in a specific key.
     */
    public function wrap(string $key): self
    {
        $this->wrapKey = $key;

        return $this;
    }

    /**
     * Disable data wrapping.
     */
    public function withoutWrapping(): self
    {
        $this->wrapKey = null;

        return $this;
    }

    /**
     * Add pagination metadata from a Paginator instance.
     */
    public function withPagination(\Plugs\Paginator\Paginator $paginator): self
    {
        $this->data = $paginator->items();

        $this->withMeta([
            'pagination' => [
                'total' => $paginator->total(),
                'count' => count($paginator->items()),
                'per_page' => $paginator->perPage(),
                'current_page' => $paginator->currentPage(),
                'total_pages' => $paginator->lastPage(),
            ],
        ]);

        $this->withLinks([
            'first' => $paginator->url(1),
            'last' => $paginator->url($paginator->lastPage()),
            'prev' => $paginator->previousPage() ? $paginator->url($paginator->previousPage()) : null,
            'next' => $paginator->nextPage() ? $paginator->url($paginator->nextPage()) : null,
        ]);

        return $this;
    }

    public function withMeta(array $meta): self
    {
        $this->meta = array_merge($this->meta, $meta);

        return $this;
    }

    public function withLinks(array $links): self
    {
        $this->links = array_merge($this->links, $links);

        return $this;
    }

    public function withHeader(string $name, string $value): self
    {
        $this->headers[$name] = $value;

        return $this;
    }

    public function withHeaders(array $headers): self
    {
        $this->headers = array_merge($this->headers, $headers);

        return $this;
    }

    public function jsonSerialize(): mixed
    {
        $response = [
            'success' => $this->success,
            'status' => $this->status,
            'message' => $this->message,
            'timestamp' => $this->timestamp,
        ];

        // Handle data wrapping
        $data = $this->data;
        if ($this->wrapKey !== null) {
            $response[$this->wrapKey] = $data;
        } else {
            $response['data'] = $data;
        }

        if (!empty($this->meta)) {
            $response['meta'] = $this->meta;
        }

        if (!empty($this->links)) {
            $response['links'] = $this->links;
        }

        // In non-production, we might want to add more debug info
        if (!Plugs::isProduction()) {
            $debugParams = [
                'memory' => memory_get_usage(true),
                'peak_memory' => memory_get_peak_usage(true),
                'php_version' => PHP_VERSION,
                'execution_time' => microtime(true) - ($_SERVER['REQUEST_TIME_FLOAT'] ?? microtime(true)),
            ];

            // Add model stats if available
            if (class_exists(\Plugs\Base\Model\PlugModel::class)) {
                $debugParams['models'] = \Plugs\Base\Model\PlugModel::getLoadedModelStats();

                /** @phpstan-ignore-next-line */
                if (method_exists(\Plugs\Base\Model\PlugModel::class, 'getDebugStats')) {
                    $debugParams['queries'] = \Plugs\Base\Model\PlugModel::getDebugStats();
                }
            }

            $response['debug'] = $debugParams;
        }

        return $response;
    }

    public function toArray(): array
    {
        return $this->jsonSerialize();
    }

    public function toJson(int $options = 0): string
    {
        return json_encode($this->jsonSerialize(), $options | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT);
    }

    /**
     * Convert to PSR-7 Response
     */
    public function toPsr(): \Psr\Http\Message\ResponseInterface
    {
        $body = new \Plugs\Http\Message\Stream(fopen('php://temp', 'r+'));
        $body->write($this->toJson());
        $body->rewind();

        return new \Plugs\Http\Message\Response(
            $this->status,
            $body,
            $this->headers
        );
    }

    /**
     * Send response to client
     */
    public function send(): void
    {
        $response = $this->toPsr();

        if ($response instanceof \Plugs\Http\Message\Response) {
            $response->send();
        } else {
            // Fallback for generic PSR-7 emitters if needed
            if (!headers_sent()) {
                header(sprintf(
                    'HTTP/%s %d %s',
                    $response->getProtocolVersion(),
                    $response->getStatusCode(),
                    $response->getReasonPhrase()
                ), true, $response->getStatusCode());

                foreach ($response->getHeaders() as $name => $values) {
                    foreach ($values as $value) {
                        header("{$name}: {$value}", false);
                    }
                }
            }

            echo (string) $response->getBody();
        }
    }

    public function __toString(): string
    {
        return $this->toJson();
    }
}

