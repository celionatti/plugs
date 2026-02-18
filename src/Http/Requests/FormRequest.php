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
    protected ?\Psr\Http\Message\ServerRequestInterface $request = null;
    protected ?Validator $validator = null;
    protected ErrorMessage $errors;
    protected array $data = [];

    /**
     * Set the current request.
     */
    public function setRequest(\Psr\Http\Message\ServerRequestInterface $request): self
    {
        $this->request = $request;
        $this->data = array_merge($request->getParsedBody() ?: [], $request->getQueryParams());

        $this->validator = new Validator(
            $this->data,
            $this->rules(),
            $this->messages(),
            $this->attributes()
        );

        return $this;
    }

    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Run validation and authorization.
     *
     * @throws \Plugs\Http\Exceptions\ValidationException
     */
    public function validateInternal(): void
    {
        if (!$this->authorize()) {
            throw new \Plugs\Exceptions\AuthorizationException("This action is unauthorized.");
        }

        if (!$this->validate()) {
            throw new \Plugs\Exceptions\ValidationException($this->errors()->toArray());
        }
    }

    /**
     * FormRequest constructor.
     */
    public function __construct(array $data = [])
    {
        $this->data = $data;
        $this->errors = new ErrorMessage();

        if (!empty($data)) {
            $this->validator = new Validator(
                $this->data,
                $this->rules(),
                $this->messages(),
                $this->attributes()
            );
        }
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

    /**
     * Send a quick prompt to the AI using request data for context.
     */
    public function aiPrompt(string $prompt, array $options = []): string
    {
        $context = json_encode($this->all());
        return ai()->prompt("Request Context: {$context}\n\nTask: {$prompt}", [], $options);
    }

    /**
     * Analyze request data using AI and return structured feedback.
     */
    public function aiAnalyze(string $instruction = "Analyze this request for potential issues or optimizations."): array
    {
        $context = json_encode($this->all());
        $response = ai()->prompt("Request Data: {$context}\n\nTask: {$instruction}. Return ONLY a JSON object of results.", [], ['max_tokens' => 500]);

        if (preg_match('/\{[\s\S]*\}/', $response, $matches)) {
            return json_decode($matches[0], true) ?: [];
        }

        return ['raw_response' => $response];
    }

    /**
     * Get all input data.
     */
    protected function all(): array
    {
        return $this->data;
    }
}
