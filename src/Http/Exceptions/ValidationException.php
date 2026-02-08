<?php

declare(strict_types=1);

namespace Plugs\Http\Exceptions;

use Plugs\View\ErrorMessage;
use Psr\Http\Message\ServerRequestInterface;
use RuntimeException;

class ValidationException extends RuntimeException
{
    protected ErrorMessage $errors;
    protected ServerRequestInterface $request;

    public function __construct(ErrorMessage $errors, ServerRequestInterface $request)
    {
        parent::__construct("The given data was invalid.");
        $this->errors = $errors;
        $this->request = $request;
    }

    public function errors(): ErrorMessage
    {
        return $this->errors;
    }

    public function getRequest(): ServerRequestInterface
    {
        return $this->request;
    }
}
