<?php

declare(strict_types=1);

namespace Plugs\Security;

/*
|--------------------------------------------------------------------------
| Sanitizer Class
|--------------------------------------------------------------------------
|
| This class provides methods to sanitize input data to prevent XSS and other
| security vulnerabilities.
*/

class Sanitizer
{
    public static function string($value): string
    {
        return htmlspecialchars(trim((string) $value), ENT_QUOTES, 'UTF-8');
    }

    public static function email($value): string
    {
        return filter_var(trim((string) $value), FILTER_SANITIZE_EMAIL);
    }

    public static function int($value): int
    {
        return (int) filter_var($value, FILTER_SANITIZE_NUMBER_INT);
    }

    public static function float($value): float
    {
        return (float) filter_var($value, FILTER_SANITIZE_NUMBER_FLOAT, FILTER_FLAG_ALLOW_FRACTION);
    }

    public static function url($value): string
    {
        return filter_var(trim((string) $value), FILTER_SANITIZE_URL);
    }

    public static function stripTags($value, ?string $allowedTags = null): string
    {
        return strip_tags((string) $value, $allowedTags);
    }

    /**
     * Sanitize HTML content by allowing safe tags and stripping dangerous attributes.
     * Useful for blog posts and rich text editors.
     */
    public static function safeHtml($value, ?array $allowedTags = null): string
    {
        if ($value === null || $value === '') {
            return '';
        }

        // Default allowed tags for blog content
        if ($allowedTags === null) {
            $allowedTags = [
                'p',
                'br',
                'b',
                'i',
                'u',
                'strong',
                'em',
                'span',
                'h1',
                'h2',
                'h3',
                'h4',
                'h5',
                'h6',
                'ul',
                'ol',
                'li',
                'blockquote',
                'code',
                'pre',
                'a',
                'img',
                'table',
                'thead',
                'tbody',
                'tr',
                'th',
                'td',
                'div',
                'hr',
            ];
        }

        $tagString = '<' . implode('><', $allowedTags) . '>';
        $content = strip_tags((string) $value, $tagString);

        return self::cleanAttributes($content);
    }

    /**
     * Strip dangerous attributes like onclick, script:, etc.
     */
    protected static function cleanAttributes(string $html): string
    {
        // Remove javascript: and data: URIs
        $html = preg_replace('/(href|src|background)\s*=\s*["\']\s*(javascript|data):/i', '$1="#', $html);

        // Remove event handlers (onmouseover, onclick, etc.)
        $html = preg_replace('/(\s)on[a-z]+\s*=\s*["\'][^"\']*["\']/i', '$1', $html);
        $html = preg_replace('/(\s)on[a-z]+\s*=\s*[^\s>]+/i', '$1', $html);

        // Remove <meta>, <link>, <style>, <script>, <embed>, <object>, <iframe> tags if somehow they slipped through
        $html = preg_replace('/<(meta|link|style|script|embed|object|iframe)[^>]*>.*?<\/\1>/is', '', $html);
        $html = preg_replace('/<(meta|link|style|script|embed|object|iframe)[^>]*>/is', '', $html);

        return $html;
    }

    public static function array(array $data, string $method = 'string'): array
    {
        return array_map([self::class, $method], $data);
    }
}
