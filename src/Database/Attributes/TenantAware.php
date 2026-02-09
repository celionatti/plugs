<?php

declare(strict_types=1);

namespace Plugs\Database\Attributes;

use Attribute;

#[Attribute(Attribute::TARGET_CLASS)]
class TenantAware
{
    public function __construct(
        public string $column = 'tenant_id'
    ) {
    }
}
