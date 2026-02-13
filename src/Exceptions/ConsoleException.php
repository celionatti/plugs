<?php

declare(strict_types=1);

namespace Plugs\Exceptions;

/*
|--------------------------------------------------------------------------
| Console Exception
|--------------------------------------------------------------------------
|
| Thrown when a CLI command encounters an error during registration or
| execution, such as invalid command classes or missing aliases.
*/

class ConsoleException extends PlugsException
{
    /**
     * The exit code for the console.
     *
     * @var int
     */
    protected int $exitCode = 1;

    /**
     * Create a new console exception.
     *
     * @param string $message
     * @param int $exitCode
     * @param \Throwable|null $previous
     */
    public function __construct(
        string $message = 'A console error occurred.',
        int $exitCode = 1,
        ?\Throwable $previous = null
    ) {
        $this->exitCode = $exitCode;
        parent::__construct($message, 0, $previous);
    }

    /**
     * Get the exit code.
     *
     * @return int
     */
    public function getExitCode(): int
    {
        return $this->exitCode;
    }

    /**
     * Create an exception for a missing command class.
     *
     * @param string $class
     * @return static
     */
    public static function classNotFound(string $class): static
    {
        return new static("Command class '{$class}' does not exist.");
    }

    /**
     * Create an exception for an invalid command class.
     *
     * @param string $class
     * @param string $expectedParent
     * @return static
     */
    public static function invalidClass(string $class, string $expectedParent): static
    {
        return new static("Command class must extend {$expectedParent}");
    }

    /**
     * Create an exception for an alias of a non-existent command.
     *
     * @param string $commandName
     * @return static
     */
    public static function aliasNotFound(string $commandName): static
    {
        return new static("Cannot alias non-existent command '{$commandName}'");
    }
}
