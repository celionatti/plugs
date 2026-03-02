<?php

declare(strict_types=1);

namespace Plugs\Database\Schema\Fields;

/**
 * StringField
 *
 * Represents a string/varchar model attribute.
 */
class StringField extends Field
{
    protected ?int $minLength = null;
    protected ?int $maxLength = null;

    /**
     * Set the minimum string length.
     */
    public function min(int $length): static
    {
        $this->minLength = $length;

        return $this;
    }

    /**
     * Set the maximum string length.
     */
    public function max(int $length): static
    {
        $this->maxLength = $length;

        return $this;
    }

    /**
     * Set both min and max length.
     */
    public function between(int $min, int $max): static
    {
        $this->minLength = $min;
        $this->maxLength = $max;

        return $this;
    }

    public function getCastType(): string
    {
        return 'string';
    }

    protected function getTypeRules(): array
    {
        $rules = ['string'];

        if ($this->minLength !== null) {
            $rules[] = "min:{$this->minLength}";
        }

        if ($this->maxLength !== null) {
            $rules[] = "max:{$this->maxLength}";
        }

        return $rules;
    }
}
