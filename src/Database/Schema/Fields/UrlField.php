<?php

declare(strict_types=1);

namespace Plugs\Database\Schema\Fields;

/**
 * UrlField
 *
 * Represents a URL model attribute.
 */
class UrlField extends Field
{
    protected ?int $maxLength = null;

    /**
     * Set the maximum URL length.
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
        $rules = ['url'];

        if ($this->maxLength !== null) {
            $rules[] = "max:{$this->maxLength}";
        }

        return $rules;
    }
}
