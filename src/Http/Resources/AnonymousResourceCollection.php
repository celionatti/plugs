<?php

declare(strict_types=1);

namespace Plugs\Http\Resources;

use Plugs\Database\Collection;

/**
 * AnonymousResourceCollection
 * 
 * A resource collection used when calling PlugResource::collection().
 * Allows creating collections without a dedicated collection class.
 * 
 * @package Plugs\Http\Resources
 */
class AnonymousResourceCollection extends PlugResourceCollection
{
    /**
     * Create a new anonymous resource collection
     */
    public function __construct(mixed $resource, ?string $collects = null)
    {
        parent::__construct($resource, $collects);
    }
}
