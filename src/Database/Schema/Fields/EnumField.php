<?php

declare(strict_types=1);

namespace Plugs\Database\Schema\Fields;

/**
 * EnumField
 *
 * Represents an enum model attribute.
 * Can be used with PHP 8.1+ backed enums or raw string values.
 */
class EnumField extends Field
{
    protected array $allowedValues = [];
    protected ?string $enumClass = null;

    /**
     * Set the allowed values (for raw string enums).
     */
    public function values(array $values): static
    {
        $this->allowedValues = $values;

        return $this;
    }

    /**
     * Set the enum class (for PHP 8.1+ backed enums).
     */
    public function enumClass(string $class): static
    {
        $this->enumClass = $class;

        return $this;
    }

    public function getCastType(): string
    {
        if ($this->enumClass) {
            return $this->enumClass;
        }

        return 'string';
    }

    protected function getTypeRules(): array
    {
        $rules = [];

        if (!empty($this->allowedValues)) {
            $rules[] = 'in:' . implode(',', $this->allowedValues);
        } elseif ($this->enumClass && enum_exists($this->enumClass)) {
            $values = [];
            foreach ($this->enumClass::cases() as $case) {
                $values[] = $case->value ?? $case->name;
            }
            $rules[] = 'in:' . implode(',', $values);
        }

        return $rules;
    }
}
