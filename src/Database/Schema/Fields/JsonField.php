<?php

declare(strict_types=1);

namespace Plugs\Database\Schema\Fields;

/**
 * JsonField
 *
 * Represents a JSON/array model attribute.
 */
class JsonField extends Field
{
    protected ?int $minItems = null;
    protected ?int $maxItems = null;

    /**
     * Set the minimum number of items (when treated as array).
     */
    public function minItems(int $count): static
    {
        $this->minItems = $count;

        return $this;
    }

    /**
     * Set the maximum number of items (when treated as array).
     */
    public function maxItems(int $count): static
    {
        $this->maxItems = $count;

        return $this;
    }

    public function getCastType(): string
    {
        return 'array';
    }

    protected function getTypeRules(): array
    {
        $rules = ['array'];

        if ($this->minItems !== null) {
            $rules[] = "min:{$this->minItems}";
        }

        if ($this->maxItems !== null) {
            $rules[] = "max:{$this->maxItems}";
        }

        return $rules;
    }
}
