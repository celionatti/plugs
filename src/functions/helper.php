<?php

declare(strict_types=1);

if (defined('PLUGS_HELPER_LOADED'))
    return;
define('PLUGS_HELPER_LOADED', true);

use Plugs\Utils\Seo;
use Plugs\Utils\Str;

/*
|--------------------------------------------------------------------------
| Generic Helper Functions - Refactored & Restored
|--------------------------------------------------------------------------
|
| Delegates common utility logic to Plugs\Utils classes.
| Restored to ensure full backward compatibility.
*/

if (!function_exists('truncateText')) {
    /**
     * Truncate text to a maximum length at word boundaries
     */
    function truncateText(string $text, int $maxLength, string $suffix = '...'): string
    {
        return Seo::truncateText($text, $maxLength, $suffix);
    }
}

if (!function_exists('normalizeWhitespace')) {
    /**
     * Clean and normalize whitespace in text
     */
    function normalizeWhitespace(string $text): string
    {
        return preg_replace('/\s+/', ' ', trim($text));
    }
}

if (!function_exists('cleanHtmlText')) {
    /**
     * Strip HTML and decode entities
     */
    function cleanHtmlText(string $html): string
    {
        return Seo::cleanHtmlText($html);
    }
}

if (!function_exists('generateSeoTitle')) {
    /**
     * Generate SEO Title from article title
     */
    function generateSeoTitle(string $title, int $maxLength = 60): string
    {
        return Seo::generateTitle($title, $maxLength);
    }
}

if (!function_exists('generateSeoDescription')) {
    /**
     * Generate SEO Description from content
     */
    function generateSeoDescription(string $content, string $fallback = '', int $maxLength = 160): string
    {
        return Seo::generateDescription($content, $fallback, $maxLength);
    }
}

if (!function_exists('generateSeoKeywords')) {
    /**
     * Generate SEO Keywords from content and title
     */
    function generateSeoKeywords(string $content, string $title = '', int $maxKeywords = 10): string
    {
        return Seo::generateKeywords($content, $title, $maxKeywords);
    }
}

if (!function_exists('generateSlug')) {
    /**
     * Generate URL-friendly slug from text
     */
    function generateSlug(string $text, string $separator = '-'): string
    {
        return Str::slug($text, $separator);
    }
}

if (!function_exists('generateExcerpt')) {
    /**
     * Generate excerpt from content
     */
    function generateExcerpt(string $content, int $length = 200, string $suffix = '...'): string
    {
        return Seo::truncateText(Seo::cleanHtmlText($content), $length, $suffix);
    }
}

if (!function_exists('blank')) {
    /**
     * Determine if the given value is "blank"
     */
    function blank(mixed $value): bool
    {
        if (is_null($value))
            return true;
        if (is_string($value))
            return trim($value) === '';
        if (is_numeric($value) || is_bool($value))
            return false;
        if ($value instanceof \Countable)
            return count($value) === 0;
        return empty($value);
    }
}

if (!function_exists('filled')) {
    /**
     * Determine if a value is "filled"
     */
    function filled(mixed $value): bool
    {
        return !blank($value);
    }
}

if (!function_exists('value')) {
    /**
     * Return the default value of the given value
     */
    function value($value, ...$args)
    {
        return $value instanceof \Closure ? $value(...$args) : $value;
    }
}

if (!function_exists('with')) {
    /**
     * Return the given value, optionally passed to a callback
     */
    function with($value, callable $callback = null)
    {
        return is_null($callback) ? $value : $callback($value);
    }
}

if (!function_exists('tap')) {
    /**
     * Call the given Closure with the given value then return the value
     */
    function tap($value, $callback = null)
    {
        if (is_null($callback)) {
            return new class ($value) {
                public function __construct(public $target)
                {}
                public function __call($method, $parameters)
                {
                    $this->target->{$method}(...$parameters);
                    return $this->target;
                }
            };
        }
        $callback($value);
        return $value;
    }
}

if (!function_exists('retry')) {
    /**
     * Retry an operation a given number of times.
     */
    function retry(int $times, callable $callback, int $sleepMilliseconds = 0)
    {
        $attempts = 0;
        beginning:
        $attempts++;
        $times--;
        try {
            return $callback($attempts);
        } catch (\Throwable $e) {
            if ($times < 1)
                throw $e;
            if ($sleepMilliseconds)
                usleep($sleepMilliseconds * 1000);
            goto beginning;
        }
    }
}

if (!function_exists('data_get')) {
    /**
     * Get an item from an array or object using "dot" notation.
     */
    function data_get($target, $key, $default = null)
    {
        if (is_null($key))
            return $target;
        $key = is_array($key) ? $key : explode('.', $key);

        foreach ($key as $i => $segment) {
            unset($key[$i]);
            if (is_null($segment))
                return $target;

            if (is_array($target)) {
                if (array_key_exists($segment, $target)) {
                    $target = $target[$segment];
                } else {
                    return value($default);
                }
            } elseif (is_object($target)) {
                if (isset($target->{$segment})) {
                    $target = $target->{$segment};
                } else {
                    return value($default);
                }
            } elseif ($target instanceof \ArrayAccess) {
                if (isset($target[$segment])) {
                    $target = $target[$segment];
                } else {
                    return value($default);
                }
            } else {
                return value($default);
            }
        }
        return $target;
    }
}

// abort() is defined in abort.php (canonical source with full signature)
