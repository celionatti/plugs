<?php

declare(strict_types=1);

use Plugs\Utils\Skeleton;

if (!function_exists('skeleton')) {
    /**
     * Get the skeleton loader instance or render styles.
     */
    function skeleton(): Skeleton
    {
        static $instance = null;
        if ($instance === null) {
            $instance = new Skeleton();
        }

        return $instance;
    }
}

if (!function_exists('skeleton_styles')) {
    /**
     * Render skeleton CSS styles.
     */
    function skeleton_styles(): string
    {
        return Skeleton::styles();
    }
}
