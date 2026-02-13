<?php

declare(strict_types=1);

namespace Plugs\Exceptions;

/*
|--------------------------------------------------------------------------
| Binding Resolution Exception
|--------------------------------------------------------------------------
|
| Thrown when the dependency injection container cannot resolve a binding.
| This covers class-not-found, non-instantiable targets, and unresolvable
| primitive parameters.
*/

class BindingResolutionException extends PlugsException
{
    /**
     * The abstract type that failed to resolve.
     *
     * @var string
     */
    protected string $abstract = '';

    /**
     * Create a new binding resolution exception.
     *
     * @param string $message
     * @param string $abstract
     * @param \Throwable|null $previous
     */
    public function __construct(
        string $message = 'Unable to resolve binding.',
        string $abstract = '',
        ?\Throwable $previous = null
    ) {
        $this->abstract = $abstract;
        parent::__construct($message, 0, $previous);
    }

    /**
     * Get the abstract type that failed to resolve.
     *
     * @return string
     */
    public function getAbstract(): string
    {
        return $this->abstract;
    }

    /**
     * Create an exception for a missing class.
     *
     * @param string $class
     * @param \Throwable|null $previous
     * @return static
     */
    public static function targetNotFound(string $class, ?\Throwable $previous = null): static
    {
        return new static(
            "Target class [{$class}] does not exist.",
            $class,
            $previous
        );
    }

    /**
     * Create an exception for a non-instantiable class.
     *
     * @param string $class
     * @return static
     */
    public static function notInstantiable(string $class): static
    {
        return new static(
            "Target [{$class}] is not instantiable.",
            $class
        );
    }

    /**
     * Create an exception for an unresolvable primitive.
     *
     * @param string $parameter
     * @return static
     */
    public static function unresolvedPrimitive(string $parameter): static
    {
        return new static(
            "Cannot resolve primitive parameter [{$parameter}].",
            $parameter
        );
    }
}
