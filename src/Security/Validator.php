<?php

declare(strict_types=1);

namespace Plugs\Security;

use Plugs\View\ErrorMessage;
use Plugs\Exceptions\ValidationException;
use Plugs\Security\Rules\RuleInterface;

/*
|--------------------------------------------------------------------------
| Validator Class
|--------------------------------------------------------------------------
|
| This class is for validating data against various rules.
| Now integrated with ErrorMessage for consistent error handling.
*/

class Validator
{
    private $data;
    private $rules;
    private $errors;
    private $customMessages = [];
    private $customAttributes = [];
    private $hasValidated = false;
    private static array $extensions = [];
    private array $instanceExtensions = [];

    public function __construct(array $data, array $rules, array $messages = [], array $attributes = [])
    {
        $this->data = $data;
        $this->rules = $rules;
        $this->errors = new ErrorMessage();
        $this->customMessages = $messages;
        $this->customAttributes = $attributes;
    }

    /**
     * Create a new validator instance
     */
    public static function make(array $data, array $rules, array $messages = [], array $attributes = []): self
    {
        return new static($data, $rules, $messages, $attributes);
    }

    /**
     * Validate all rules
     */
    public function validate(): bool
    {
        $this->hasValidated = true;

        foreach ($this->rules as $field => $rules) {
            // Support wildcard validation (e.g., items.*.name)
            if (strpos($field, '*') !== false) {
                $this->validateWildcard($field, $rules);
            } else {
                $ruleList = is_array($rules) ? $rules : explode('|', $rules);

                // Handle 'sometimes' rule: if field is not present in data, skip all validation for it
                if (in_array('sometimes', $ruleList, true) && !$this->hasValue($field)) {
                    continue;
                }

                $value = $this->getValue($field);

                // Handle 'nullable' rule: if value is null, skip all subsequent validation
                // except if other rules specify otherwise (Laravel behavior)
                if (in_array('nullable', $ruleList, true) && $value === null) {
                    continue;
                }

                foreach ($ruleList as $rule) {
                    // Skip sometimes and nullable rules themselves as they were handled above
                    if ($rule === 'sometimes' || $rule === 'nullable') {
                        continue;
                    }

                    $this->validateRule($field, $value, $rule);
                }
            }
        }

        return !$this->errors->any();
    }

    /**
     * Run the validator and throw a ValidationException on failure
     *
     * @return array The validated data
     * @throws ValidationException
     */
    public function validateOrFail(): array
    {
        if ($this->fails()) {
            throw new ValidationException($this->errors->toArray());
        }

        return $this->validated();
    }

    // ...

    private function validateRule(string $field, $value, $rule): void
    {
        // Support Rule objects
        if ($rule instanceof RuleInterface) {
            $isValid = $rule->validate($field, $value, $this->data);

            if ($isValid !== true) {
                $message = is_string($isValid) ? $isValid : $rule->message();
                $this->addError($field, 'custom_rule_obj', ['message' => $message]);
            }

            return;
        }

        $params = [];

        // Support closures in rules
        if ($rule instanceof \Closure) {
            $fail = function (string $message) use ($field) {
                $this->errors->add($field, $message);
            };

            $rule($field, $value, $fail, $this);

            return;
        }

        // Handle string rules
        $ruleString = (string) $rule;

        // Handle regex rule separately as it can contain colons
        if (str_starts_with($ruleString, 'regex:')) {
            $params = [substr($ruleString, 6)];
            $ruleName = 'regex';
        } elseif (strpos($ruleString, ':') !== false) {
            [$ruleName, $params] = explode(':', $ruleString, 2);
            $params = $this->parseParameters($params);
        } else {
            $ruleName = $ruleString;
        }

        $method = 'validate' . str_replace('_', '', ucwords($ruleName, '_'));

        if (method_exists($this, $method)) {
            $this->$method($field, $value, $params);

            return;
        }

        // Check custom rules
        $extension = $this->instanceExtensions[$ruleName] ?? self::$extensions[$ruleName] ?? null;
        if ($extension) {
            if (!$extension($field, $value, $params, $this)) {
                $this->addError($field, $ruleName);
            }
        }
    }

    /**
     * Register a custom validation rule globally
     */
    public static function extend(string $rule, callable $extension, ?string $message = null): void
    {
        self::$extensions[$rule] = $extension;
    }

    /**
     * Add a custom validation rule to this validator instance
     */
    public function addCustomRule(string $rule, callable $extension, ?string $message = null): void
    {
        $this->instanceExtensions[$rule] = $extension;

        if ($message) {
            $this->customMessages[$rule] = $message;
        }
    }

    /**
     * Get validation errors as ErrorMessage instance
     */
    public function errors(): ErrorMessage
    {
        if (!$this->hasValidated) {
            $this->validate();
        }

        return $this->errors;
    }

    /**
     * Get first error for a field
     */
    public function first(string $field): ?string
    {
        return $this->errors()->first($field);
    }

    /**
     * Check if validation failed
     */
    public function fails(): bool
    {
        if (!$this->hasValidated) {
            $this->validate();
        }

        return $this->errors->any();
    }

