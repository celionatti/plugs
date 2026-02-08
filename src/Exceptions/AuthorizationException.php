<?php

declare(strict_types=1);

namespace Plugs\Exceptions;

/*
|--------------------------------------------------------------------------
| Authorization Exception
|--------------------------------------------------------------------------
|
| Thrown when a user is not authorized to perform an action.
*/

/**
 * @phpstan-consistent-constructor
 */
class AuthorizationException extends PlugsException
{
    /**
     * HTTP status code for authorization errors.
     *
     * @var int
     */
    protected int $statusCode = 403;

    /**
     * The response to be returned.
     *
     * @var mixed
     */
    protected mixed $response = null;

    /**
     * Create a new authorization exception.
     *
     * @param string $message
     * @param mixed $response
     */
    public function __construct(
        string $message = 'This action is unauthorized.',
        mixed $response = null
    ) {
        parent::__construct($message);
        $this->response = $response;
    }

    /**
     * Get the response from the exception.
     *
     * @return mixed
     */
    public function response(): mixed
    {
        return $this->response;
    }

    /**
     * Set the response from the exception.
     *
     * @param mixed $response
     * @return static
     */
    public function withResponse(mixed $response): static
    {
        $this->response = $response;

        return $this;
    }

    /**
     * Create a new authorization exception for denying a specific ability.
     *
     * @param string $ability
     * @return static
     */
    public static function forAbility(string $ability): static
    {
        return new static("You do not have permission to {$ability}.");
    }
}
