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
     * Escape for HTML body content. Deeply escapes arrays and object properties.
     * 
     * @param mixed $value
     * @param bool $doubleEncode
     * @return mixed
     */
    public static function html(mixed $value, bool $doubleEncode = true): mixed
    {
        if (is_array($value)) {
            $escaped = [];
            foreach ($value as $key => $val) {
                $escaped[$key] = self::html($val, $doubleEncode);
            }
            return $escaped;
        }

        if (is_object($value)) {
            if (method_exists($value, '__toString')) {
                return htmlspecialchars((string) $value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8', $doubleEncode);
            }
            if (method_exists($value, 'toArray')) {
                return self::html($value->toArray(), $doubleEncode);
            }
            if ($value instanceof \JsonSerializable) {
                return self::html($value->jsonSerialize(), $doubleEncode);
            }
            return $value;
        }

        if ($value === null) {
            return '';
        }

        return htmlspecialchars((string) $value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8', $doubleEncode);
    }

    /**
     * Escape for HTML attributes. Deeply escapes arrays and object properties.
     * 
     * @param mixed $value
     * @param bool $doubleEncode
     * @return mixed
     */
    public static function attr(mixed $value, bool $doubleEncode = true): mixed
    {
        if (is_array($value)) {
            $escaped = [];
            foreach ($value as $key => $val) {
                $escaped[$key] = self::attr($val, $doubleEncode);
            }
            return $escaped;
        }

        if (is_object($value)) {
            if (method_exists($value, '__toString')) {
                return htmlspecialchars((string) $value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8', $doubleEncode);
            }
            if (method_exists($value, 'toArray')) {
                return self::attr($value->toArray(), $doubleEncode);
            }
            if ($value instanceof \JsonSerializable) {
                return self::attr($value->jsonSerialize(), $doubleEncode);
            }
            return $value;
        }

        if (empty($value) && $value !== '0' && $value !== 0) {
            return '';
        }

        return htmlspecialchars((string) $value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8', $doubleEncode);
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
    /**
     * Escape for CSS context (style attributes).
     * Blocks dangerous CSS values that could enable CSS injection.
     * 
     * @param mixed $value
     * @return string
     */
    public static function css(mixed $value): string
    {
        $value = (string) $value;

        if ($value === '') {
            return '';
        }

        // Check for dangerous CSS keywords (case-insensitive)
        $dangerous = ['expression', 'javascript', 'vbscript', 'url(', 'import', 'behavior', 'binding', '-moz-binding'];
        $clean = strtolower(trim($value));

        foreach ($dangerous as $keyword) {
            if (str_contains($clean, $keyword)) {
                return ''; // Neutralize dangerous CSS
            }
        }

        // Strip characters that could break out of CSS context
        return preg_replace('/[^a-zA-Z0-9\s#.,;:%()\-_\/\'"!+*>~\[\]=]/', '', $value);
    }

    /**
     * Escape a value for use as an HTML element ID.
     * Strips all characters except alphanumeric, hyphens, and underscores.
     * 
     * @param mixed $value
     * @return string
     */
    public static function id(mixed $value): string
    {
        return preg_replace('/[^a-zA-Z0-9_-]/', '', (string) $value);
    }

    /**
     * Escape for JSON context.
     * 
     * @param mixed $value
     * @return string
     */
    public static function json(mixed $value): string
    {
        return self::js($value);
    }
}