    /**
     * Check if validation passed
     */
    public function passes(): bool
    {
        if (!$this->hasValidated) {
            $this->validate();
        }

        return !$this->errors->any();
    }

    /**
     * Get validated data only
     */
    public function validated(): array
    {
        if (!$this->hasValidated) {
            $this->validate();
        }

        $validated = [];

        foreach (array_keys($this->rules) as $field) {
            if (isset($this->data[$field])) {
                $validated[$field] = $this->data[$field];
            }
        }

        return $validated;
    }

    /**
     * Validate wildcard fields (e.g., items.*.name)
     */
    private function validateWildcard(string $pattern, $rules): void
    {
        $fields = $this->expandWildcard($pattern);

        foreach ($fields as $field) {
            $value = $this->getValue($field);
            $ruleList = is_string($rules) ? explode('|', $rules) : $rules;

            foreach ($ruleList as $rule) {
                $this->validateRule($field, $value, $rule);
            }
        }
    }

    /**
     * Expand wildcard pattern to actual fields
     */
    private function expandWildcard(string $pattern): array
    {
        $fields = [];
        $parts = explode('.', $pattern);

        $this->expandWildcardRecursive($this->data, $parts, '', $fields);

        return $fields;
    }

    /**
     * Recursive wildcard expansion
     */
    private function expandWildcardRecursive($data, array $parts, string $prefix, array &$fields): void
    {
        if (empty($parts)) {
            $fields[] = rtrim($prefix, '.');

            return;
        }

        $part = array_shift($parts);

        if ($part === '*') {
            if (is_array($data)) {
                foreach ($data as $key => $value) {
                    $this->expandWildcardRecursive($value, $parts, $prefix . $key . '.', $fields);
                }
            }
        } else {
            $newPrefix = $prefix . $part . '.';
            $newData = $data[$part] ?? null;
            $this->expandWildcardRecursive($newData, $parts, $newPrefix, $fields);
        }
    }

    /**
     * Get value from data using dot notation
     */
    private function getValue(string $field)
    {
        return \Plugs\Utils\Arr::get($this->data, $field);
    }

    /**
     * Check if a field exists in data using dot notation
     */
    private function hasValue(string $field): bool
    {
        return \Plugs\Utils\Arr::has($this->data, $field);
    }



    /**
     * Parse rule parameters
     */
    private function parseParameters(string $params): array
    {
        // Support both comma and pipe separated parameters
        if (strpos($params, ',') !== false) {
            return array_map('trim', explode(',', $params));
        }

        return [$params];
    }

    /**
     * Add error message using ErrorMessage instance
     */
    private function addError(string $field, string $rule, array $replacements = []): void
    {
        if ($rule === 'custom_rule_obj' && isset($replacements['message'])) {
            $message = $replacements['message'];
        } else {
            $message = $this->getMessage($field, $rule);
        }

        // Replace placeholders
        foreach ($replacements as $key => $value) {
            $message = str_replace(":{$key}", (string) $value, $message);
        }

        // Replace field name
        $attribute = $this->customAttributes[$field] ?? $field;
        $message = str_replace(':attribute', $attribute, $message);
        $message = str_replace(':field', $attribute, $message);

        $this->errors->add($field, $message);
    }

    /**
     * Get error message for rule
     */
    private function getMessage(string $field, string $rule): string
    {
        // Check custom message for specific field
        if (isset($this->customMessages["{$field}.{$rule}"])) {
            return $this->customMessages["{$field}.{$rule}"];
        }

        // Check custom message for rule
        if (isset($this->customMessages[$rule])) {
            return $this->customMessages[$rule];
        }

        // Default messages
        return $this->getDefaultMessage($rule);
    }

