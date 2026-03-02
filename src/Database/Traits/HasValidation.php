<?php

declare(strict_types=1);

namespace Plugs\Database\Traits;

/**
 * @phpstan-ignore trait.unused
 */
trait HasValidation
{
    protected array $rules = [];
    protected array $messages = [];
    protected array $errors = [];

    /**
     * Boot the trait to automatically validate on save.
     */
    public static function bootHasValidation(): void
    {
        static::saving(function ($model) {
            if (!$model->validate()) {
                return false; // Cancels save
            }
        });
    }

    /**
     * Validate the model attributes against rules.
     */
    public function validate(?array $rules = null, ?array $messages = null): bool
    {
        $rules = $rules ?? $this->getValidationRules();
        $messages = $messages ?? $this->getValidationMessages();

        if (empty($rules)) {
            return true;
        }

        $validator = new \Plugs\Security\Validator($this->attributes, $rules, $messages);

        if (!$validator->validate()) {
            $this->errors = $validator->errors()->all();
            return false;
        }

        $this->errors = [];
        return true;
    }

    /**
     * Validate the model or throw an exception.
     *
     * @throws \Plugs\Exceptions\ValidationException
     */
    public function validateOrFail(?array $rules = null, ?array $messages = null): void
    {
        if (!$this->validate($rules, $messages)) {
            throw new \Plugs\Exceptions\ValidationException($this->getErrors());
        }
    }

    /**
     * Get the validation rules, supporting dynamic rules() method and schema.
     */
    protected function getValidationRules(): array
    {
        // Start with schema-derived rules (if schema exists)
        $schemaRules = [];
        if (method_exists($this, 'getSchemaRules')) {
            $schemaRules = $this->getSchemaRules() ?? [];
        }

        // Get explicit rules (from property or method)
        $explicitRules = method_exists($this, 'rules') ? $this->rules() : $this->rules;

        // Explicit rules override schema rules for the same field
        return array_merge($schemaRules, $explicitRules);
    }

    /**
     * Get the validation messages, supporting dynamic messages() method.
     */
    protected function getValidationMessages(): array
    {
        return method_exists($this, 'messages') ? $this->messages() : $this->messages;
    }

    /**
     * Get the validation errors.
     */
    public function getErrors(): array
    {
        return $this->errors;
    }

    /**
     * Check if the model has validation errors.
     */
    public function hasErrors(): bool
    {
        return !empty($this->errors);
    }
}
