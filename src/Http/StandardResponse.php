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
    }

    public static function success(mixed $data = null, int $status = 200, ?string $message = 'Success'): self
    {
        return new self($data, true, $status, $message);
    }

    public static function error(string $message = 'Error', int $status = 400, mixed $data = null): self
    {
        return new self($data, false, $status, $message);
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
            'data' => $this->data,
        ];

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
                'models' => \Plugs\Base\Model\PlugModel::getLoadedModelStats(),
                'execution_time' => microtime(true) - ($_SERVER['REQUEST_TIME_FLOAT'] ?? microtime(true)),
            ];

            // Add query stats if available
            if (method_exists(\Plugs\Base\Model\PlugModel::class, 'getDebugStats')) {
                $debugParams['queries'] = \Plugs\Base\Model\PlugModel::getDebugStats();
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
        return json_encode($this->jsonSerialize(), $options);
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
            // But here we know it's our Response
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

            echo (string) $response->getBody();
        }
    }

    public function __toString(): string
    {
        return $this->toJson();
    }
}