    /**
     * Default error messages
     */
    private function getDefaultMessage(string $rule): string
    {
        $messages = [
            'required' => 'The :attribute field is required.',
            'email' => 'The :attribute must be a valid email address.',
            'min' => 'The :attribute must be at least :min characters.',
            'max' => 'The :attribute must not exceed :max characters.',
            'between' => 'The :attribute must be between :min and :max.',
            'numeric' => 'The :attribute must be a number.',
            'integer' => 'The :attribute must be an integer.',
            'string' => 'The :attribute must be a string.',
            'array' => 'The :attribute must be an array.',
            'boolean' => 'The :attribute must be true or false.',
            'url' => 'The :attribute must be a valid URL.',
            'regex' => 'The :attribute format is invalid.',
            'confirmed' => 'The :attribute confirmation does not match.',
            'same' => 'The :attribute and :other must match.',
            'different' => 'The :attribute and :other must be different.',
            'in' => 'The selected :attribute is invalid.',
            'not_in' => 'The selected :attribute is invalid.',
            'alpha' => 'The :attribute may only contain letters.',
            'alpha_dash' => 'The :attribute may only contain letters, numbers, dashes and underscores.',
            'alpha_num' => 'The :attribute may only contain letters and numbers.',
            'date' => 'The :attribute is not a valid date.',
            'date_format' => 'The :attribute does not match the format :format.',
            'before' => 'The :attribute must be a date before :date.',
            'after' => 'The :attribute must be a date after :date.',
            'unique' => 'The :attribute has already been taken.',
            'exists' => 'The selected :attribute is invalid.',
            'ip' => 'The :attribute must be a valid IP address.',
            'ipv4' => 'The :attribute must be a valid IPv4 address.',
            'ipv6' => 'The :attribute must be a valid IPv6 address.',
            'json' => 'The :attribute must be a valid JSON string.',
            'file' => 'The :attribute must be a file.',
            'image' => 'The :attribute must be an image.',
            'mimes' => 'The :attribute must be a file of type: :values.',
            'digits' => 'The :attribute must be :digits digits.',
            'digits_between' => 'The :attribute must be between :min and :max digits.',
            'size' => 'The :attribute must be :size.',
            'uuid' => 'The :attribute must be a valid UUID.',
            'timezone' => 'The :attribute must be a valid timezone.',
            'active_url' => 'The :attribute is not a valid URL.',
            'password' => 'The :attribute must meet the complexity requirements: :requirements.',
            'strong_password' => 'The :attribute must be at least 8 characters and contain at least one uppercase letter, one lowercase letter, one number, and one special character.',
            'safe_html' => 'The :attribute contains invalid or dangerous HTML tags.',
            'accepted' => 'The :attribute must be accepted.',
            'declined' => 'The :attribute must be declined.',
            'present' => 'The :attribute field must be present.',
            'prohibited' => 'The :attribute field is prohibited.',
            'prohibited_if' => 'The :attribute field is prohibited when :other is :value.',
            'prohibited_unless' => 'The :attribute field is prohibited unless :other is in :value.',
            'gt' => 'The :attribute must be greater than :value.',
            'gte' => 'The :attribute must be greater than or equal :value.',
            'lt' => 'The :attribute must be less than :value.',
            'lte' => 'The :attribute must be less than or equal :value.',
            'multiple_of' => 'The :attribute must be a multiple of :value.',
            'min_digits' => 'The :attribute must have at least :value digits.',
            'max_digits' => 'The :attribute must not have more than :value digits.',
            'mac_address' => 'The :attribute must be a valid MAC address.',
            'ulid' => 'The :attribute must be a valid ULID.',
            'phone' => 'The :attribute must be a valid phone number.',
            'credit_card' => 'The :attribute must be a valid credit card number.',
            'hex_color' => 'The :attribute must be a valid hexadecimal color.',
            'slug' => 'The :attribute must be a valid slug.',
            'base64' => 'The :attribute must be a valid base64 string.',
            'ascii' => 'The :attribute must be ASCII characters only.',
            'filled' => 'The :attribute field must have a value.',
            'starts_with' => 'The :attribute must start with one of the following: :values.',
            'ends_with' => 'The :attribute must end with one of the following: :values.',
            'contains' => 'The :attribute must contain the value: :value.',
            'lowercase' => 'The :attribute must be lowercase.',
            'uppercase' => 'The :attribute must be uppercase.',
            'ai_fail' => 'The :attribute failed AI validation: :reason',
            'custom_rule' => 'The :attribute failed validation.',
        ];

        return $messages[$rule] ?? "The :attribute is invalid.";
    }

    // =====================================================
    // VALIDATION RULES
    // =====================================================

    // =====================================================
    // VALIDATION RULES
    // =====================================================

    /**
     * Required field
     */
    private function validateRequired(string $field, $value): void
    {
        if (is_null($value)) {
            $this->addError($field, 'required');
        } elseif (is_string($value) && trim($value) === '') {
            $this->addError($field, 'required');
        } elseif (is_array($value) && empty($value)) {
            $this->addError($field, 'required');
        }
    }

    /**
     * Required if another field equals a value
     */
    private function validateRequiredIf(string $field, $value, array $params): void
    {
        $otherField = $params[0];
        $otherValue = $params[1] ?? null;
        $actualValue = $this->getValue($otherField);

        if ($actualValue == $otherValue) {
            $this->validateRequired($field, $value);
        }
    }

    /**
     * Required unless another field equals a value
     */
    private function validateRequiredUnless(string $field, $value, array $params): void
    {
        $otherField = $params[0];
        $otherValue = $params[1] ?? null;
        $actualValue = $this->getValue($otherField);

        if ($actualValue != $otherValue) {
            $this->validateRequired($field, $value);
        }
    }

    /**
     * Required with another field
     */
    private function validateRequiredWith(string $field, $value, array $params): void
    {
        foreach ($params as $otherField) {
            if ($this->hasValue($otherField) && $this->getValue($otherField) !== null && $this->getValue($otherField) !== '') {
                $this->validateRequired($field, $value);
                return;
            }
        }
    }

    /**
     * Required without another field
     */
    private function validateRequiredWithout(string $field, $value, array $params): void
    {
        foreach ($params as $otherField) {
            if (!$this->hasValue($otherField) || $this->getValue($otherField) === null || $this->getValue($otherField) === '') {
                $this->validateRequired($field, $value);
                return;
            }
        }
    }

    /**
     * Email validation
     */
    private function validateEmail(string $field, $value): void
    {
        if (empty($value)) {
            return;
        }

        if (!filter_var($value, FILTER_VALIDATE_EMAIL)) {
            $this->addError($field, 'email');
        }
    }

