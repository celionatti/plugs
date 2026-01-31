<?php

declare(strict_types=1);

namespace Plugs\Exceptions;

/*
|--------------------------------------------------------------------------
| Authentication Exception
|--------------------------------------------------------------------------
|
| Thrown when authentication is required but not provided or is invalid.
*/

class AuthenticationException extends PlugsException
{
    /**
     * HTTP status code for authentication errors.
     *
     * @var int
     */
    protected int $statusCode = 401;

    /**
     * The path the user should be redirected to.
     *
     * @var string|null
     */
    protected ?string $redirectTo = null;

    /**
     * The guards that were checked.
     *
     * @var array
     */
    protected array $guards = [];

    /**
     * Create a new authentication exception.
     *
     * @param string $message
     * @param array $guards
     * @param string|null $redirectTo
     */
    public function __construct(
        string $message = 'Unauthenticated.',
        array $guards = [],
        ?string $redirectTo = null
    ) {
        parent::__construct($message);
        $this->guards = $guards;
        $this->redirectTo = $redirectTo;
    }

    /**
     * Get the guards that were checked.
     *
     * @return array
     */
    public function guards(): array
    {
        return $this->guards;
    }

    /**
     * Get the path the user should be redirected to.
     *
     * @return string|null
     */
    public function redirectTo(): ?string
    {
        return $this->redirectTo;
    }
}
