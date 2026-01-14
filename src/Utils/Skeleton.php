<?php

declare(strict_types=1);

namespace Plugs\Utils;

/**
 * Skeleton Loader Utility
 * 
 * Provides methods to generate skeleton loading placeholders.
 */
class Skeleton
{
    /**
     * Render a skeleton box.
     */
    public function box(string $width = '100%', string $height = '16px', string $borderRadius = '4px'): string
    {
        return sprintf(
            '<div class="plugs-skeleton" style="width: %s; height: %s; border-radius: %s;"></div>',
            $width,
            $height,
            $borderRadius
        );
    }

    /**
     * Render a skeleton circle (e.g., for avatars).
     */
    public function circle(string $size = '50px'): string
    {
        return $this->box($size, $size, '50%');
    }

    /**
     * Render a skeleton text line.
     */
    public function text(string $width = '80%'): string
    {
        return $this->box($width, '14px', '3px');
    }

    /**
     * Get the CSS styles required for skeleton animation.
     */
    public static function styles(): string
    {
        return '
        <style>
            .plugs-skeleton {
                background: #e2e8f0;
                background-image: linear-gradient(
                    90deg, 
                    rgba(255, 255, 255, 0) 0, 
                    rgba(255, 255, 255, 0.2) 20%, 
                    rgba(255, 255, 255, 0.5) 60%, 
                    rgba(255, 255, 255, 0)
                );
                background-size: 200% 100%;
                animation: plugs-shimmer 1.5s infinite;
                display: inline-block;
                vertical-align: middle;
            }

            @keyframes plugs-shimmer {
                0% { background-position: 200% 0; }
                100% { background-position: -200% 0; }
            }

            [data-theme="dark"] .plugs-skeleton {
                background: #334155;
                background-image: linear-gradient(
                    90deg, 
                    rgba(255, 255, 255, 0) 0, 
                    rgba(255, 255, 255, 0.05) 20%, 
                    rgba(255, 255, 255, 0.1) 60%, 
                    rgba(255, 255, 255, 0)
                );
            }
        </style>';
    }
}
