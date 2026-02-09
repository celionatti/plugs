<?php

declare(strict_types=1);

namespace Plugs\Database\Attributes;

use Attribute;

/**
 * Attribute to define relationship constraints and guarantees.
 */
#[Attribute(Attribute::TARGET_METHOD)]
class Relation
{
    /**
     * Create a new Relation attribute instance.
     *
     * @param bool $required Whether the relationship is mandatory.
     * @param int|null $min Minimum number of related records.
     * @param int|null $max Maximum number of related records.
     */
    public function __construct(
        public bool $required = false,
        public ?int $min = null,
        public ?int $max = null
    ) {
    }
}
