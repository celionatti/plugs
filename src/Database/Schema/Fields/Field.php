<?php

declare(strict_types=1);

namespace Plugs\Database\Schema\Fields;

/**
 * Field
 *
 * Abstract base class for all schema field types.
 * Provides a fluent API for declaring validation rules, casting,
 * fillable/guarded status, and other constraints.
 */
abstract class Field
{
    protected string $name = '';
    protected bool $isRequired = false;
    protected bool $isNullable = false;
    protected bool $isUnique = false;
    protected bool $isGuarded = false;
    protected bool $isHidden = false;
    protected mixed $defaultValue = null;
    protected bool $hasDefault = false;
    protected array $extraRules = [];
    protected ?string $uniqueTable = null;
    protected ?string $uniqueColumn = null;

    /**
     * Create a new field instance.
     */
    public static function make(): static
    {
        return new static();
    }

    // ==================== FLUENT SETTERS ====================

    /**
     * Mark this field as required.
     */
    public function required(): static
    {
        $this->isRequired = true;

        return $this;
    }

    /**
     * Mark this field as nullable.
     */
    public function nullable(): static
    {
        $this->isNullable = true;

        return $this;
    }

    /**
     * Mark this field as unique.
     *
     * @param string|null $table  Table name for the unique check (auto-detected if null)
     * @param string|null $column Column name for the unique check (uses field name if null)
     */
    public function unique(?string $table = null, ?string $column = null): static
    {
        $this->isUnique = true;
        $this->uniqueTable = $table;
        $this->uniqueColumn = $column;

        return $this;
    }

    /**
     * Mark this field as guarded (not mass-assignable).
     */
    public function guarded(): static
    {
        $this->isGuarded = true;

        return $this;
    }

    /**
     * Mark this field as hidden from serialization.
     */
    public function hidden(): static
    {
        $this->isHidden = true;

        return $this;
    }

    /**
     * Set a default value for this field.
     */
    public function default(mixed $value): static
    {
        $this->defaultValue = $value;
        $this->hasDefault = true;

        return $this;
    }

    /**
     * Append an arbitrary validation rule string.
     *
     * @param string $rule  A pipe-delimited or single rule string (e.g. 'min:5' or 'regex:/^[A-Z]/')
     */
    public function rule(string $rule): static
    {
        $this->extraRules[] = $rule;

        return $this;
    }

    /**
     * Set the field name (called internally by SchemaDefinition).
     */
    public function setName(string $name): static
    {
        $this->name = $name;

        return $this;
    }

    // ==================== DERIVATION METHODS ====================

    /**
     * Get the cast type for this field.
     * Must be implemented by each concrete field type.
     */
    abstract public function getCastType(): string;

    /**
     * Get the implicit validation rules for this field type.
     * Override in concrete classes for type-specific rules.
     *
     * @return array<string>
     */
    protected function getTypeRules(): array
    {
        return [];
    }

    /**
     * Build the complete validation rule string for this field.
     */
    public function getValidationRules(?string $table = null): string
    {
        $rules = [];

        if ($this->isRequired) {
            $rules[] = 'required';
        } elseif ($this->isNullable) {
            $rules[] = 'nullable';
        }

        // Add type-specific rules
        $rules = array_merge($rules, $this->getTypeRules());

        // Add unique constraint
        if ($this->isUnique) {
            $uniqueTable = $this->uniqueTable ?? $table;
            $uniqueColumn = $this->uniqueColumn ?? $this->name;

            if ($uniqueTable) {
                $rules[] = "unique:{$uniqueTable},{$uniqueColumn}";
            } else {
                $rules[] = 'unique';
            }
        }

        // Add extra user-defined rules
        foreach ($this->extraRules as $rule) {
            $rules[] = $rule;
        }

        return implode('|', $rules);
    }

    /**
     * Whether this field should be mass-assignable.
     */
    public function isFillable(): bool
    {
        return !$this->isGuarded;
    }

    /**
     * Whether this field should be hidden from serialization.
     */
    public function isFieldHidden(): bool
    {
        return $this->isHidden;
    }

    /**
     * Whether this field has a default value.
     */
    public function hasDefaultValue(): bool
    {
        return $this->hasDefault;
    }

    /**
     * Get the default value.
     */
    public function getDefaultValue(): mixed
    {
        return $this->defaultValue;
    }

    /**
     * Get the field name.
     */
    public function getName(): string
    {
        return $this->name;
    }

    /**
     * Whether this field is required.
     */
    public function isFieldRequired(): bool
    {
        return $this->isRequired;
    }

    /**
     * Whether this field is nullable.
     */
    public function isFieldNullable(): bool
    {
        return $this->isNullable;
    }

    /**
     * Whether this field should be unique.
     */
    public function isFieldUnique(): bool
    {
        return $this->isUnique;
    }

    /**
     * Whether this field is guarded.
     */
    public function isFieldGuarded(): bool
    {
        return $this->isGuarded;
    }
}
