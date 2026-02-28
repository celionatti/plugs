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
        $nonce = null;
        try {
            $container = \Plugs\Container\Container::getInstance();
            if ($container && $container->bound('view')) {
                $viewEngine = $container->make('view');
                if (method_exists($viewEngine, 'getCspNonce')) {
                    $nonce = $viewEngine->getCspNonce();
                }
            }
        } catch (\Throwable $e) {
            // Silently fail — nonce is optional
        }
        return Skeleton::styles($nonce);
    }
}
