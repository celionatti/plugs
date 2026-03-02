<?php

declare(strict_types=1);

namespace Plugs\Database\Traits;

use Plugs\Database\Schema\SchemaDefinition;

/**
 * HasSchema
 *
 * Enables intelligent typed model schemas. When a model declares:
 *
 *     protected array $schema = [
 *         'email' => EmailField::make()->required()->unique(),
 *         'age'   => IntegerField::make()->min(18),
 *     ];
 *
 * This trait automatically derives fillable, casts, validation rules,
 * hidden fields, and default values from the schema definition.
 *
 * @phpstan-ignore trait.unused
 */
trait HasSchema
{
    /**
     * Cached schema definitions per model class.
     *
     * @var array<string, SchemaDefinition>
     */
    protected static array $schemaDefinitions = [];

    /**
     * Boot the HasSchema trait.
     * Schema resolution is deferred to first access to avoid recursion.
     */
    public static function bootHasSchema(): void
    {
        // No-op: schema resolution is deferred to getSchemaDefinition()
        // to avoid infinite recursion (new static() triggers constructor → boot → bootHasSchema).
    }

    /**
     * Check if this model has a schema property defined.
     */
    protected function hasSchemaProperty(): bool
    {
        return property_exists($this, 'schema') && !empty($this->schema);
    }

    /**
     * Get the raw schema array from the model.
     *
     * @return array<string, \Plugs\Database\Schema\Fields\Field>
     */
    protected function getRawSchema(): array
    {
        if (!$this->hasSchemaProperty()) {
            return [];
        }

        return $this->schema;
    }

    /**
     * Get the resolved SchemaDefinition for this model.
     * Lazily resolves and caches on first access.
     */
    public function getSchemaDefinition(): ?SchemaDefinition
    {
        if (!$this->hasSchemaProperty()) {
            return null;
        }

        $class = static::class;

        if (!isset(static::$schemaDefinitions[$class])) {
            $schema = $this->getRawSchema();
            $tableName = $this->getTable();
            static::$schemaDefinitions[$class] = new SchemaDefinition($schema, $tableName);
        }

        return static::$schemaDefinitions[$class];
    }

    /**
     * Get fillable attributes derived from the schema.
     *
     * @return array<string>|null  Returns null if no schema is defined
     */
    public function getSchemaFillable(): ?array
    {
        $schema = $this->getSchemaDefinition();

        return $schema?->getFillable();
    }

    /**
     * Get guarded attributes derived from the schema.
     *
     * @return array<string>|null  Returns null if no schema is defined
     */
    public function getSchemaGuarded(): ?array
    {
        $schema = $this->getSchemaDefinition();

        return $schema?->getGuarded();
    }

    /**
     * Get casts derived from the schema.
     *
     * @return array<string, string>|null  Returns null if no schema is defined
     */
    public function getSchemaCasts(): ?array
    {
        $schema = $this->getSchemaDefinition();

        return $schema?->getCasts();
    }

    /**
     * Get validation rules derived from the schema.
     *
     * @return array<string, string>|null  Returns null if no schema is defined
     */
    public function getSchemaRules(): ?array
    {
        $schema = $this->getSchemaDefinition();

        return $schema?->getValidationRules();
    }

    /**
     * Get hidden attributes derived from the schema.
     *
     * @return array<string>|null  Returns null if no schema is defined
     */
    public function getSchemaHidden(): ?array
    {
        $schema = $this->getSchemaDefinition();

        return $schema?->getHidden();
    }

    /**
     * Get default values derived from the schema.
     *
     * @return array<string, mixed>|null  Returns null if no schema is defined
     */
    public function getSchemaDefaults(): ?array
    {
        $schema = $this->getSchemaDefinition();

        return $schema?->getDefaults();
    }

    /**
     * Apply schema defaults to the model's attributes.
     * Called during construction when defaults haven't been set yet.
     */
    protected function applySchemaDefaults(): void
    {
        $defaults = $this->getSchemaDefaults();

        if ($defaults === null) {
            return;
        }

        foreach ($defaults as $key => $value) {
            if (!array_key_exists($key, $this->attributes)) {
                $this->attributes[$key] = $value;
            }
        }
    }

    /**
     * Merge schema-derived hidden fields with explicitly declared hidden fields.
     */
    protected function mergeSchemaHidden(): void
    {
        $schemaHidden = $this->getSchemaHidden();

        if ($schemaHidden === null) {
            return;
        }

        $this->hidden = array_unique(array_merge($this->hidden, $schemaHidden));
    }
}
