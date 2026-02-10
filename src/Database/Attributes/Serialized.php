<?php

declare(strict_types=1);

namespace Plugs\Database\Attributes;

use Attribute;

#[Attribute(Attribute::TARGET_CLASS | Attribute::IS_REPEATABLE)]
class Serialized
{
    public function __construct(
        public string $profile,
        public array $visible = [],
        public array $hidden = [],
        public array $appends = []
    ) {
    }
}
