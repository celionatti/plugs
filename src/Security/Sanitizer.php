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

    public static function array(array $data, string $method = 'string'): array
    {
        return array_map([self::class, $method], $data);
    }
}
