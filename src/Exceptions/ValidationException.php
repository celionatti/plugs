<?php

declare(strict_types=1);

namespace Plugs\Exceptions;

/*
|--------------------------------------------------------------------------
| Validation Exception
|--------------------------------------------------------------------------
|
| Thrown when validation fails. Contains the validation errors.
*/

class ValidationException extends PlugsException
{
    /**
     * The validation errors.
     *
     * @var array
     */
    protected array $errors = [];

    /**
     * The recommended response to send to the client.
     *
     * @var string|null
     */
    protected ?string $redirectTo = null;

    /**
     * HTTP status code for validation errors.
     *
     * @var int
     */
    protected int $statusCode = 422;

    /**
     * Create a new validation exception.
     *
     * @param array $errors
     * @param string $message
     */
    public function __construct(array $errors = [], string $message = 'The given data was invalid.')
    {
        parent::__construct($message);
        $this->errors = $errors;
    }

    /**
     * Create a new validation exception from a validator instance.
     *
     * @param mixed $validator
     * @return static
     */
    public static function withMessages(array $messages): static
    {
        return new static($messages);
    }

    /**
     * Get the validation errors.
     *
     * @return array
     */
    public function errors(): array
    {
        return $this->errors;
    }

    /**
     * Set the URL to redirect to on validation error.
     *
     * @param string $url
     * @return static
     */
    public function redirectTo(string $url): static
    {
        $this->redirectTo = $url;
        return $this;
    }

    /**
     * Get the redirect URL.
     *
     * @return string|null
     */
    public function getRedirectTo(): ?string
    {
        return $this->redirectTo;
    }

    /**
     * Convert the exception to an array.
     *
     * @return array
     */
    public function toArray(): array
    {
        return [
            'message' => $this->getMessage(),
            'errors' => $this->errors,
        ];
    }
}
