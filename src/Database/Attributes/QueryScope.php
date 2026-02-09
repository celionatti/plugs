<?php

declare(strict_types=1);

namespace Plugs\Database\Attributes;

use Attribute;

/**
 * Attribute to mark a model method as a discoverable query scope.
 */
#[Attribute(Attribute::TARGET_METHOD)]
class QueryScope
{
    /**
     * Create a new QueryScope attribute instance.
     *
     * @param string|null $description Optional description of what the scope does.
     */
    public function __construct(public ?string $description = null)
    {
    }
}
