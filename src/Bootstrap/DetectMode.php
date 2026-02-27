<?php

declare(strict_types=1);

namespace Plugs\Bootstrap;

use Plugs\Bootstrap\ContextType;
use Plugs\Bootstrap\ContextResolver;

/**
 * Detect the execution mode of the application.
 *
 * This class is now integrated with the ContextResolver system.
 * Legacy isAsync() method is preserved for backward compatibility.
 */
class DetectMode
{
    /**
     * Determine if the application is running in async mode.
     */
    public static function isAsync(): bool
    {
        // 1. Check if we're running in Swoole coroutine context
        if (extension_loaded('swoole') && class_exists('\Swoole\Coroutine') && \Swoole\Coroutine::getuid() > 0) {
            return true;
        }

        // 2. Check if we're running inside a ReactPHP or general loop-driven command
        if (defined('PLUGS_ASYNC_MODE') && PLUGS_ASYNC_MODE === true) {
            return true;
        }

        // 3. Check for specific CLI flags that trigger async mode
        if (PHP_SAPI === 'cli') {
            global $argv;
            if (isset($argv) && (in_array('--async', $argv) || in_array('serve', $argv))) {
                return true;
            }
        }

        return false;
    }

    /**
     * Get the current execution mode name.
     */
    public static function getMode(): string
    {
        return self::isAsync() ? 'async' : 'sync';
    }

    /**
     * Get the resolved context type from the ContextResolver.
     */
    public static function getContext(?array $server = null): ContextType
    {
        return ContextResolver::resolve($server);
    }

    /**
     * Check if running in a specific context.
     */
    public static function isContext(ContextType $expected, ?array $server = null): bool
    {
        return self::getContext($server) === $expected;
    }

    /**
     * Check if the current context is HTTP-based.
     */
    public static function isHttp(?array $server = null): bool
    {
        return self::getContext($server)->isHttp();
    }

    /**
     * Check if the current context is CLI-based.
     */
    public static function isCli(): bool
    {
        return in_array(PHP_SAPI, ['cli', 'phpdbg'], true);
    }
}
