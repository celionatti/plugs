<?php

declare(strict_types=1);

namespace Plugs\Bootstrap;

/**
 * Represents the detected execution context of the application.
 *
 * Used by the ContextResolver to determine which kernel
 * should handle the current request lifecycle.
 */
enum ContextType: string
{
    case Web = 'web';
    case Api = 'api';
    case Cli = 'cli';
    case Queue = 'queue';
    case Realtime = 'realtime';
    case Cron = 'cron';

    /**
     * Whether this context operates over HTTP.
     */
    public function isHttp(): bool
    {
        return match ($this) {
            self::Web, self::Api, self::Realtime => true,
            default => false,
        };
    }

    /**
     * Whether this context needs session support.
     */
    public function needsSession(): bool
    {
        return $this === self::Web;
    }

    /**
     * Whether this context needs CSRF protection.
     */
    public function needsCsrf(): bool
    {
        return $this === self::Web;
    }

    /**
     * Whether this context needs the view/template engine.
     */
    public function needsViews(): bool
    {
        return $this === self::Web;
    }

    /**
     * Whether this context needs routing.
     */
    public function needsRouting(): bool
    {
        return match ($this) {
            self::Web, self::Api, self::Realtime => true,
            default => false,
        };
    }
}
