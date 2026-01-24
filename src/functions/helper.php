<?php

declare(strict_types=1);

/**
 * Truncate text to a maximum length at word boundaries
 */
function truncateText(string $text, int $maxLength, string $suffix = '...'): string
{
    $text = trim($text);

    if (strlen($text) <= $maxLength) {
        return $text;
    }

    $truncated = substr($text, 0, $maxLength);

    // Try to break at word boundary
    $lastSpace = strrpos($truncated, ' ');
    $threshold = max(20, (int)($maxLength * 0.7)); // At least 70% of max length

    if ($lastSpace !== false && $lastSpace > $threshold) {
        $truncated = substr($truncated, 0, $lastSpace);
    }

    // Remove trailing punctuation
    $truncated = rtrim($truncated, ".,!?;:-");

    return $truncated . $suffix;
}

/**
 * Clean and normalize whitespace in text
 */
function normalizeWhitespace(string $text): string
{
    return preg_replace('/\s+/', ' ', trim($text));
}

/**
 * Strip HTML and decode entities
 */
function cleanHtmlText(string $html): string
{
    $text = strip_tags($html);
    $text = html_entity_decode($text, ENT_QUOTES | ENT_HTML5, 'UTF-8');

    return normalizeWhitespace($text);
}

/**
 * Generate SEO Title from article title
 */
function generateSeoTitle(string $title, int $maxLength = 60): string
{
    $seoTitle = normalizeWhitespace($title);

    return truncateText($seoTitle, $maxLength, "");
}

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
    if (strlen($text) < $minLength && !empty($fallback)) {
        $text = $fallback . ' - ' . $text;
    }

    return truncateText($text, $maxLength, "");
}

/**
 * Extract meaningful words from text, removing stop words
 */
function extractWords(string $text, array $stopWords = [], int $minWordLength = 3): array
{
    $text = cleanHtmlText($text);
    $text = strtolower($text);

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
        return strlen($word) >= $minWordLength && !in_array($word, $stopWords, true);
    });
}

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

/**
 * Count word frequency in text
 */
function getWordFrequency(array $words): array
{
    $frequency = [];

    foreach ($words as $word) {
        $frequency[$word] = ($frequency[$word] ?? 0) + 1;
    }

    arsort($frequency);

    return $frequency;
}

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
    if (strlen($keywordString) > $maxLength) {
        $lastComma = strrpos(substr($keywordString, 0, $maxLength), ',');
        $keywordString = $lastComma !== false
            ? substr($keywordString, 0, $lastComma)
            : substr($keywordString, 0, $maxLength);
    }

    return $keywordString;
}

/**
 * Generate URL-friendly slug from text
 */
function generateSlug(
    string $text,
    string $separator = '-',
    ?callable $uniqueChecker = null
): string {
    $slug = strtolower(trim($text));

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
