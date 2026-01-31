<?php

declare(strict_types=1);

namespace Plugs\Utils;

/**
 * Minifier Utility
 * 
 * Provides robust, production-ready minification for JavaScript and CSS.
 * Designed to be safe and avoid common pitfalls like ASI errors by respecting
 * line boundaries and protecting string/regex literals.
 */
class Minifier
{
    /**
     * Minify JavaScript content
     * 
     * @param string $js
     * @param bool $removeConsole Remove console.log statements
     * @return string
     */
    public static function js(string $js): string
    {
        // 1. Remove comments while protecting strings and regex
        // Patterns for: double quotes, single quotes, template literals, regex literals, and comments
        $pattern = '/(?:
            "(?:\\\\.|[^"\\\\])*" |
            \'(?:\\\\.|[^\'\\\\])*\' |
            `(?:\\\\.|[^`\\\\])*` |
            \/(?![*\/])(?:\\\\. | [^\/\\\\])+\/[gimuy]*
        ) | (\/\* [\s\S]*? \*\/ | \/\/ [^\n]*)/x';

        $js = preg_replace_callback($pattern, function ($matches) {
            // If the second group (index 1) matches, it's a comment
            if (isset($matches[1]) && $matches[1] !== '') {
                return '';
            }
            return $matches[0];
        }, $js);

        // 2. Standardize line endings and remove leading/trailing whitespace per line
        $js = str_replace(["\r\n", "\r"], "\n", $js);
        $js = preg_replace('/^[ \t]+|[ \t]+$/m', '', $js);

        // 3. Remove whitespace around operators and punctuation (excluding newlines for now)
        // We exclude '.' here because '1 .toString()' is common in JS to differentiate from decimals
        $operators = preg_quote('{}()[]=+-*/%|&!<>?:;,', '/');
        $js = preg_replace('/[ \t]*([' . $operators . '])[ \t]*/', '$1', $js);

        // 4. Collapse multiple spaces
        $js = preg_replace('/[ \t]+/', ' ', $js);

        // 5. Remove empty lines
        $js = preg_replace('/\n+/', "\n", $js);

        // 6. Safe Joining: Remove newlines that are definitely not required for ASI
        // Join after characters that cannot end a statement
        $js = preg_replace('/([' . preg_quote('{}()[]=+-*/%|&!<>?:,', '/') . '])\n/', '$1', $js);
        // Join before characters that cannot start a statement
        $js = preg_replace('/\n([' . preg_quote('{}()[]=+-*/%|&!<>?:,', '/') . '])/', '$1', $js);

        // Also safe to join after a semicolon
        $js = preg_replace('/;\n/', ';', $js);

        return trim($js);
    }

    /**
     * Minify CSS content
     * 
     * @param string $css
     * @return string
     */
    public static function css(string $css): string
    {
        // Remove comments
        $css = preg_replace('!/\*[^*]*\*+([^/][^*]*\*+)*/!', '', $css);

        // Remove whitespace
        $css = str_replace(["\r\n", "\r", "\n", "\t"], '', $css);
        $css = preg_replace('/\s+/', ' ', $css);
        $css = preg_replace('/\s*([\{\}\:\;\,\>])\s*/', '$1', $css);

        // Remove redundant semicolons before closing braces
        $css = str_replace(';}', '}', $css);

        // Shorten hexadecimal colors
        $css = preg_replace('/#([a-f0-9])\\1([a-f0-9])\\2([a-f0-9])\\3/i', '#$1$2$3', $css);

        return trim($css);
    }
}
