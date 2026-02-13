<?php

declare(strict_types=1);

namespace Plugs\Exceptions;

/*
|--------------------------------------------------------------------------
| Filesystem Exception
|--------------------------------------------------------------------------
|
| Thrown when a file or directory operation fails. This covers missing files,
| unwritable directories, and failed read/write operations.
*/

class FilesystemException extends PlugsException
{
    /**
     * The path that caused the error.
     *
     * @var string
     */
    protected string $path = '';

    /**
     * Create a new filesystem exception.
     *
     * @param string $message
     * @param string $path
     * @param \Throwable|null $previous
     */
    public function __construct(
        string $message = 'A filesystem error occurred.',
        string $path = '',
        ?\Throwable $previous = null
    ) {
        $this->path = $path;
        parent::__construct($message, 0, $previous);
    }

    /**
     * Get the path that caused the error.
     *
     * @return string
     */
    public function getPath(): string
    {
        return $this->path;
    }

    /**
     * Create an exception for a missing file.
     *
     * @param string $path
     * @return static
     */
    public static function fileNotFound(string $path): static
    {
        return new static("File not found: {$path}", $path);
    }

    /**
     * Create an exception for a failed write operation.
     *
     * @param string $path
     * @return static
     */
    public static function writeFailed(string $path): static
    {
        return new static("Failed to write file: {$path}", $path);
    }

    /**
     * Create an exception for a failed directory creation.
     *
     * @param string $path
     * @return static
     */
    public static function directoryCreateFailed(string $path): static
    {
        return new static("Failed to create directory: {$path}", $path);
    }
}
