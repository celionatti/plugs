<?php

declare(strict_types=1);

namespace Plugs\Http\Resources;

/**
 * MissingValue
 * 
 * Marker class for conditional values that should be excluded from resource output.
 * Used internally by PlugResource::when() and PlugResource::whenLoaded().
 * 
 * @package Plugs\Http\Resources
 */
class MissingValue
{
    /**
     * Check if a value is a MissingValue instance
     */
    public static function isMissing(mixed $value): bool
    {
        return $value instanceof self;
    }
}
