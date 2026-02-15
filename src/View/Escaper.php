<?php

declare(strict_types=1);

namespace Plugs\View;

/**
 * Escaper Class
 * 
 * Provides context-aware escaping for different parts of an HTML document.
 * Follows security-first principles to prevent XSS.
 * 
 * @package Plugs\View
 */
class Escaper
{
    /**
     * Escape for HTML body content.
     * 
     * @param mixed $value
     * @return string
     */
    public static function html(mixed $value): string
    {
        return htmlspecialchars((string) $value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    }

    /**
     * Escape for HTML attributes.
     * 
     * @param mixed $value
     * @return string
     */
    public static function attr(mixed $value): string
    {
        if (empty($value) && $value !== '0' && $value !== 0) {
            return '';
        }

        return htmlspecialchars((string) $value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    }

    /**
     * Escape for JavaScript context.
     * 
     * Encodes value as JSON and ensures it's safe to inject into a <script> tag.
     * 
     * @param mixed $value
     * @return string
     */
    public static function js(mixed $value): string
    {
        $json = json_encode($value, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_THROW_ON_ERROR);

        // Ensure it's treated as a string/value in JS
        return $json;
    }
}
