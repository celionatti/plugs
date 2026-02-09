<?php

declare(strict_types=1);

namespace Plugs\Router;

use Psr\Http\Message\ServerRequestInterface;

class ApiVersion
{
    public const HEADER = 'X-API-Version';
    public const QUERY_PARAM = 'api_version';

    public function __construct(
        public readonly string $version
    ) {
    }

    public static function fromRequest(ServerRequestInterface $request): ?self
    {
        // 1. Check Header
        if ($request->hasHeader(self::HEADER)) {
            return new self($request->getHeaderLine(self::HEADER));
        }

        // 2. Check Query Param
        $queryParams = $request->getQueryParams();
        if (isset($queryParams[self::QUERY_PARAM])) {
            return new self($queryParams[self::QUERY_PARAM]);
        }

        // 3. Check URI Prefix (e.g., /api/v1/users) -> handled by Router groups actually
        // But we can extract it if needed. For now, we rely on Router group attributes.

        return null;
    }

    public function __toString(): string
    {
        return $this->version;
    }

    public function equals(string|self $other): bool
    {
        $otherVersion = $other instanceof self ? $other->version : $other;
        return $this->version === $otherVersion;
    }
}
