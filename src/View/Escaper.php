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

        return $json;
    }

    /**
     * Escape for URL query parameter context.
     * 
     * @param mixed $value
     * @return string
     */
    public static function query(mixed $value): string
    {
        return urlencode((string) $value);
    }

    /**
     * Provide protocol-safe URL escaping for href/src attributes.
     * Rejects javascript:, data: (non-image), etc.
     * 
     * @param mixed $value
     * @return string
     */
    public static function safeUrl(mixed $value): string
    {
        $value = (string) $value;

        // Basic protocol sanitization
        $dangerProtocols = ['javascript:', 'data:', 'vbscript:', 'file:'];
        $cleanValue = strtolower(trim($value));

        foreach ($dangerProtocols as $protocol) {
            if (str_starts_with($cleanValue, $protocol)) {
                // If it's a data URL, we only allow images
                if ($protocol === 'data:' && str_starts_with($cleanValue, 'data:image/')) {
                    continue;
                }
                return '#'; // Neutralize dangerous links
            }
        }

        return self::attr($value);
    }

    /**
     * Escape for JSON context (proxy to js for safe injection).
     * 
     * @param mixed $value
     * @return string
     */
    public static function json(mixed $value): string
    {
        return self::js($value);
    }
}
