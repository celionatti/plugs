<?php

declare(strict_types=1);

namespace Plugs\Database\Schema\Fields;

/**
 * EmailField
 *
 * Represents an email address model attribute.
 * Automatically applies email validation.
 */
class EmailField extends Field
{
    protected ?int $maxLength = null;

    /**
     * Set the maximum string length.
     */
    public function max(int $length): static
    {
        $this->maxLength = $length;

        return $this;
    }

    public function getCastType(): string
    {
        return 'string';
    }

    protected function getTypeRules(): array
    {
        $rules = ['email'];

        if ($this->maxLength !== null) {
            $rules[] = "max:{$this->maxLength}";
        }

        return $rules;
    }
}
