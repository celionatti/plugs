<?php

declare(strict_types=1);

namespace Plugs\Http\Middleware;

use ReflectionClass;
use Plugs\Http\Middleware\Middleware;

/**
 * Utility to extract metadata from middleware classes.
 */
class MiddlewareMetadata
{
    /**
     * Extract layer and priority from a middleware class.
     * 
     * Priority order:
     * 1. PHP 8 Attribute #[Middleware]
     * 2. Public properties $layer, $priority
     * 3. Defaults (business, 500)
     */
    public static function extract(string|object $middleware): array
    {
        $class = is_object($middleware) ? get_class($middleware) : $middleware;

        if (!class_exists($class)) {
            return [
                'layer' => MiddlewareLayer::BUSINESS,
                'priority' => 500
            ];
        }

        $reflection = new ReflectionClass($class);

        // 1. Check for PHP 8 Attribute
        $attributes = $reflection->getAttributes(Middleware::class);
        if (!empty($attributes)) {
            $attr = $attributes[0]->newInstance();
            return [
                'layer' => $attr->layer instanceof MiddlewareLayer ? $attr->layer : MiddlewareLayer::tryFrom($attr->layer) ?? MiddlewareLayer::BUSINESS,
                'priority' => $attr->priority
            ];
        }

        // 2. Check for public properties (legacy/flexible support)
        $defaultProperties = $reflection->getDefaultProperties();
        $layer = $defaultProperties['layer'] ?? 'business';
        $priority = $defaultProperties['priority'] ?? 500;

        return [
            'layer' => MiddlewareLayer::tryFrom($layer) ?? MiddlewareLayer::BUSINESS,
            'priority' => (int) $priority
        ];
    }
}
