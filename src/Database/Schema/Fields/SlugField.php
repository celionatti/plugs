<?php

declare(strict_types=1);

namespace Plugs\Database\Schema\Fields;

/**
 * SlugField
 *
 * Represents a URL slug model attribute.
 */
class SlugField extends Field
{
    protected ?int $maxLength = null;

    /**
     * Set the maximum slug length.
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
        $rules = ['slug'];

        if ($this->maxLength !== null) {
            $rules[] = "max:{$this->maxLength}";
        }

        return $rules;
    }
}
