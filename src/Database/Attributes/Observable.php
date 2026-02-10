<?php

declare(strict_types=1);

namespace Plugs\Database\Attributes;

use Attribute;

#[Attribute(Attribute::TARGET_CLASS)]
class Observable
{
    public function __construct(
        public bool $trackMetrics = true,
        public float $slowQueryThreshold = 0.1, // 100ms
        public bool $alertOnSlow = true
    ) {
    }
}
