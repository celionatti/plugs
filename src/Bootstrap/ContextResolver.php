<?php

declare(strict_types=1);

namespace Plugs\Bootstrap;

use Plugs\Bootstrap\ContextType;

/**
 * Detects the execution context before kernel boot.
 *
 * Analyzes PHP SAPI, headers, subdomain, route prefix, and CLI argv
 * to determine the appropriate kernel for the current request.
 */
class ContextResolver
{
    /**
     * CLI commands that indicate a queue worker context.
     */
    private const QUEUE_COMMANDS = ['queue:work', 'queue:listen'];

    /**
     * CLI commands that indicate a realtime/WebSocket context.
     */
    private const REALTIME_COMMANDS = ['ws:serve', 'realtime:serve', 'websocket:serve'];

    /**
     * CLI commands that indicate a cron/scheduler context.
     */
    private const CRON_COMMANDS = ['schedule:run'];

    /**
     * Resolve the current execution context.
     *
     * Detection priority:
     * 1. PHP SAPI (cli → CLI/Queue/Cron context)
     * 2. Route prefix (/api → API context)
     * 3. Subdomain (api.xxx → API context)
     * 4. Content-Type header (application/json → API context)
     * 5. Accept header (application/json without text/html → API context)
     * 6. Default → Web context
     */
    public static function resolve(?array $server = null, ?array $argv = null): ContextType
    {
        $server = $server ?? $_SERVER;
        $argv = $argv ?? ($GLOBALS['argv'] ?? []);

        // 1. CLI SAPI detection
        if (self::isCli($server)) {
            return self::resolveCliContext($argv);
        }

        // 2. Route prefix detection
        if (self::hasApiRoutePrefix($server)) {
            return ContextType::Api;
        }

        // 3. Subdomain detection
        if (self::hasApiSubdomain($server)) {
            return ContextType::Api;
        }

        // 4. Content-Type header detection
        if (self::hasJsonContentType($server)) {
            return ContextType::Api;
        }

        // 5. Accept header detection
        if (self::prefersJson($server)) {
            return ContextType::Api;
        }

        // 6. Default to Web
        return ContextType::Web;
    }

    /**
     * Check if running in CLI SAPI.
     */
    private static function isCli(array $server): bool
    {
        $sapi = $server['SAPI_NAME'] ?? PHP_SAPI;

        return in_array($sapi, ['cli', 'phpdbg', 'cli-server'], true) && !isset($server['HTTP_HOST']);
    }

    /**
     * Resolve the specific CLI sub-context from argv.
     */
    private static function resolveCliContext(array $argv): ContextType
    {
        $command = $argv[1] ?? '';

        if (in_array($command, self::QUEUE_COMMANDS, true)) {
            return ContextType::Queue;
        }

        if (in_array($command, self::REALTIME_COMMANDS, true)) {
            return ContextType::Realtime;
        }

        if (in_array($command, self::CRON_COMMANDS, true)) {
            return ContextType::Cron;
        }

        return ContextType::Cli;
    }

    /**
     * Check if the request URI starts with /api.
     */
    private static function hasApiRoutePrefix(array $server): bool
    {
        $uri = $server['REQUEST_URI'] ?? '';
        $path = parse_url($uri, PHP_URL_PATH) ?: '';

        return str_starts_with(rtrim($path, '/'), '/api');
    }

    /**
     * Check if the request is coming from an api.* subdomain.
     */
    private static function hasApiSubdomain(array $server): bool
    {
        $host = $server['HTTP_HOST'] ?? $server['SERVER_NAME'] ?? '';

        // Remove port if present
        $host = strtolower(explode(':', $host)[0]);

        return str_starts_with($host, 'api.');
    }

    /**
     * Check if the Content-Type header indicates JSON.
     */
    private static function hasJsonContentType(array $server): bool
    {
        $contentType = $server['CONTENT_TYPE'] ?? $server['HTTP_CONTENT_TYPE'] ?? '';

        return stripos($contentType, 'application/json') !== false;
    }

    /**
     * Check if the Accept header prefers JSON over HTML.
     */
    private static function prefersJson(array $server): bool
    {
        $accept = $server['HTTP_ACCEPT'] ?? '';

        // Must explicitly request JSON WITHOUT also requesting HTML
        $wantsJson = stripos($accept, 'application/json') !== false;
        $wantsHtml = stripos($accept, 'text/html') !== false;

        return $wantsJson && !$wantsHtml;
    }

    /**
     * Quick check if the current context is HTTP-based (not CLI).
     */
    public static function isHttp(?array $server = null): bool
    {
        return self::resolve($server)->isHttp();
    }
}
