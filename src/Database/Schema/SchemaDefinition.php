<?php

declare(strict_types=1);

namespace Plugs\Database\Schema;

use Plugs\Database\Schema\Fields\Field;

/**
 * SchemaDefinition
 *
 * Immutable container that holds the resolved schema for a model class.
 * Derives fillable, guarded, casts, validation rules, hidden fields,
 * and defaults from an array of Field instances.
 */
class SchemaDefinition
{
    /**
     * @var array<string, Field>
     */
    protected array $fields;

    /**
     * The table name for unique constraint resolution.
     */
    protected ?string $tableName;

    /**
     * @param array<string, Field> $fields Field instances keyed by attribute name
     * @param string|null $tableName The table name for unique constraint resolution
     */
    public function __construct(array $fields, ?string $tableName = null)
    {
        $this->fields = $fields;
        $this->tableName = $tableName;

        // Set the name on each field
        foreach ($this->fields as $name => $field) {
            $field->setName($name);
        }
    }

    /**
     * Get all field instances.
     *
     * @return array<string, Field>
     */
    public function getFields(): array
    {
        return $this->fields;
    }

    /**
     * Get a specific field by name.
     */
    public function getField(string $name): ?Field
    {
        return $this->fields[$name] ?? null;
    }

    /**
     * Check if a field exists in the schema.
     */
    public function hasField(string $name): bool
    {
        return isset($this->fields[$name]);
    }

    /**
     * Get all fillable attribute names.
     *
     * @return array<string>
     */
    public function getFillable(): array
    {
        $fillable = [];

        foreach ($this->fields as $name => $field) {
            if ($field->isFillable()) {
                $fillable[] = $name;
            }
        }

        return $fillable;
    }

    /**
     * Get all guarded attribute names.
     *
     * @return array<string>
     */
    public function getGuarded(): array
    {
        $guarded = [];

        foreach ($this->fields as $name => $field) {
            if ($field->isFieldGuarded()) {
                $guarded[] = $name;
            }
        }

        return $guarded;
    }

    /**
     * Get the casts array (attribute name => cast type).
     *
     * @return array<string, string>
     */
    public function getCasts(): array
    {
        $casts = [];

        foreach ($this->fields as $name => $field) {
            $castType = $field->getCastType();
            if ($castType !== '') {
                $casts[$name] = $castType;
            }
        }

        return $casts;
    }

    /**
     * Get the validation rules array (attribute name => rule string).
     *
     * @return array<string, string>
     */
    public function getValidationRules(): array
    {
        $rules = [];

        foreach ($this->fields as $name => $field) {
            $ruleString = $field->getValidationRules($this->tableName);
            if ($ruleString !== '') {
                $rules[$name] = $ruleString;
            }
        }

        return $rules;
    }

    /**
     * Get all hidden attribute names.
     *
     * @return array<string>
     */
    public function getHidden(): array
    {
        $hidden = [];

        foreach ($this->fields as $name => $field) {
            if ($field->isFieldHidden()) {
                $hidden[] = $name;
            }
        }

        return $hidden;
    }

    /**
     * Get the default values array (attribute name => default value).
     *
     * @return array<string, mixed>
     */
    public function getDefaults(): array
    {
        $defaults = [];

        foreach ($this->fields as $name => $field) {
            if ($field->hasDefaultValue()) {
                $defaults[$name] = $field->getDefaultValue();
            }
        }

        return $defaults;
    }

    /**
     * Get all attribute names defined in the schema.
     *
     * @return array<string>
     */
    public function getAttributeNames(): array
    {
        return array_keys($this->fields);
    }

    /**
     * Get the table name.
     */
    public function getTableName(): ?string
    {
        return $this->tableName;
    }
}
