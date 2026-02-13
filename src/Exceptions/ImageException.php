<?php

declare(strict_types=1);

namespace Plugs\Exceptions;

/*
|--------------------------------------------------------------------------
| Image Exception
|--------------------------------------------------------------------------
|
| Thrown when an image processing operation fails. Covers loading, resizing,
| cropping, saving, watermarking, and other GD-based operations.
*/

class ImageException extends PlugsException
{
    /**
     * The image path involved, if any.
     *
     * @var string
     */
    protected string $imagePath = '';

    /**
     * Create a new image exception.
     *
     * @param string $message
     * @param string $imagePath
     * @param \Throwable|null $previous
     */
    public function __construct(
        string $message = 'An image processing error occurred.',
        string $imagePath = '',
        ?\Throwable $previous = null
    ) {
        $this->imagePath = $imagePath;
        parent::__construct($message, 0, $previous);
    }

    /**
     * Get the image path.
     *
     * @return string
     */
    public function getImagePath(): string
    {
        return $this->imagePath;
    }

    /**
     * Create an exception for a missing image file.
     *
     * @param string $path
     * @return static
     */
    public static function fileNotFound(string $path): static
    {
        return new static("Image not found: {$path}", $path);
    }

    /**
     * Create an exception for a failed operation.
     *
     * @param string $operation
     * @param string $path
     * @return static
     */
    public static function operationFailed(string $operation, string $path = ''): static
    {
        return new static("Failed to {$operation} image.", $path);
    }

    /**
     * Create an exception for an unsupported image type.
     *
     * @param string $type
     * @param string $path
     * @return static
     */
    public static function unsupportedType(string $type, string $path = ''): static
    {
        return new static("Unsupported image type: {$type}", $path);
    }
}
