<?php

declare(strict_types=1);

if (!function_exists('truncateText')) {
    /**
     * Truncate text to a maximum length at word boundaries
     */
    function truncateText(string $text, int $maxLength, string $suffix = '...'): string
    {
        $text = trim($text);

        if (mb_strlen($text) <= $maxLength) {
            return $text;
        }

        $truncated = mb_substr($text, 0, $maxLength);

        // Try to break at word boundary
        $lastSpace = mb_strrpos($truncated, ' ');
        $threshold = max(20, (int) ($maxLength * 0.7)); // At least 70% of max length

        if ($lastSpace !== false && $lastSpace > $threshold) {
            $truncated = mb_substr($truncated, 0, $lastSpace);
        }

        // Remove trailing punctuation
        $truncated = rtrim($truncated, ".,!?;:-");

        return $truncated . $suffix;
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
        $text = strip_tags($html);
        $text = html_entity_decode($text, ENT_QUOTES | ENT_HTML5, 'UTF-8');

        return normalizeWhitespace($text);
    }
}

if (!function_exists('generateSeoTitle')) {
    /**
     * Generate SEO Title from article title
     */
    function generateSeoTitle(string $title, int $maxLength = 60): string
    {
        $seoTitle = normalizeWhitespace($title);

        return truncateText($seoTitle, $maxLength, "");
    }
}

if (!function_exists('generateSeoDescription')) {
    /**
     * Generate SEO Description from content
     */
    function generateSeoDescription(
        string $content,
        string $fallback = '',
        int $maxLength = 160,
        int $minLength = 50
    ): string {
        $text = cleanHtmlText($content);

        // Use fallback if content is too short
        if (mb_strlen($text) < $minLength && !empty($fallback)) {
            $text = $fallback . ' - ' . $text;
        }

        return truncateText($text, $maxLength, "");
    }
}

if (!function_exists('extractWords')) {
    /**
     * Extract meaningful words from text, removing stop words
     */
    function extractWords(string $text, array $stopWords = [], int $minWordLength = 3): array
    {
        $text = cleanHtmlText($text);
        $text = mb_strtolower($text);

        // Remove special characters but keep spaces and hyphens
        $text = preg_replace('/[^\p{L}\p{N}\s-]/u', ' ', $text);

        // Split into words
        $words = preg_split('/\s+/', $text, -1, PREG_SPLIT_NO_EMPTY);

        // Use default stop words if none provided
        if (empty($stopWords)) {
            $stopWords = getDefaultStopWords();
        }

        // Filter words
        return array_filter($words, function ($word) use ($stopWords, $minWordLength) {
            return mb_strlen($word) >= $minWordLength && !in_array($word, $stopWords, true);
        });
    }
}

if (!function_exists('getDefaultStopWords')) {
    /**
     * Get default English stop words
     */
    function getDefaultStopWords(): array
    {
        return [
            'the',
            'a',
            'an',
            'and',
            'or',
            'but',
            'in',
            'on',
            'at',
            'to',
            'for',
            'of',
            'with',
            'by',
            'from',
            'up',
            'about',
            'into',
            'through',
            'during',
            'before',
            'after',
            'above',
            'below',
            'between',
            'among',
            'is',
            'are',
            'was',
            'were',
            'be',
            'been',
            'being',
            'have',
            'has',
            'had',
            'do',
            'does',
            'did',
            'will',
            'would',
            'could',
            'should',
            'may',
            'might',
            'must',
            'can',
            'this',
            'that',
            'these',
            'those',
            'i',
            'you',
            'he',
            'she',
            'it',
            'we',
            'they',
            'me',
            'him',
            'her',
            'us',
            'them',
            'my',
            'your',
            'his',
            'its',
            'our',
            'their',
            'what',
            'which',
            'who',
            'whom',
            'whose',
            'when',
            'where',
            'why',
            'how',
            'all',
            'any',
            'both',
            'each',
            'few',
            'more',
            'most',
            'other',
            'some',
            'such',
            'no',
            'nor',
            'not',
            'only',
            'own',
            'same',
            'so',
            'than',
            'too',
            'very',
        ];
    }
}

if (!function_exists('getWordFrequency')) {
    /**
     * Count word frequency in text
     */
    function getWordFrequency(array $words): array
    {
        $frequency = array_count_values($words);

        arsort($frequency);

        return $frequency;
    }
}