    /**
     * Minimum length/value
     */
    private function validateMin(string $field, $value, array $params): void
    {
        $min = (int) $params[0];

        if ($value === null || $value === '') {
            return;
        }

        // Check if value is a numeric type AND the 'numeric' rule was not applied
        // If 'numeric' rule is present, treat it as a number comparison.
        // Otherwise, if it's a string that looks like a number, default to string length unless 'numeric' rule is explicit.
        // However, common framework behavior:
        // - Integers/Floats -> Size is value
        // - Strings -> Size is strlen
        // - Arrays -> Size is count

        $size = null;

        if (is_int($value) || is_float($value)) {
            $size = $value;
        } elseif (is_array($value)) {
            $size = count($value);
        } else {
            // It is a string
            $size = mb_strlen((string) $value);
        }

        if ($size < $min) {
            $this->addError($field, 'min', ['min' => $min]);
        }
    }

    /**
     * Maximum length/value
     */
    private function validateMax(string $field, $value, array $params): void
    {
        $max = (int) $params[0];

        if ($value === null || $value === '') {
            return;
        }

        $size = null;

        if (is_int($value) || is_float($value)) {
            $size = $value;
        } elseif (is_array($value)) {
            $size = count($value);
        } else {
            $size = mb_strlen((string) $value);
        }

        if ($size > $max) {
            $this->addError($field, 'max', ['max' => $max]);
        }
    }

    /**
     * Between min and max
     */
    private function validateBetween(string $field, $value, array $params): void
    {
        $min = (int) $params[0];
        $max = (int) $params[1];

        if ($value === null || $value === '') {
            return;
        }

        $size = null;

        if (is_int($value) || is_float($value)) {
            $size = $value;
        } elseif (is_array($value)) {
            $size = count($value);
        } else {
            $size = mb_strlen((string) $value);
        }

        if ($size < $min || $size > $max) {
            $this->addError($field, 'between', ['min' => $min, 'max' => $max]);
        }
    }

    /**
     * Numeric validation
     */
    private function validateNumeric(string $field, $value): void
    {
        if ($value === null || $value === '') {
            return;
        }

        if (!is_numeric($value)) {
            $this->addError($field, 'numeric');
        }
    }

    /**
     * Integer validation
     */
    private function validateInteger(string $field, $value): void
    {
        if ($value === null || $value === '') {
            return;
        }

        if (!filter_var($value, FILTER_VALIDATE_INT) && (string) (int) $value !== (string) $value) {
            $this->addError($field, 'integer');
        }
    }

    /**
     * Confirmed field validation
     */
    private function validateConfirmed(string $field, $value): void
    {
        $confirmField = $field . '_confirmation';

        if (!$this->hasValue($confirmField)) {
            $this->addError($field, 'confirmed');
            return;
        }

        $confirmValue = $this->getValue($confirmField);

        if ($value !== $confirmValue) {
            $this->addError($field, 'confirmed');
        }
    }

    // ... (keep previous methods)

    /**
     * Phone validation (new)
     */
    private function validatePhone(string $field, $value): void
    {
        if (empty($value))
            return;
        // Basic international phone regex
        $pattern = '/^[\+]?[(]?[0-9]{1,4}[)]?[-\s\.]?[(]?[0-9]{1,3}[)]?[-\s\.]?[0-9]{3,4}[-\s\.]?[0-9]{3,4}$/';

        if (!preg_match($pattern, (string) $value)) {
            $this->addError($field, 'phone');
        }
    }

    /**
     * Credit Card validation (Luhn algorithm) (new)
     */
    private function validateCreditCard(string $field, $value): void
    {
        if (empty($value))
            return;

        $number = preg_replace('/\D/', '', $value);
        if (empty($number)) {
            $this->addError($field, 'credit_card');
            return;
        }

        $sum = 0;
        $shouldDouble = false;

        for ($i = strlen($number) - 1; $i >= 0; $i--) {
            $digit = (int) $number[$i];

            if ($shouldDouble) {
                if (($digit *= 2) > 9) {
                    $digit -= 9;
                }
            }

            $sum += $digit;
            $shouldDouble = !$shouldDouble;
        }

        if ($sum % 10 !== 0) {
            $this->addError($field, 'credit_card');
        }
    }

    /**
     * Hex Color validation (new)
     */
    private function validateHexColor(string $field, $value): void
    {
        if (empty($value))
            return;

        if (!preg_match('/^#?([a-fA-F0-9]{3}|[a-fA-F0-9]{6})$/', $value)) {
            $this->addError($field, 'hex_color');
        }
    }

    /**
     * Slug validation (new)
     */
    private function validateSlug(string $field, $value): void
    {
        if (empty($value))
            return;

        if (!preg_match('/^[a-z0-9]+(?:-[a-z0-9]+)*$/', $value)) {
            $this->addError($field, 'slug');
        }
    }

    /**
     * Base64 validation (new)
     */
    private function validateBase64(string $field, $value): void
    {
        if (empty($value))
            return;

        if (!preg_match('/^[a-zA-Z0-9\/\r\n+]*={0,2}$/', $value)) {
            $this->addError($field, 'base64');
        }
    }

