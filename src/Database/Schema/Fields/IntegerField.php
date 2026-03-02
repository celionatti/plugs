<?php

declare(strict_types=1);

namespace Plugs\Database\Schema\Fields;

/**
 * IntegerField
 *
 * Represents an integer model attribute.
 */
class IntegerField extends Field
{
    protected ?int $minValue = null;
    protected ?int $maxValue = null;

    /**
     * Set the minimum value.
     */
    public function min(int $value): static
    {
        $this->minValue = $value;

        return $this;
    }

    /**
     * Set the maximum value.
     */
    public function max(int $value): static
    {
        $this->maxValue = $value;

        return $this;
    }

    /**
     * Set both min and max value.
     */
    public function between(int $min, int $max): static
    {
        $this->minValue = $min;
        $this->maxValue = $max;

        return $this;
    }

    /**
     * Shorthand: mark as unsigned (min 0).
     */
    public function unsigned(): static
    {
        $this->minValue = 0;

        return $this;
    }

    public function getCastType(): string
    {
        return 'integer';
    }

    protected function getTypeRules(): array
    {
        $rules = ['integer'];

        if ($this->minValue !== null) {
            $rules[] = "min:{$this->minValue}";
        }

        if ($this->maxValue !== null) {
            $rules[] = "max:{$this->maxValue}";
        }

        return $rules;
    }
}
