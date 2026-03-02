<?php

declare(strict_types=1);

namespace Plugs\Database\Schema\Fields;

/**
 * TextField
 *
 * Represents a long text model attribute (TEXT/LONGTEXT columns).
 */
class TextField extends Field
{
    protected ?int $minLength = null;
    protected ?int $maxLength = null;

    /**
     * Set the minimum text length.
     */
    public function min(int $length): static
    {
        $this->minLength = $length;

        return $this;
    }

    /**
     * Set the maximum text length.
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
