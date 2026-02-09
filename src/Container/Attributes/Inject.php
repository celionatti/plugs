<?php

declare(strict_types=1);

namespace Plugs\Container\Attributes;

use Attribute;

#[Attribute(Attribute::TARGET_PARAMETER)]
class Inject
{
    public function __construct(
        public string $service
    ) {
    }
}