    /**
     * ASCII validation (new)
     */
    private function validateAscii(string $field, $value): void
    {
        if (empty($value))
            return;

        if (!mb_check_encoding($value, 'ASCII')) {
            $this->addError($field, 'ascii');
        }
    }

    /**
     * Filled validation (new)
     */
    private function validateFilled(string $field, $value): void
    {
        if (!$this->hasValue($field) || empty($value)) {
            $this->addError($field, 'filled');
        }
    }


    /**
     * UUID validation
     */
    private function validateUuid(string $field, $value): void
    {
        if ($value === null || $value === '') {
            return;
        }

        $pattern = '/^[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}$/i';

        if (!preg_match($pattern, (string) $value)) {
            $this->addError($field, 'uuid');
        }
    }

    /**
     * AI-powered validation
     * Usage: ai:check if the text is constructive
     */
    private function validateAi(string $field, $value, array $params): void
    {
        if (empty($value)) {
            return;
        }

        $instruction = $params[0] ?? "Validate this input.";
        $prompt = "Task: {$instruction}\nInput for field '{$field}': \"{$value}\"\n\nDoes this input satisfy the requirement? Return ONLY 'yes' or a brief explanation of why it fails.";

        $response = ai()->prompt($prompt, [], ['max_tokens' => 50]);
        $response = strtolower(trim($response));

        if ($response !== 'yes' && strpos($response, 'yes') !== 0) {
            $this->addError($field, 'ai_fail', ['reason' => $response]);
        }
    }

    /**
     * Digits validation
     */
    private function validateDigits(string $field, $value, array $params): void
    {
        $length = (int) $params[0];

        if ($value !== null && (!ctype_digit((string) $value) || strlen((string) $value) !== $length)) {
            $this->addError($field, 'digits', ['digits' => $length]);
        }
    }

    /**
     * Digits between
     */
    private function validateDigitsBetween(string $field, $value, array $params): void
    {
        $min = (int) $params[0];
        $max = (int) $params[1];
        $length = strlen((string) $value);

        if ($value !== null && (!ctype_digit((string) $value) || $length < $min || $length > $max)) {
            $this->addError($field, 'digits_between', ['min' => $min, 'max' => $max]);
        }
    }

    /**
     * Timezone validation
     */
    private function validateTimezone(string $field, $value): void
    {
        if ($value === null || $value === '') {
            return;
        }

        if (!in_array($value, timezone_identifiers_list())) {
            $this->addError($field, 'timezone');
        }
    }

    /**
     * File validation (for uploaded files)
     */
    private function validateFile(string $field, $value): void
    {
        if ($value !== null && !($value instanceof \Plugs\Upload\UploadedFile)) {
            $this->addError($field, 'file');
        }
    }

    /**
     * Image validation
     */
    private function validateImage(string $field, $value): void
    {
        if ($value instanceof \Plugs\Upload\UploadedFile) {
            if (!$value->isImage()) {
                $this->addError($field, 'image');
            }
        }
    }

    /**
     * MIME types validation
     */
    private function validateMimes(string $field, $value, array $params): void
    {
        if ($value instanceof \Plugs\Upload\UploadedFile) {
            $extension = $value->getClientExtension();

            if (!in_array($extension, $params)) {
                $this->addError($field, 'mimes', ['values' => implode(', ', $params)]);
            }
        }
    }

    /**
     * Size validation
     */
    private function validateSize(string $field, $value, array $params): void
    {
        $size = (int) $params[0];

        if ($value === null || $value === '') {
            return;
        }

        if ($value instanceof \Plugs\Upload\UploadedFile) {
            $actualSize = $value->getSize() / 1024; // KB

            if ($actualSize != $size) {
                $this->addError($field, 'size', ['size' => $size]);
            }
        } elseif (is_numeric($value)) {
            if ($value != $size) {
                $this->addError($field, 'size', ['size' => $size]);
            }
        } elseif (is_string($value)) {
            if (strlen($value) != $size) {
                $this->addError($field, 'size', ['size' => $size]);
            }
        } elseif (is_array($value)) {
            if (count($value) != $size) {
                $this->addError($field, 'size', ['size' => $size]);
            }
        }
    }

    /**
     * Nullable field (allows null)
     */
    private function validateNullable(string $field, $value): void
    {
        // This rule does nothing - it just marks field as nullable
    }

    /**
     * Sometimes validation (only validate if present)
     */
    private function validateSometimes(string $field, $value): void
    {
        // This rule does nothing - it just marks field as optional
    }

    /**
     * Starts with validation
     */
    private function validateStartsWith(string $field, $value, array $params): void
    {
        if ($value === null || $value === '') {
            return;
        }

        foreach ($params as $prefix) {
            if (strpos($value, $prefix) === 0) {
                return;
            }
        }

        $this->addError($field, 'starts_with', ['values' => implode(', ', $params)]);
    }

    /**
     * Ends with validation
     */
    private function validateEndsWith(string $field, $value, array $params): void
    {
        if ($value === null || $value === '') {
            return;
        }

        foreach ($params as $suffix) {
            if (substr($value, -strlen($suffix)) === $suffix) {
                return;
            }
        }

        $this->addError($field, 'ends_with', ['values' => implode(', ', $params)]);
    }

