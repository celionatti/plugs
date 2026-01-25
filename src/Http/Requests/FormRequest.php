<?php

declare(strict_types=1);

namespace Plugs\Http\Requests;

use Plugs\Security\Validator;
use Plugs\View\ErrorMessage;

/**
 * Class FormRequest
 *
 * Base class for form requests handling validation.
 * Users should extend this class and implement rules(), and optionally messages() and attributes().
 */
abstract class FormRequest
{
    protected ?Validator $validator = null;
    protected ErrorMessage $errors;
    protected array $data = [];

    /**
     * FormRequest constructor.
     *
     * @param array|null $data The data to validate
     */
    public function __construct(?array $data = [])
    {
        $this->data = $data ?? [];
        $this->errors = new ErrorMessage();

        $this->validator = new Validator(
            $this->data,
            $this->rules(),
            $this->messages(),
            $this->attributes()
        );
    }

    /**
     * Get validation rules.
     *
     * @return array
     */
    public function rules(): array
    {
        return [];
    }

    /**
     * Get custom error messages.
     *
     * @return array
     */
    public function messages(): array
    {
        return [];
    }

    /**
     * Get custom attribute names.
     *
     * @return array
     */
    public function attributes(): array
    {
        return [];
    }

    /**
     * Run validation.
     *
     * @return bool
     */
    public function validate(): bool
    {
        // Ensure validator is initialized if not already (redundant if ctor runs, but safe)
        if (!$this->validator) {
            return false;
        }

        if (!$this->validator->validate()) {
            $this->errors = $this->validator->errors();

            return false;
        }

        return true;
    }

    /**
     * Get validation errors.
     *
     * @return ErrorMessage
     */
    public function errors(): ErrorMessage
    {
        return $this->errors;
    }

    /**
     * Get validated data.
     *
     * @return array
     */
    public function validated(): array
    {
        return $this->validator ? $this->validator->validated() : [];
    }

    /**
     * Get sanitization rules.
     * Use rule names from Sanitizer class (string, int, float, email, url, safe_html).
     *
     * @return array
     */
    public function sanitizers(): array
    {
        return [];
    }

    /**
     * Get sanitized data.
     * Applies sanitization rules defined in sanitizers().
     *
     * @return array
     */
    public function sanitized(): array
    {
        $data = $this->validated();
        $rules = $this->sanitizers();

        foreach ($rules as $field => $method) {
            if (isset($data[$field])) {
                $data[$field] = \Plugs\Security\Sanitizer::$method($data[$field]);
            }
        }

        return $data;
    }

    /**
     * Get raw input data.
     *
     * @return array
     */
    public function raw(): array
    {
        return $this->data;
    }
}

