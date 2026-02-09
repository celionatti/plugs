<?php

declare(strict_types=1);

namespace Plugs\Database\Exception;

use Plugs\Exceptions\PlugsException;

/**
 * Exception thrown when a relationship is lazy-loaded while lazy loading is disabled.
 */
class LazyLoadingDisabledException extends PlugsException
{
    /**
     * Create a new exception instance.
     *
     * @param string $model The model class name.
     * @param string $relation The relationship name.
     */
    public function __construct(string $model, string $relation)
    {
        parent::__construct("Lazy loading relationship [{$relation}] on model [{$model}] is disabled.");
    }
}
