<?php

declare(strict_types=1);

namespace Plugs\Security;

use Plugs\View\ErrorMessage;

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

    public function __construct(array $data, array $rules, array $messages = [], array $attributes = [])
    {
        $this->data = $data;
        $this->rules = $rules;
        $this->errors = new ErrorMessage();
        $this->customMessages = $messages;
        $this->customAttributes = $attributes;
    }

    /**
     * Validate all rules
     */
    public function validate(): bool
    {
        foreach ($this->rules as $field => $rules) {
            // Support wildcard validation (e.g., items.*.name)
            if (strpos($field, '*') !== false) {
                $this->validateWildcard($field, $rules);
            } else {
                $value = $this->getValue($field);
                $ruleList = is_string($rules) ? explode('|', $rules) : $rules;

                foreach ($ruleList as $rule) {
                    $this->validateRule($field, $value, $rule);
                }
            }
        }

        return !$this->errors->any();
    }

    /**
     * Get validation errors as ErrorMessage instance
     */
    public function errors(): ErrorMessage
    {
        return $this->errors;
    }

    /**
     * Get first error for a field
     */
    public function first(string $field): ?string
    {
        return $this->errors->first($field);
    }

    /**
     * Check if validation failed
     */
    public function fails(): bool
    {
        return $this->errors->any();
    }

    /**
     * Check if validation passed
     */
    public function passes(): bool
    {
        return !$this->errors->any();
    }

    /**
     * Get validated data only
     */
    public function validated(): array
    {
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
        $keys = explode('.', $field);
        $value = $this->data;

        foreach ($keys as $key) {
            if (is_array($value) && array_key_exists($key, $value)) {
                $value = $value[$key];
            } else {
                return null;
            }
        }

        return $value;
    }

    /**
     * Validate single rule
     */
    private function validateRule(string $field, $value, string $rule): void
    {
        if (strpos($rule, ':') !== false) {
            [$rule, $params] = explode(':', $rule, 2);
            $params = $this->parseParameters($params);
        } else {
            $params = [];
        }

        $method = 'validate' . str_replace('_', '', ucwords($rule, '_'));

        if (method_exists($this, $method)) {
            $this->$method($field, $value, $params);
        }
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
        $message = $this->getMessage($field, $rule);

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
        ];

        return $messages[$rule] ?? "The :attribute is invalid.";
    }

    // =====================================================
    // VALIDATION RULES
    // =====================================================

    /**
     * Required field
     */
    private function validateRequired(string $field, $value): void
    {
        if ($value === null || $value === '' || (is_array($value) && empty($value))) {
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

        if ($actualValue == $otherValue && ($value === null || $value === '')) {
            $this->addError($field, 'required');
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

        if ($actualValue != $otherValue && ($value === null || $value === '')) {
            $this->addError($field, 'required');
        }
    }

    /**
     * Required with another field
     */
    private function validateRequiredWith(string $field, $value, array $params): void
    {
        foreach ($params as $otherField) {
            $otherValue = $this->getValue($otherField);
            if ($otherValue !== null && $otherValue !== '') {
                if ($value === null || $value === '') {
                    $this->addError($field, 'required');
                }

                break;
            }
        }
    }

    /**
     * Required without another field
     */
    private function validateRequiredWithout(string $field, $value, array $params): void
    {
        foreach ($params as $otherField) {
            $otherValue = $this->getValue($otherField);
            if ($otherValue === null || $otherValue === '') {
                if ($value === null || $value === '') {
                    $this->addError($field, 'required');
                }

                break;
            }
        }
    }

    /**
     * Email validation
     */
    private function validateEmail(string $field, $value): void
    {
        if ($value !== null && $value !== '' && !filter_var($value, FILTER_VALIDATE_EMAIL)) {
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

        if (is_numeric($value)) {
            if ($value < $min) {
                $this->addError($field, 'min', ['min' => $min]);
            }
        } elseif (is_string($value)) {
            if (strlen($value) < $min) {
                $this->addError($field, 'min', ['min' => $min]);
            }
        } elseif (is_array($value)) {
            if (count($value) < $min) {
                $this->addError($field, 'min', ['min' => $min]);
            }
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

        if (is_numeric($value)) {
            if ($value > $max) {
                $this->addError($field, 'max', ['max' => $max]);
            }
        } elseif (is_string($value)) {
            if (strlen($value) > $max) {
                $this->addError($field, 'max', ['max' => $max]);
            }
        } elseif (is_array($value)) {
            if (count($value) > $max) {
                $this->addError($field, 'max', ['max' => $max]);
            }
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

        $size = is_numeric($value) ? $value : (is_string($value) ? strlen($value) : count($value));

        if ($size < $min || $size > $max) {
            $this->addError($field, 'between', ['min' => $min, 'max' => $max]);
        }
    }

    /**
     * Numeric validation
     */
    private function validateNumeric(string $field, $value): void
    {
        if ($value !== null && $value !== '' && !is_numeric($value)) {
            $this->addError($field, 'numeric');
        }
    }

    /**
     * Integer validation
     */
    private function validateInteger(string $field, $value): void
    {
        if ($value !== null && $value !== '' && !filter_var($value, FILTER_VALIDATE_INT)) {
            $this->addError($field, 'integer');
        }
    }

    /**
     * String validation
     */
    private function validateString(string $field, $value): void
    {
        if ($value !== null && !is_string($value)) {
            $this->addError($field, 'string');
        }
    }

    /**
     * Array validation
     */
    private function validateArray(string $field, $value): void
    {
        if ($value !== null && !is_array($value)) {
            $this->addError($field, 'array');
        }
    }

    /**
     * Boolean validation
     */
    private function validateBoolean(string $field, $value): void
    {
        $acceptable = [true, false, 0, 1, '0', '1'];

        if ($value !== null && !in_array($value, $acceptable, true)) {
            $this->addError($field, 'boolean');
        }
    }

    /**
     * URL validation
     */
    private function validateUrl(string $field, $value): void
    {
        if ($value !== null && $value !== '' && !filter_var($value, FILTER_VALIDATE_URL)) {
            $this->addError($field, 'url');
        }
    }

    /**
     * Active URL validation (checks DNS)
     */
    private function validateActiveUrl(string $field, $value): void
    {
        if ($value === null || $value === '') {
            return;
        }

        $host = parse_url($value, PHP_URL_HOST);

        if (!$host || !checkdnsrr($host, 'A') && !checkdnsrr($host, 'AAAA')) {
            $this->addError($field, 'active_url');
        }
    }

    /**
     * Regex pattern validation
     */
    private function validateRegex(string $field, $value, array $params): void
    {
        $pattern = $params[0];

        if ($value !== null && $value !== '' && !preg_match($pattern, $value)) {
            $this->addError($field, 'regex');
        }
    }

    /**
     * Not regex pattern validation
     */
    private function validateNotRegex(string $field, $value, array $params): void
    {
        $pattern = $params[0];

        if ($value !== null && $value !== '' && preg_match($pattern, $value)) {
            $this->addError($field, 'not_regex');
        }
    }

    /**
     * Confirmed field validation
     */
    private function validateConfirmed(string $field, $value): void
    {
        $confirmField = $field . '_confirmation';
        $confirmValue = $this->getValue($confirmField);

        if ($value !== $confirmValue) {
            $this->addError($field, 'confirmed');
        }
    }

    /**
     * Same as another field
     */
    private function validateSame(string $field, $value, array $params): void
    {
        $otherField = $params[0];
        $otherValue = $this->getValue($otherField);

        if ($value !== $otherValue) {
            $this->addError($field, 'same', ['other' => $otherField]);
        }
    }

    /**
     * Different from another field
     */
    private function validateDifferent(string $field, $value, array $params): void
    {
        $otherField = $params[0];
        $otherValue = $this->getValue($otherField);

        if ($value === $otherValue) {
            $this->addError($field, 'different', ['other' => $otherField]);
        }
    }

    /**
     * In array validation
     */
    private function validateIn(string $field, $value, array $params): void
    {
        if ($value !== null && !in_array($value, $params)) {
            $this->addError($field, 'in', ['values' => implode(', ', $params)]);
        }
    }

    /**
     * Not in array validation
     */
    private function validateNotIn(string $field, $value, array $params): void
    {
        if ($value !== null && in_array($value, $params)) {
            $this->addError($field, 'not_in', ['values' => implode(', ', $params)]);
        }
    }

    /**
     * Alpha characters only
     */
    private function validateAlpha(string $field, $value): void
    {
        if ($value !== null && $value !== '' && !ctype_alpha($value)) {
            $this->addError($field, 'alpha');
        }
    }

    /**
     * Alphanumeric characters only
     */
    private function validateAlphaNum(string $field, $value): void
    {
        if ($value !== null && $value !== '' && !ctype_alnum($value)) {
            $this->addError($field, 'alpha_num');
        }
    }

    /**
     * Alpha, numeric, dash, and underscore
     */
    private function validateAlphaDash(string $field, $value): void
    {
        if ($value !== null && $value !== '' && !preg_match('/^[a-zA-Z0-9_-]+$/', $value)) {
            $this->addError($field, 'alpha_dash');
        }
    }

    /**
     * Date validation
     */
    private function validateDate(string $field, $value): void
    {
        if ($value === null || $value === '') {
            return;
        }

        if (strtotime($value) === false) {
            $this->addError($field, 'date');
        }
    }

    /**
     * Date format validation
     */
    private function validateDateFormat(string $field, $value, array $params): void
    {
        if ($value === null || $value === '') {
            return;
        }

        $format = $params[0];
        $date = \DateTime::createFromFormat($format, $value);

        if (!$date || $date->format($format) !== $value) {
            $this->addError($field, 'date_format', ['format' => $format]);
        }
    }

    /**
     * Date before another date
     */
    private function validateBefore(string $field, $value, array $params): void
    {
        if ($value === null || $value === '') {
            return;
        }

        $compareDate = $params[0];

        if (strtotime($value) >= strtotime($compareDate)) {
            $this->addError($field, 'before', ['date' => $compareDate]);
        }
    }

    /**
     * Date after another date
     */
    private function validateAfter(string $field, $value, array $params): void
    {
        if ($value === null || $value === '') {
            return;
        }

        $compareDate = $params[0];

        if (strtotime($value) <= strtotime($compareDate)) {
            $this->addError($field, 'after', ['date' => $compareDate]);
        }
    }

    /**
     * IP address validation
     */
    private function validateIp(string $field, $value): void
    {
        if ($value !== null && $value !== '' && !filter_var($value, FILTER_VALIDATE_IP)) {
            $this->addError($field, 'ip');
        }
    }

    /**
     * IPv4 validation
     */
    private function validateIpv4(string $field, $value): void
    {
        if ($value !== null && $value !== '' && !filter_var($value, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4)) {
            $this->addError($field, 'ipv4');
        }
    }

    /**
     * IPv6 validation
     */
    private function validateIpv6(string $field, $value): void
    {
        if ($value !== null && $value !== '' && !filter_var($value, FILTER_VALIDATE_IP, FILTER_FLAG_IPV6)) {
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

        json_decode($value);

        if (json_last_error() !== JSON_ERROR_NONE) {
            $this->addError($field, 'json');
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

        $pattern = '/^[0-9a-f]{8}-[0-9a-f]{4}-4[0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}$/i';

        if (!preg_match($pattern, $value)) {
            $this->addError($field, 'uuid');
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
            '/<embed/i'
        ];

        foreach ($dangerousPatterns as $pattern) {
            if (preg_match($pattern, (string) $value)) {
                $this->addError($field, 'safe_html');

                break;
            }
        }
    }
}
