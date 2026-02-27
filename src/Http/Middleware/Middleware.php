<?php

declare(strict_types=1);

namespace Plugs\Http\Middleware;

use Attribute;

/**
 * PHP 8 Attribute for declaring middleware metadata.
 */
#[Attribute(Attribute::TARGET_CLASS)]
class Middleware
{
    public function __construct(
        public string|MiddlewareLayer $layer = 'business',
        public int $priority = 500
    ) {
    }
}
