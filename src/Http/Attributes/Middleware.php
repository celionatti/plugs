<?php

declare(strict_types=1);

namespace Plugs\Http\Attributes;

use Attribute;

/**
 * Middleware Attribute
 */
#[Attribute(Attribute::TARGET_CLASS | Attribute::TARGET_METHOD | Attribute::IS_REPEATABLE)]
class Middleware
{
    /**
     * @param string|array $middleware The middleware or list of middleware
     */
    public function __construct(
        public string|array $middleware
    ) {
    }
}