    /**
     * Contains validation
     */
    private function validateContains(string $field, $value, array $params): void
    {
        if ($value === null || $value === '') {
            return;
        }

        $needle = $params[0];

        if (strpos($value, $needle) === false) {
            $this->addError($field, 'contains', ['value' => $needle]);
        }
    }

    /**
     * Lowercase validation
     */
    private function validateLowercase(string $field, $value): void
    {
        if ($value !== null && $value !== '' && $value !== strtolower($value)) {
            $this->addError($field, 'lowercase');
        }
    }

    /**
     * Uppercase validation
     */
    private function validateUppercase(string $field, $value): void
    {
        if ($value !== null && $value !== '' && $value !== strtoupper($value)) {
            $this->addError($field, 'uppercase');
        }
    }

    /**
     * Password complexity validation
     * Usage: password:min_8,letters,symbols,numbers
     */
    private function validatePassword(string $field, $value, array $params): void
    {
        if ($value === null || $value === '') {
            return;
        }

        $failed = [];
        $requirements = [];

        foreach ($params as $param) {
            if (strpos($param, 'min_') === 0) {
                $min = (int) substr($param, 4);
                if (strlen($value) < $min) {
                    $failed[] = "at least $min characters";
                }
                $requirements[] = "at least $min characters";
            } elseif ($param === 'letters') {
                if (!preg_match('/[a-zA-Z]/', $value)) {
                    $failed[] = 'at least one letter';
                }
                $requirements[] = 'at least one letter';
            } elseif ($param === 'numbers') {
                if (!preg_match('/[0-9]/', $value)) {
                    $failed[] = 'at least one number';
                }
                $requirements[] = 'at least one number';
            } elseif ($param === 'symbols') {
                if (!preg_match('/[^a-zA-Z0-9]/', $value)) {
                    $failed[] = 'at least one special character';
                }
                $requirements[] = 'at least one special character';
            } elseif ($param === 'mixed') {
                if (!preg_match('/[a-z]/', $value) || !preg_match('/[A-Z]/', $value)) {
                    $failed[] = 'both uppercase and lowercase letters';
                }
                $requirements[] = 'both uppercase and lowercase letters';
            }
        }

        if (!empty($failed)) {
            $this->addError($field, 'password', ['requirements' => implode(', ', $requirements)]);
        }
    }

    /**
     * Strong password validation
     */
    private function validateStrongPassword(string $field, $value): void
    {
        if ($value === null || $value === '') {
            return;
        }

        $pattern = '/^(?=.*[a-z])(?=.*[A-Z])(?=.*\d)(?=.*[^a-zA-Z0-9]).{8,}$/';

        if (!preg_match($pattern, $value)) {
            $this->addError($field, 'strong_password');
        }
    }

    /**
     * Safe HTML validation
     */
    private function validateSafeHtml(string $field, $value): void
    {
        if ($value === null || $value === '') {
            return;
        }

        $sanitized = \Plugs\Security\Sanitizer::safeHtml($value);

        // If the sanitized version is different from original, it might contain dangerous tags
        // However, some editors might send slightly different formatting.
        // A better check is to see if any forbidden tags were removed.
        if (strip_tags((string) $value) !== strip_tags($sanitized)) {
            // This is a simple check; more advanced check could compare DOM structures
        }

        // For validation purposes, we can check if certain very dangerous patterns exist
        $dangerousPatterns = [
            '/<script/i',
            '/on[a-z]+\s*=/i',
            '/javascript:/i',
            '/data:/i',
            '/<iframe/i',
            '/<object/i',
            '/<embed/i',
        ];

        foreach ($dangerousPatterns as $pattern) {
            if (preg_match($pattern, (string) $value)) {
                $this->addError($field, 'safe_html');

                break;
            }
        }
    }

    /**
     * Accepted validation (yes, on, 1, or true)
     */
    private function validateAccepted(string $field, $value): void
    {
        $acceptable = ['yes', 'on', '1', 1, true, 'true'];

        if (!in_array($value, $acceptable, true)) {
            $this->addError($field, 'accepted');
        }
    }

    /**
     * Declined validation (no, off, 0, or false)
     */
    private function validateDeclined(string $field, $value): void
    {
        $acceptable = ['no', 'off', '0', 0, false, 'false'];

        if (!in_array($value, $acceptable, true)) {
            $this->addError($field, 'declined');
        }
    }

    /**
     * Present validation (field must exist in data)
     */
    private function validatePresent(string $field, $value): void
    {
        if (!$this->hasValue($field)) {
            $this->addError($field, 'present');
        }
    }

    /**
     * Prohibited validation (field must NOT be present)
     */
    private function validateProhibited(string $field, $value): void
    {
        if ($this->hasValue($field)) {
            $this->addError($field, 'prohibited');
        }
    }

    /**
     * Prohibited if validation
     */
    private function validateProhibitedIf(string $field, $value, array $params): void
    {
        if (count($params) < 2) {
            return;
        }

        $otherField = $params[0];
        $otherValues = array_slice($params, 1);
        $actualValue = $this->getValue($otherField);

        if (in_array((string) $actualValue, $otherValues, true) && $this->hasValue($field)) {
            $this->addError($field, 'prohibited_if', ['other' => $otherField, 'value' => implode(', ', $otherValues)]);
        }
    }

