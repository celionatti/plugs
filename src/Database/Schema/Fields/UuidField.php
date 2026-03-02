<?php

declare(strict_types=1);

namespace Plugs\Database\Schema\Fields;

/**
 * UuidField
 *
 * Represents a UUID model attribute.
 */
class UuidField extends Field
{
    public function getCastType(): string
    {
        return 'string';
    }

    protected function getTypeRules(): array
    {
        return ['uuid'];
    }
}
