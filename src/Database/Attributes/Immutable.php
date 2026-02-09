<?php

declare(strict_types=1);

namespace Plugs\Database\Attributes;

use Attribute;

#[Attribute(Attribute::TARGET_CLASS)]
class Immutable
{
}