    /**
     * Prohibited unless validation
     */
    private function validateProhibitedUnless(string $field, $value, array $params): void
    {
        if (count($params) < 2) {
            return;
        }

        $otherField = $params[0];
        $otherValues = array_slice($params, 1);
        $actualValue = $this->getValue($otherField);

        if (!in_array((string) $actualValue, $otherValues, true) && $this->hasValue($field)) {
            $this->addError($field, 'prohibited_unless', ['other' => $otherField, 'value' => implode(', ', $otherValues)]);
        }
    }

    /**
     * Greater than validation (field > other)
     */
    private function validateGt(string $field, $value, array $params): void
    {
        if (!isset($params[0])) {
            return;
        }

        $comparison = $params[0];
        $otherValue = $this->hasValue($comparison) ? $this->getValue($comparison) : $comparison;

        if (!(is_numeric($value) && is_numeric($otherValue) && $value > $otherValue)) {
            $this->addError($field, 'gt', ['value' => $otherValue]);
        }
    }

    /**
     * Greater than or equal validation (field >= other)
     */
    private function validateGte(string $field, $value, array $params): void
    {
        if (!isset($params[0])) {
            return;
        }

        $comparison = $params[0];
        $otherValue = $this->hasValue($comparison) ? $this->getValue($comparison) : $comparison;

        if (!(is_numeric($value) && is_numeric($otherValue) && $value >= $otherValue)) {
            $this->addError($field, 'gte', ['value' => $otherValue]);
        }
    }

    /**
     * Less than validation (field < other)
     */
    private function validateLt(string $field, $value, array $params): void
    {
        if (!isset($params[0])) {
            return;
        }

        $comparison = $params[0];
        $otherValue = $this->hasValue($comparison) ? $this->getValue($comparison) : $comparison;

        if (!(is_numeric($value) && is_numeric($otherValue) && $value < $otherValue)) {
            $this->addError($field, 'lt', ['value' => $otherValue]);
        }
    }

    /**
     * Less than or equal validation (field <= other)
     */
    private function validateLte(string $field, $value, array $params): void
    {
        if (!isset($params[0])) {
            return;
        }

        $comparison = $params[0];
        $otherValue = $this->hasValue($comparison) ? $this->getValue($comparison) : $comparison;

        if (!(is_numeric($value) && is_numeric($otherValue) && $value <= $otherValue)) {
            $this->addError($field, 'lte', ['value' => $otherValue]);
        }
    }

    /**
     * Multiple of validation
     */
    private function validateMultipleOf(string $field, $value, array $params): void
    {
        if (!isset($params[0]) || !is_numeric($value)) {
            return;
        }

        $multiple = (float) $params[0];

        if ($multiple == 0 || fmod((float) $value, $multiple) != 0) {
            $this->addError($field, 'multiple_of', ['value' => $multiple]);
        }
    }

    /**
     * Minimum digits validation
     */
    private function validateMinDigits(string $field, $value, array $params): void
    {
        if (!isset($params[0]) || !is_numeric($value)) {
            return;
        }

        $min = (int) $params[0];

        if (strlen((string) abs((int) $value)) < $min) {
            $this->addError($field, 'min_digits', ['value' => $min]);
        }
    }

    /**
     * Maximum digits validation
     */
    private function validateMaxDigits(string $field, $value, array $params): void
    {
        if (!isset($params[0]) || !is_numeric($value)) {
            return;
        }

        $max = (int) $params[0];

        if (strlen((string) abs((int) $value)) > $max) {
            $this->addError($field, 'max_digits', ['value' => $max]);
        }
    }

    /**
     * MAC address validation
     */
    private function validateMacAddress(string $field, $value): void
    {
        if (!filter_var($value, FILTER_VALIDATE_MAC)) {
            $this->addError($field, 'mac_address');
        }
    }

    /**
     * ULID validation
     */
    private function validateUlid(string $field, $value): void
    {
        if ($value === null || $value === '') {
            return;
        }

        if (!preg_match('/^[0-7][0-9A-HJKMNP-TV-Z]{25}$/i', (string) $value)) {
            $this->addError($field, 'ulid');
        }
    }

    /**
     * Unique validation (checks database)
     * Usage: unique:table,column,except,id_column
     */
    private function validateUnique(string $field, $value, array $params): void
    {
        if (empty($value) || empty($params)) {
            return;
        }

        $table = $params[0];
        $column = $params[1] ?? $field;
        $except = $params[2] ?? null;
        $idColumn = $params[3] ?? 'id';

        $query = db($table)->where($column, '=', $value);

        if ($except !== null && $except !== 'NULL') {
            $query->where($idColumn, '!=', $except);
        }

        if ($query->exists()) {
            $this->addError($field, 'unique');
        }
    }

    /**
     * Exists validation (checks database)
     * Usage: exists:table,column
     */
    private function validateExists(string $field, $value, array $params): void
    {
        if (empty($value) || empty($params)) {
            return;
        }

        $table = $params[0];
        $column = $params[1] ?? $field;

        if (!db($table)->where($column, '=', $value)->exists()) {
            $this->addError($field, 'exists');
        }
    }

