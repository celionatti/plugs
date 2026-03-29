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
        
        // Clean attributes and dangerous tags WITH their content FIRST
        $content = self::cleanAttributes((string) $value);
        
        // Then strip remaining disallowed tags
        return strip_tags($content, $tagString);
    }

    /**
     * Sanitize a file path by removing dangerous characters and traversal attempts.
     */
    public static function path(string $path): string
    {
        // Remove null bytes and control characters
        $path = preg_replace('/[\x00-\x1F\x7F]/', '', $path);

        // Normalize separators
        $path = str_replace(['/', '\\'], DIRECTORY_SEPARATOR, $path);

        // Remove multiple consecutive separators
        $path = preg_replace('/' . preg_quote(DIRECTORY_SEPARATOR, '/') . '{2,}/', DIRECTORY_SEPARATOR, $path);

        // Robust protection against traversal: split, filter, and rejoin
        // We do this in a loop to handle cases like "....//"
        $parts = explode(DIRECTORY_SEPARATOR, $path);

        do {
            $count = count($parts);
            $safeParts = [];
            foreach ($parts as $part) {
                if ($part === '.' || $part === '..' || $part === '') {
                    continue;
                }
                $safeParts[] = $part;
            }
            $parts = $safeParts;
        } while (count($parts) !== $count && !empty($parts));

        return implode(DIRECTORY_SEPARATOR, $parts);
    }

    /**
     * Strip dangerous attributes like onclick, script:, etc.
     */
    protected static function cleanAttributes(string $html): string
    {
        // 1. Handle common bypasses for URI-based attributes (href, src, etc.)
        $dangerousSchemes = ['javascript', 'data', 'vbscript', 'file'];
        
        foreach ($dangerousSchemes as $scheme) {
            // Regex explanation:
            // Match the attribute name (href, etc.)
            // Match = and optional quotes
            // Match the scheme with optional whitespace/control characters/entities (&#x0A; etc.)
            // This is complex, but we try to catch things like: j  a v&#x0A;ascript:
            $chars = str_split($scheme);
            $schemePattern = '';
            foreach ($chars as $char) {
                $schemePattern .= preg_quote($char, '/') . '[\s\x00-\x1F\x7F]*';
            }

            // Also match common hex/decimal entities for ':', '/', etc.
            $pattern = '/(href|src|postaction|background|formaction|action)\s*=\s*["\']\s*(' . $schemePattern . '|&[#x][a-f0-9]+;[^"\']*):/i';
            $html = preg_replace($pattern, '$1="#', $html);
        }

        // 2. Remove ANY attribute starting with 'on' (event handlers)
        // This is more aggressive and catches everything: onclick, onfocus, onmouseover...
        // We match attributes like onmouseover="...", onfocus='...', or even onfocus=alert(1)
        $html = preg_replace('/\s+on[a-z]+\s*=\s*(["\'])(?:(?!\1).)*\1/i', ' ', $html);
        $html = preg_replace('/\s+on[a-z]+\s*=\s*[^\s>]+/i', ' ', $html);

        // 3. Remove dangerous attributes that don't start with 'on'
        $dangerousAttrs = ['formaction', 'postaction', 'autofocus', 'loading'];
        foreach ($dangerousAttrs as $attr) {
            $html = preg_replace('/\s+' . $attr . '\s*=\s*(["\'])(?:(?!\1).)*\1/i', ' ', $html);
            $html = preg_replace('/\s+' . $attr . '\s*=\s*[^\s>]+/i', ' ', $html);
        }

        // 4. Remove style attributes that contain dangerous directives (expression, behavior, url, -moz-binding)
        $html = preg_replace('/style\s*=\s*(["\'])(?:(?!\1).)*?(expression|behavior|url\s*\(|-moz-binding).*?\1/is', 'style="display:none"', $html);

        // 5. Remove dangerous tags COMPLETELY including their content
        // This is a fail-safe for when they are NOT in the whitelist but somehow passed through.
        $dangerousTags = ['meta', 'link', 'style', 'script', 'embed', 'object', 'iframe', 'frame', 'frameset', 'applet', 'video', 'audio', 'canvas', 'svg', 'math'];
        $tagPattern = '/<(' . implode('|', $dangerousTags) . ')\b[^>]*>.*?<\/\1>/is';
        $html = preg_replace($tagPattern, '', $html);
        
        // Catch self-closing dangerous tags
        $selfClosingPattern = '/<(' . implode('|', $dangerousTags) . ')\b[^>]*\/?>/is';
        $html = preg_replace($selfClosingPattern, '', $html);

        return $html;
    }

    public static function array(array $data, string $method = 'string'): array
    {
        return array_map([self::class, $method], $data);
    }
}
