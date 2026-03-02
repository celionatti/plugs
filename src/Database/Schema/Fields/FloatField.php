<?php

declare(strict_types=1);

namespace Plugs\Database\Schema\Fields;

/**
 * FloatField
 *
 * Represents a floating-point model attribute.
 */
class FloatField extends Field
{
    protected ?float $minValue = null;
    protected ?float $maxValue = null;

    /**
     * Set the minimum value.
     */
    public function min(float $value): static
    {
        $this->minValue = $value;

        return $this;
    }

    /**
     * Set the maximum value.
     */
    public function max(float $value): static
    {
        $this->maxValue = $value;

        return $this;
    }

    /**
     * Set both min and max value.
     */
    public function between(float $min, float $max): static
    {
        $this->minValue = $min;
        $this->maxValue = $max;

        return $this;
    }

    public function getCastType(): string
    {
        return 'float';
    }

    protected function getTypeRules(): array
    {
        $rules = ['numeric'];

        if ($this->minValue !== null) {
            $rules[] = "min:{$this->minValue}";
        }

        if ($this->maxValue !== null) {
            $rules[] = "max:{$this->maxValue}";
        }

        return $rules;
    }
}
