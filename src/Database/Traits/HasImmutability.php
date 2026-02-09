<?php

declare(strict_types=1);

namespace Plugs\Database\Traits;

use Plugs\Database\Attributes\Immutable;
use Plugs\Database\Exceptions\ImmutableModelException;
use ReflectionClass;

trait HasImmutability
{
    public static function bootHasImmutability(): void
    {
        $reflection = new ReflectionClass(static::class);
        $attributes = $reflection->getAttributes(Immutable::class);

        if (empty($attributes)) {
            return;
        }

        static::updating(function ($model) {
            if ($model->exists()) {
                throw new ImmutableModelException("Model [" . static::class . "] is immutable and cannot be updated.");
            }
        });

        static::deleting(function ($model) {
            if ($model->exists()) {
                throw new ImmutableModelException("Model [" . static::class . "] is immutable and cannot be deleted.");
            }
        });
    }
}
