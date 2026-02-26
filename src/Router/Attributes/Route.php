<?php

declare(strict_types=1);

namespace Plugs\Router\Attributes;

use Attribute;

/**
 * Route Attribute
 */
#[Attribute(Attribute::TARGET_METHOD | Attribute::TARGET_CLASS | Attribute::IS_REPEATABLE)]
class Route
{
    public function __construct(
        public string $path,
        public string|array $methods = 'GET',
        public ?string $name = null,
        public array $middleware = [],
        public array $where = [],
        public ?string $domain = null
    ) {
    }
}
