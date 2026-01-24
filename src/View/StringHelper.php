<?php

declare(strict_types=1);

namespace Plugs\View;

/**
 * Chainable String Helper for View Templates
 *
 * Usage in templates:
 * {{ str($article->title)->title()->truncate(50) }}
 * {{ str($user->email)->lower()->slug() }}
 * {{ str($price)->currency('USD') }}
 */
class StringHelper
{
    private string $value;

    public function __construct(string $value)
    {
        $this->value = $value;
    }

    /**
     * Convert to uppercase
     */
    public function upper(): self
    {
        $this->value = strtoupper($this->value);

        return $this;
    }

    /**
     * Convert to lowercase
     */
    public function lower(): self
    {
        $this->value = strtolower($this->value);

        return $this;
    }

    /**
     * Convert to title case
     */
    public function title(): self
    {
        $this->value = ucwords(strtolower($this->value));

        return $this;
    }

    /**
     * Capitalize first letter
     */
    public function ucfirst(): self
    {
        $this->value = ucfirst($this->value);

        return $this;
    }

    /**
     * Convert to URL-friendly slug
     */
    public function slug(): self
    {
        $this->value = strtolower(trim(preg_replace('/[^A-Za-z0-9-]+/', '-', $this->value), '-'));

        return $this;
    }

    /**
     * Truncate to specified length
     */
    public function truncate(int $length = 100, string $end = '...'): self
    {
        if (mb_strlen($this->value) > $length) {
            $this->value = mb_substr($this->value, 0, $length) . $end;
        }

        return $this;
    }

    /**
     * Create excerpt (word-aware truncation)
     */
    public function excerpt(int $length = 150): self
    {
        $stripped = strip_tags($this->value);

        if (mb_strlen($stripped) <= $length) {
            $this->value = $stripped;

            return $this;
        }

        $truncated = mb_substr($stripped, 0, $length);
        $lastSpace = mb_strrpos($truncated, ' ');

        $this->value = mb_substr($truncated, 0, $lastSpace) . '...';

        return $this;
    }

    /**
     * Limit to specified number of words
     */
    public function words(int $words = 100, string $end = '...'): self
    {
        $wordsArray = preg_split('/\s+/', $this->value, $words + 1);

        if (count($wordsArray) > $words) {
            array_pop($wordsArray);
            $this->value = implode(' ', $wordsArray) . $end;
        }

        return $this;
    }

    /**
     * Replace text
     */
    public function replace(string $search, string $replace): self
    {
        $this->value = str_replace($search, $replace, $this->value);

        return $this;
    }

    /**
     * Strip HTML tags
     */
    public function stripTags(string $allowedTags = ''): self
    {
        $this->value = strip_tags($this->value, $allowedTags);

        return $this;
    }

    /**
     * Trim whitespace
     */
    public function trim(string $characters = " \t\n\r\0\x0B"): self
    {
        $this->value = trim($this->value, $characters);

        return $this;
    }

    /**
     * Append text
     */
    public function append(string $text): self
    {
        $this->value .= $text;

        return $this;
    }

    /**
     * Prepend text
     */
    public function prepend(string $text): self
    {
        $this->value = $text . $this->value;

        return $this;
    }

    /**
     * Format as currency
     */
    public function currency(string $currency = 'USD'): self
    {
        $symbols = [
            'USD' => '$',
            'EUR' => '€',
            'GBP' => '£',
            'JPY' => '¥',
            'NGN' => '₦',
            'INR' => '₹',
        ];

        $symbol = $symbols[$currency] ?? $currency . ' ';
        $this->value = $symbol . number_format((float)$this->value, 2);

        return $this;
    }

    /**
     * Format as number
     */
    public function number(int $decimals = 0): self
    {
        $this->value = number_format((float)$this->value, $decimals);

        return $this;
    }

    /**
     * Format as percentage
     */
    public function percent(int $decimals = 2): self
    {
        $this->value = number_format((float)$this->value, $decimals) . '%';

        return $this;
    }

    /**
     * Get the final value
     */
    public function toString(): string
    {
        return $this->value;
    }

    /**
     * Magic method to convert to string
     */
    public function __toString(): string
    {
        return $this->value;
    }
}

/**
 * Global helper function
 */
if (!function_exists('str')) {
    function str(string $value): StringHelper
    {
        return new StringHelper($value);
    }
}
