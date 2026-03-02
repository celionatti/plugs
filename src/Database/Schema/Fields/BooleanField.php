<?php

declare(strict_types=1);

namespace Plugs\Database\Schema\Fields;

/**
 * BooleanField
 *
 * Represents a boolean model attribute.
 */
class BooleanField extends Field
{
    public function getCastType(): string
    {
        return 'boolean';
    }

    protected function getTypeRules(): array
    {
        return ['boolean'];
    }
}
