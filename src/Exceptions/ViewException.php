<?php

declare(strict_types=1);

namespace Plugs\Exceptions;

/**
 * Exception thrown by the view system.
 */
class ViewException extends PlugsException
{
    protected ?string $view = null;

    public function __construct(string $message, int $code = 0, ?\Throwable $previous = null, ?string $view = null)
    {
        parent::__construct($message, $code, $previous);
        $this->view = $view;
    }

    public function getView(): ?string
    {
        return $this->view;
    }
}
