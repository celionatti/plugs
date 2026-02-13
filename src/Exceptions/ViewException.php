<?php

declare(strict_types=1);

namespace Plugs\Exceptions;

/**
 * Exception thrown by the view system.
 */
class ViewException extends PlugsException
{
    public const VIEW_NOT_FOUND = 'PLUGS-VIEW-001';
    public const COMPONENT_NOT_FOUND = 'PLUGS-VIEW-002';
    public const COMPILATION_ERROR = 'PLUGS-VIEW-003';
    public const RUNTIME_ERROR = 'PLUGS-VIEW-004';
    public const INVALID_PATH = 'PLUGS-VIEW-005';

    protected ?string $view = null;
    protected ?string $frameworkCode = null;

    public function __construct(
        string $message = '',
        int $code = 0,
        ?\Throwable $previous = null,
        ?string $view = null,
        ?string $frameworkCode = null,
        array $context = []
    ) {
        $this->view = $view;
        $this->frameworkCode = $frameworkCode;

        if ($frameworkCode && $message !== '') {
            $message = "[{$frameworkCode}] {$message}";
        }

        parent::__construct($message, $code, $previous, $context);
    }

    public function getView(): ?string
    {
        return $this->view;
    }

    public function getFrameworkCode(): ?string
    {
        return $this->frameworkCode;
    }
}
