<?php

declare(strict_types=1);

namespace Plugs;

/*
|--------------------------------------------------------------------------
| Plugs Class
|--------------------------------------------------------------------------
|
| This is the main class for the Plugs framework. It serves as the entry
| point for the application and can be used to initialize and run the.
*/

use Plugs\Http\Message\Stream;
use Psr\Http\Message\ServerRequestInterface;

use Plugs\Http\Message\Response;
use Plugs\Http\MiddlewareDispatcher;
use Plugs\Http\Message\ServerRequest;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Server\MiddlewareInterface;

class Plugs
{
    private $dispatcher;
    private $fallbackHandler;

    public function __construct()
    {
        $this->loadFunctions();
        $this->dispatcher = new MiddlewareDispatcher();
        $this->fallbackHandler = function (ServerRequestInterface $request) {
            $body = new Stream(fopen('php://temp', 'w+'));
            $body->write('Not Found');
            $body->rewind();
            return new Response(404, $body, ['Content-Type' => 'text/plain']);
        };
    }

    public function pipe(MiddlewareInterface $middleware): self
    {
        $this->dispatcher->add($middleware);
        return $this;
    }

    public function setFallback(callable $handler): self
    {
        $this->fallbackHandler = $handler;
        return $this;
    }

    public function run(?ServerRequestInterface $request = null): void
    {
        $request = $request ?? $this->createServerRequest();

        // Set the fallback handler before handling the request
        $this->dispatcher->setFallbackHandler($this->fallbackHandler);

        $response = $this->dispatcher->handle($request);
        $this->emitResponse($response);
    }

    private function createServerRequest(): ServerRequestInterface
    {
        $method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
        $uri = $_SERVER['REQUEST_URI'] ?? '/';

        // Parse query string
        $queryParams = [];
        if (isset($_SERVER['QUERY_STRING'])) {
            parse_str($_SERVER['QUERY_STRING'], $queryParams);
        }

        $headers = [];
        foreach ($_SERVER as $key => $value) {
            if (strpos($key, 'HTTP_') === 0) {
                $header = str_replace('_', '-', strtolower(substr($key, 5)));
                $headers[$header] = $value;
            }
        }

        $body = new Stream(fopen('php://input', 'r'));

        $request = new ServerRequest(
            $method,
            $uri,
            $headers,
            $body,
            $_SERVER['SERVER_PROTOCOL'] ?? '1.1',
            $_SERVER
        );

        return $request
            ->withQueryParams($queryParams)
            ->withParsedBody($_POST)
            ->withCookieParams($_COOKIE)
            ->withUploadedFiles($this->normalizeFiles($_FILES));
    }

    private function normalizeFiles(array $files): array
    {
        $normalized = [];

        foreach ($files as $key => $value) {
            if (is_array($value) && isset($value['tmp_name'])) {
                // Single file or multiple files with same name
                if (is_array($value['tmp_name'])) {
                    // Multiple files
                    $normalized[$key] = [];
                    foreach (array_keys($value['tmp_name']) as $index) {
                        $normalized[$key][$index] = [
                            'name' => $value['name'][$index],
                            'type' => $value['type'][$index],
                            'tmp_name' => $value['tmp_name'][$index],
                            'error' => $value['error'][$index],
                            'size' => $value['size'][$index],
                        ];
                    }
                } else {
                    // Single file
                    $normalized[$key] = $value;
                }
            } elseif (is_array($value)) {
                $normalized[$key] = $this->normalizeFiles($value);
            }
        }

        return $normalized;
    }

    private function emitResponse(ResponseInterface $response): void
    {
        // Send status line
        $statusCode = $response->getStatusCode();
        $reasonPhrase = $response->getReasonPhrase();
        header(sprintf(
            'HTTP/%s %d%s',
            $response->getProtocolVersion(),
            $statusCode,
            ($reasonPhrase ? ' ' . $reasonPhrase : '')
        ), true, $statusCode);

        // Send headers (replace any existing headers with same name)
        foreach ($response->getHeaders() as $name => $values) {
            $first = true;
            foreach ($values as $value) {
                header(sprintf('%s: %s', $name, $value), $first);
                $first = false; // Subsequent values should be additive
            }
        }

        // Send body
        echo $response->getBody();
    }

    /**
     * Load helper functions.
     */
    private function loadFunctions(): void
    {
        $functionsDir = __DIR__ . '/functions/';

        if (!is_dir($functionsDir)) {
            return;
        }

        // Use a more efficient way to load functions
        $files = glob($functionsDir . '*.php', GLOB_NOSORT);

        foreach ($files as $file) {
            // Check if it's a file and not an index or something else
            if (is_file($file)) {
                require_once $file;
            }
        }
    }

    /**
     * Check if the application is in production mode.
     */
    public static function isProduction(): bool
    {
        return strtolower(getenv('APP_ENV') ?: 'production') === 'production';
    }
}