<?php

declare(strict_types=1);

namespace Plugs\Security\Authorization\Attributes;

use Attribute;

#[Attribute(Attribute::TARGET_CLASS | Attribute::TARGET_METHOD | Attribute::IS_REPEATABLE)]
class Authorize
{
    public function __construct(
        public ?string $ability = null,
        public ?string $role = null,
        public ?string $permission = null
    ) {
    }
}
