<?php

declare(strict_types=1);

namespace Plugs\Http\Middleware;

/**
 * Defines the standard layers for middleware execution.
 */
enum MiddlewareLayer: string
{
    case SECURITY = 'security';
    case PERFORMANCE = 'performance';
    case BUSINESS = 'business';
    case FINALIZATION = 'finalization';

    /**
     * Get the default execution order for layers.
     */
    public static function getOrder(): array
    {
        return [
            self::SECURITY,
            self::PERFORMANCE,
            self::BUSINESS,
            self::FINALIZATION,
        ];
    }
}