if (!function_exists('generateSeoKeywords')) {
    /**
     * Generate SEO Keywords from content and title
     */
    function generateSeoKeywords(
        string $content,
        string $title = '',
        int $maxKeywords = 10,
        int $maxLength = 255,
        array $customStopWords = []
    ): string {
        $text = $title . ' ' . $content;
        $words = extractWords($text, $customStopWords);
        $frequency = getWordFrequency($words);

        // Take top keywords
        $keywords = array_slice(array_keys($frequency), 0, $maxKeywords);
        $keywordString = implode(', ', $keywords);

        // Ensure we don't exceed max length
        if (mb_strlen($keywordString) > $maxLength) {
            $lastComma = mb_strrpos(mb_substr($keywordString, 0, $maxLength), ',');
            $keywordString = $lastComma !== false
                ? mb_substr($keywordString, 0, $lastComma)
                : mb_substr($keywordString, 0, $maxLength);
        }

        return $keywordString;
    }
}

if (!function_exists('generateSlug')) {
    /**
     * Generate URL-friendly slug from text
     *
     * Supports international text via transliteration (requires intl extension).
     */
    function generateSlug(
        string $text,
        string $separator = '-',
        ?callable $uniqueChecker = null
    ): string {
        // Transliterate international characters to ASCII if intl extension is available
        if (function_exists('transliterator_transliterate')) {
            $text = transliterator_transliterate('Any-Latin; Latin-ASCII; Lower()', $text);
        }

        $slug = mb_strtolower(trim($text));

        // Replace spaces with separator
        $slug = preg_replace('/\s+/', $separator, $slug);

        // Remove special characters (keep alphanumeric and separator)
        $slug = preg_replace('/[^a-z0-9' . preg_quote($separator, '/') . ']/', '', $slug);

        // Remove consecutive separators
        $slug = preg_replace('/' . preg_quote($separator, '/') . '+/', $separator, $slug);

        // Trim separators from ends
        $slug = trim($slug, $separator);

        // Fallback for empty slugs
        if (empty($slug)) {
            $slug = 'item-' . time();
        }

        // Check uniqueness if checker provided
        if ($uniqueChecker !== null) {
            $slug = ensureUniqueSlug($slug, $separator, $uniqueChecker);
        }

        return $slug;
    }
}

if (!function_exists('ensureUniqueSlug')) {
    /**
     * Ensure slug uniqueness using a custom checker function
     */
    function ensureUniqueSlug(string $slug, string $separator, callable $existsChecker): string
    {
        $originalSlug = $slug;
        $counter = 1;

        while ($existsChecker($slug)) {
            $slug = $originalSlug . $separator . $counter;
            $counter++;
        }

        return $slug;
    }
}

if (!function_exists('generateExcerpt')) {
    /**
     * Generate excerpt from content
     */
    function generateExcerpt(string $content, int $length = 200, string $suffix = '...'): string
    {
        if (empty(trim($content))) {
            return '';
        }

        $text = cleanHtmlText($content);

        return truncateText($text, $length, $suffix);
    }
}

if (!function_exists('blank')) {
    /**
     * Determine if the given value is "blank".
     *
     * Unlike empty(), this returns FALSE for boolean false, integer 0,
     * and string "0". It returns TRUE for null, empty arrays,
     * whitespace-only strings, and empty Countable objects.
     */
    function blank(mixed $value): bool
    {
        if (is_null($value)) {
            return true;
        }

        if (is_string($value)) {
            return trim($value) === '';
        }

        if (is_numeric($value) || is_bool($value)) {
            return false;
        }

        if ($value instanceof \Countable) {
            return count($value) === 0;
        }

        return empty($value);
    }
}

if (!function_exists('filled')) {
    /**
     * Determine if a value is "filled".
     */
    function filled(mixed $value): bool
    {
        return !blank($value);
    }
}

if (!function_exists('value')) {
    /**
     * Return the default value of the given value.
     */
    function value($value, ...$args)
    {
        return $value instanceof \Closure ? $value(...$args) : $value;
    }
}

if (!function_exists('with')) {
    /**
     * Return the given value, optionally passed to a callback.
     */
    function with($value, callable $callback = null)
    {
        return is_null($callback) ? $value : $callback($value);
    }
}

if (!function_exists('tap')) {
    /**
     * Call the given Closure with the given value then return the value.
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
            if ($times < 1) {
                throw $e;
            }

            if ($sleepMilliseconds) {
                usleep($sleepMilliseconds * 1000);
            }

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
        if (is_null($key)) {
            return $target;
        }

        $key = is_array($key) ? $key : explode('.', $key);

        foreach ($key as $i => $segment) {
            unset($key[$i]);

            if (is_null($segment)) {
                return $target;
            }

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

if (!function_exists('abort')) {
    /**
     * Throw an HTTP exception with a given code.
     *
     * @param int $code
     * @param string $message
     * @param array $headers
     * @return never
     *
     * @throws \Plugs\Exceptions\HttpException
     */
    function abort(int $code, string $message = '', array $headers = []): never
    {
        if ($code === 404 && $message === '') {
            $message = 'Not Found';
        }

        throw new \Plugs\Exceptions\HttpException($code, $message, null, $headers);
    }
}
