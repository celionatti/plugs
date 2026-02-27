<?php

declare(strict_types=1);

namespace Plugs\Utils;

/**
 * Plugs Framework SEO & Content Utilities
 */
class Seo
{
    /**
     * Generate SEO Title from article title.
     */
    public static function generateTitle(string $title, int $maxLength = 60): string
    {
        return static::truncateText(trim(preg_replace('/\s+/', ' ', $title)), $maxLength, "");
    }

    /**
     * Generate SEO Description from content.
     */
    public static function generateDescription(string $content, string $fallback = '', int $maxLength = 160, int $minLength = 50): string
    {
        $text = static::cleanHtmlText($content);
        if (mb_strlen($text) < $minLength && !empty($fallback)) {
            $text = $fallback . ' - ' . $text;
        }
        return static::truncateText($text, $maxLength, "");
    }

    /**
     * Generate SEO Keywords from content and title.
     */
    public static function generateKeywords(string $content, string $title = '', int $maxKeywords = 10, int $maxLength = 255): string
    {
        $text = $title . ' ' . $content;
        $words = static::extractWords($text);
        $frequency = array_count_values($words);
        arsort($frequency);

        $keywords = array_slice(array_keys($frequency), 0, $maxKeywords);
        $keywordString = implode(', ', $keywords);

        if (mb_strlen($keywordString) > $maxLength) {
            $lastComma = mb_strrpos(mb_substr($keywordString, 0, $maxLength), ',');
            $keywordString = $lastComma !== false ? mb_substr($keywordString, 0, $lastComma) : mb_substr($keywordString, 0, $maxLength);
        }

        return $keywordString;
    }

    /**
     * Truncate text to a maximum length at word boundaries.
     */
    public static function truncateText(string $text, int $maxLength, string $suffix = '...'): string
    {
        $text = trim($text);
        if (mb_strlen($text) <= $maxLength)
            return $text;
        $truncated = mb_substr($text, 0, $maxLength);
        $lastSpace = mb_strrpos($truncated, ' ');
        $threshold = max(20, (int) ($maxLength * 0.7));
        if ($lastSpace !== false && $lastSpace > $threshold)
            $truncated = mb_substr($truncated, 0, $lastSpace);
        return rtrim($truncated, ".,!?;:-") . $suffix;
    }

    /**
     * Strip HTML and decode entities.
     */
    public static function cleanHtmlText(string $html): string
    {
        $text = strip_tags($html);
        $text = html_entity_decode($text, ENT_QUOTES | ENT_HTML5, 'UTF-8');
        return preg_replace('/\s+/', ' ', trim($text));
    }

    /**
     * Extract meaningful words from text.
     */
    protected static function extractWords(string $text): array
    {
        $text = mb_strtolower(static::cleanHtmlText($text));
        $text = preg_replace('/[^\p{L}\p{N}\s-]/u', ' ', $text);
        $words = preg_split('/\s+/', $text, -1, PREG_SPLIT_NO_EMPTY);
        $stopWords = ['the', 'a', 'an', 'and', 'or', 'but', 'in', 'on', 'at', 'to', 'for', 'of', 'with', 'by', 'from', 'is', 'are', 'was', 'were', 'it', 'this', 'that'];
        return array_filter($words, fn($w) => mb_strlen($w) >= 3 && !in_array($w, $stopWords));
    }
}
