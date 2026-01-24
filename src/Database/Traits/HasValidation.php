<?php

declare(strict_types=1);

namespace Plugs\Database\Traits;

trait HasValidation
{
    protected $rules = [];
    protected $messages = [];
    protected $errors = [];

    public function validate(?array $rules = null, ?array $messages = null): bool
    {
        $rules = $rules ?? $this->rules;
        $messages = $messages ?? $this->messages;

        if (empty($rules)) {
            return true;
        }

        $this->errors = [];

        foreach ($rules as $field => $fieldRules) {
            $value = $this->getAttribute($field);
            $ruleList = is_string($fieldRules) ? explode('|', $fieldRules) : $fieldRules;

            foreach ($ruleList as $rule) {
                if (!$this->validateRule($field, $value, $rule, $messages)) {
                    break;
                }
            }
        }

        return empty($this->errors);
    }

    protected function validateRule(string $field, $value, string $rule, array $messages): bool
    {
        $parts = explode(':', $rule, 2);
        $ruleName = $parts[0];
        $params = isset($parts[1]) ? explode(',', $parts[1]) : [];

        $valid = true;
        $message = $messages[$field . '.' . $ruleName] ?? $messages[$ruleName] ?? null;

        switch ($ruleName) {
            case 'required':
                $valid = !empty($value);
                $message = $message ?? "The {$field} field is required.";

                break;
            case 'email':
                $valid = filter_var($value, FILTER_VALIDATE_EMAIL) !== false;
                $message = $message ?? "The {$field} must be a valid email.";

                break;
            case 'min':
                $min = (int) $params[0];
                $valid = is_string($value) ? strlen($value) >= $min : $value >= $min;
                $message = $message ?? "The {$field} must be at least {$min}.";

                break;
            case 'max':
                $max = (int) $params[0];
                $valid = is_string($value) ? strlen($value) <= $max : $value <= $max;
                $message = $message ?? "The {$field} must not exceed {$max}.";

                break;
            case 'unique':
                $table = $params[0] ?? $this->table;
                $column = $params[1] ?? $field;
                $query = static::where($column, $value);
                if ($this->exists) {
                    $query->where($this->primaryKey, '!=', $this->getAttribute($this->primaryKey));
                }
                $valid = $query->count() === 0;
                $message = $message ?? "The {$field} has already been taken.";

                break;
            case 'in':
                $valid = in_array($value, $params);
                $message = $message ?? "The {$field} is invalid.";

                break;
            case 'numeric':
                $valid = is_numeric($value);
                $message = $message ?? "The {$field} must be numeric.";

                break;
            case 'integer':
                $valid = filter_var($value, FILTER_VALIDATE_INT) !== false;
                $message = $message ?? "The {$field} must be an integer.";

                break;
            case 'date':
                $valid = strtotime($value) !== false;
                $message = $message ?? "The {$field} must be a valid date.";

                break;
        }

        if (!$valid) {
            $this->errors[$field][] = $message;
        }

        return $valid;
    }

    public function getErrors(): array
    {
        return $this->errors;
    }

    public function hasErrors(): bool
    {
        return !empty($this->errors);
    }
}
