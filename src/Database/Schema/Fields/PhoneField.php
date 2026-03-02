<?php

declare(strict_types=1);

namespace Plugs\Database\Schema\Fields;

/**
 * PhoneField
 *
 * Represents a phone number model attribute.
 */
class PhoneField extends Field
{
    public function getCastType(): string
    {
        return 'string';
    }

    protected function getTypeRules(): array
    {
        return ['phone'];
    }
}
