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
        // 0. Remove null bytes and control characters that can be used to hide protocols/attributes
        $html = preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/', '', $html);

        // 1. Handle common bypasses for URI-based attributes (href, src, etc.)
        $dangerousSchemes = ['javascript', 'data', 'vbscript', 'file', 'about', 'chrome'];
        
        foreach ($dangerousSchemes as $scheme) {
            // Match the scheme with optional whitespace/control characters/entities
            $chars = str_split($scheme);
            $schemePattern = '';
            foreach ($chars as $char) {
                $schemePattern .= preg_quote($char, '/') . '[\s\x00-\x1F\x7F]*';
            }

            // Also match common hex/decimal entities for characters and protocols
            // We match a sequence of entities or literal characters that could form the scheme
            // Example: &#106;&#97;v&#x61;script:
            $entityPattern = '(?:&#[0-9]+;?|&#[xX][a-fA-F0-9]+;?|&[a-zA-Z0-9]+;?)';
            $flexiblePattern = '(?:' . $schemePattern . '|' . $entityPattern . ')+';

            $pattern = '/(href|src|postaction|background|formaction|action|lowsrc|dynsrc)\s*=\s*(["\']?)\s*' . $flexiblePattern . '[\s\x00-\x1F\x7F]*:/i';
            $html = preg_replace($pattern, '$1=$2#', $html);
        }

        // 2. Remove ANY attribute starting with 'on' (event handlers)
        // Aggressive: catches onclick, onfocus, onmouseover, but also on- (for custom elements if used incorrectly)
        // Correctly handles single quotes, double quotes, and unquoted values
        $html = preg_replace('/\s+on[a-z0-9-]+\s*=\s*(["\'])(?:(?!\1).)*\1/is', ' ', $html);
        $html = preg_replace('/\s+on[a-z0-9-]+\s*=\s*[^\s>]+/is', ' ', $html);

        // 3. Remove dangerous attributes that don't start with 'on'
        $dangerousAttrs = ['formaction', 'postaction', 'autofocus', 'loading', 'ping', 'srcset', 'contenteditable'];
        foreach ($dangerousAttrs as $attr) {
            $html = preg_replace('/\s+' . $attr . '\s*=\s*(["\'])(?:(?!\1).)*\1/is', ' ', $html);
            $html = preg_replace('/\s+' . $attr . '\s*=\s*[^\s>]+/is', ' ', $html);
        }

        // 4. Remove style attributes that contain dangerous directives (expression, behavior, url, -moz-binding)
        // Also check for hidden "javascript:" inside style
        $html = preg_replace('/style\s*=\s*(["\'])(?:(?!\1).)*?(expression|behavior|url\s*\(|-moz-binding|javascript\s*:).*?\1/is', 'style="display:none"', $html);

        // 5. Remove dangerous tags COMPLETELY including their content
        $dangerousTags = ['meta', 'link', 'style', 'script', 'embed', 'object', 'iframe', 'frame', 'frameset', 'applet', 'video', 'audio', 'canvas', 'svg', 'math', 'base'];
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
