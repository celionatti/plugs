<?php

declare(strict_types=1);

namespace Plugs\Bootstrap;

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
}
