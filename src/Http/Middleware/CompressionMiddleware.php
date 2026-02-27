<?php

declare(strict_types=1);

namespace Plugs\Http\Middleware;

use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;
use Plugs\Http\Message\Stream;
use Plugs\Http\Middleware\MiddlewareLayer;
use Plugs\Http\Middleware\Middleware;

#[Middleware(layer: MiddlewareLayer::PERFORMANCE, priority: 20)]
class CompressionMiddleware implements MiddlewareInterface
{
    private array $compressibleTypes = [
        'text/html',
        'text/css',
        'application/javascript',
        'application/json',
        'text/xml',
        'application/xml',
        'text/plain'
    ];

    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        $response = $handler->handle($request);

        if ($this->shouldCompress($request, $response)) {
            $content = (string) $response->getBody();
            // In case of empty body, don't compress
            if ($content === '') {
                return $response;
            }

            $compressedContent = gzencode($content, 6);
            if ($compressedContent !== false) {
                $stream = new Stream(fopen('php://temp', 'r+'));
                $stream->write($compressedContent);
                $stream->rewind();

                return $response
                    ->withHeader('Content-Encoding', 'gzip')
                    ->withAddedHeader('Vary', 'Accept-Encoding')
                    ->withBody($stream);
            }
        }

        return $response;
    }

    private function shouldCompress(ServerRequestInterface $request, ResponseInterface $response): bool
    {
        if (connection_aborted() || (headers_sent() && PHP_SAPI !== 'cli')) {
            return false;
        }

        if ($response->hasHeader('Content-Encoding')) {
            return false;
        }

        $contentType = $response->getHeaderLine('Content-Type') ?: 'text/html';
        $isCompressible = false;

        foreach ($this->compressibleTypes as $type) {
            if (stripos($contentType, $type) !== false) {
                $isCompressible = true;
                break;
            }
        }

        if (!$isCompressible) {
            return false;
        }

        $acceptEncoding = $request->getHeaderLine('Accept-Encoding');
        return stripos($acceptEncoding, 'gzip') !== false;
    }
}