    /**
     * URL validation
     */
    private function validateUrl(string $field, $value): void
    {
        if (empty($value)) {
            return;
        }

        if (!filter_var($value, FILTER_VALIDATE_URL)) {
            $this->addError($field, 'url');
        }
    }

    /**
     * Regex validation
     */
    private function validateRegex(string $field, $value, array $params): void
    {
        if (empty($value) || empty($params)) {
            return;
        }

        if (!preg_match($params[0], (string) $value)) {
            $this->addError($field, 'regex');
        }
    }

    /**
     * In validation
     */
    private function validateIn(string $field, $value, array $params): void
    {
        if ($value === null || $value === '') {
            return;
        }

        if (!in_array((string) $value, $params)) {
            $this->addError($field, 'in');
        }
    }

    /**
     * Not In validation
     */
    private function validateNotIn(string $field, $value, array $params): void
    {
        if ($value === null || $value === '') {
            return;
        }

        if (in_array((string) $value, $params)) {
            $this->addError($field, 'not_in');
        }
    }

    /**
     * Alpha validation
     */
    private function validateAlpha(string $field, $value): void
    {
        if (empty($value)) {
            return;
        }

        if (!is_string($value) || !preg_match('/^\pL+$/u', $value)) {
            $this->addError($field, 'alpha');
        }
    }

    /**
     * Alpha Numeric validation
     */
    private function validateAlphaNum(string $field, $value): void
    {
        if (empty($value)) {
            return;
        }

        if (!is_string($value) || !preg_match('/^[\pL\pN]+$/u', $value)) {
            $this->addError($field, 'alpha_num');
        }
    }

    /**
     * Alpha Dash validation
     */
    private function validateAlphaDash(string $field, $value): void
    {
        if (empty($value)) {
            return;
        }

        if (!is_string($value) || !preg_match('/^[\pL\pN_-]+$/u', $value)) {
            $this->addError($field, 'alpha_dash');
        }
    }

    /**
     * Date validation
     */
    private function validateDate(string $field, $value): void
    {
        if (empty($value)) {
            return;
        }

        if (!strtotime((string) $value)) {
            $this->addError($field, 'date');
        }
    }

    /**
     * Date Format validation
     */
    private function validateDateFormat(string $field, $value, array $params): void
    {
        if (empty($value) || empty($params)) {
            return;
        }

        $format = $params[0];
        $date = \DateTime::createFromFormat($format, (string) $value);

        if (!$date || $date->format($format) !== (string) $value) {
            $this->addError($field, 'date_format', ['format' => $format]);
        }
    }

    /**
     * Before date validation
     */
    private function validateBefore(string $field, $value, array $params): void
    {
        if (empty($value) || empty($params)) {
            return;
        }

        $compareDate = strtotime($params[0]);
        $valueDate = strtotime((string) $value);

        if (!$valueDate || $valueDate >= $compareDate) {
            $this->addError($field, 'before', ['date' => $params[0]]);
        }
    }

    /**
     * After date validation
     */
    private function validateAfter(string $field, $value, array $params): void
    {
        if (empty($value) || empty($params)) {
            return;
        }

        $compareDate = strtotime($params[0]);
        $valueDate = strtotime((string) $value);

        if (!$valueDate || $valueDate <= $compareDate) {
            $this->addError($field, 'after', ['date' => $params[0]]);
        }
    }

    /**
     * IP validation
     */
    private function validateIp(string $field, $value): void
    {
        if (empty($value)) {
            return;
        }

        if (!filter_var($value, FILTER_VALIDATE_IP)) {
            $this->addError($field, 'ip');
        }
    }

    /**
     * IPv4 validation
     */
    private function validateIpv4(string $field, $value): void
    {
        if (empty($value)) {
            return;
        }

        if (!filter_var($value, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4)) {
            $this->addError($field, 'ipv4');
        }
    }

    /**
     * IPv6 validation
     */
    private function validateIpv6(string $field, $value): void
    {
        if (empty($value)) {
            return;
        }

        if (!filter_var($value, FILTER_VALIDATE_IP, FILTER_FLAG_IPV6)) {
            $this->addError($field, 'ipv6');
        }
    }

    /**
     * JSON validation
     */
    private function validateJson(string $field, $value): void
    {
        if ($value === null || $value === '') {
            return;
        }

        if (!is_string($value) || !\Plugs\Utils\Str::isJson($value)) {
            $this->addError($field, 'json');
        }
    }

    /**
     * Array validation
     */
    private function validateArray(string $field, $value): void
    {
        if ($value === null) {
            return;
        }

        if (!is_array($value)) {
            $this->addError($field, 'array');
        }
    }

    /**
     * String validation
     */
    private function validateString(string $field, $value): void
    {
        if ($value === null) {
            return;
        }

        if (!is_string($value)) {
            $this->addError($field, 'string');
        }
    }

    /**
     * Boolean validation
     */
    private function validateBoolean(string $field, $value): void
    {
        if ($value === null || $value === '') {
            return;
        }

        $acceptable = [true, false, 1, 0, '1', '0', 'true', 'false'];

        if (!in_array($value, $acceptable, true)) {
            $this->addError($field, 'boolean');
        }
    }
}
