<?php

declare(strict_types=1);

namespace Plugs\Middlewares;

use Plugs\Security\Sanitizer;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;

class SanitizationMiddleware implements MiddlewareInterface
{
    private array $except = [];

    public function __construct(array $except = [])
    {
        $this->except = $except;
    }

    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        if ($this->shouldSkip($request)) {
            return $handler->handle($request);
        }

        $parsedBody = $request->getParsedBody();
        $queryParams = $request->getQueryParams();

        $cleanedBody = $this->cleanArray((array) $parsedBody);
        $request = $request->withParsedBody($cleanedBody);

        $cleanedParams = $this->cleanArray($queryParams);
        $request = $request->withQueryParams($cleanedParams);

        return $handler->handle($request);
    }

    private function cleanArray(array $data): array
    {
        return Sanitizer::array($data);
    }

    private function shouldSkip(ServerRequestInterface $request): bool
    {
        foreach ($this->except as $pattern) {
            if ($this->requestIs($request, $pattern)) {
                return true;
            }
        }

        return false;
    }

    private function requestIs(ServerRequestInterface $request, string $pattern): bool
    {
        $path = $request->getUri()->getPath();
        $path = rawurldecode($path) ?: '/';

        if ($pattern === '/') {
            return $path === '/';
        }

        $pattern = trim($pattern, '/');
        $path = trim($path, '/');

        if ($pattern === $path) {
            return true;
        }

        $pattern = preg_quote($pattern, '#');
        $pattern = str_replace('\*', '.*', $pattern);

        return (bool) preg_match('#^' . $pattern . '\z#u', $path);
    }
}
