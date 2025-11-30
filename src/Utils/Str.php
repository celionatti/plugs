<?php

declare(strict_types=1);

namespace Plugs\Utils;

/**
 * String manipulation helper class
 * Production-ready string utilities for PHP applications
 */
class Str
{
    /**
     * The cache of snake-cased words.
     */
    protected static array $snakeCache = [];

    /**
     * The cache of camel-cased words.
     */
    protected static array $camelCache = [];

    /**
     * The cache of studly-cased words.
     */
    protected static array $studlyCache = [];

    /**
     * Convert a value to camel case.
     */
    public static function camel(string $value): string
    {
        if (isset(static::$camelCache[$value])) {
            return static::$camelCache[$value];
        }

        return static::$camelCache[$value] = lcfirst(static::studly($value));
    }

    /**
     * Convert a string to snake case.
     */
    public static function snake(string $value, string $delimiter = '_'): string
    {
        $key = $value;

        if (isset(static::$snakeCache[$key][$delimiter])) {
            return static::$snakeCache[$key][$delimiter];
        }

        if (!ctype_lower($value)) {
            $value = preg_replace('/\s+/u', '', ucwords($value));
            $value = static::lower(preg_replace('/(.)(?=[A-Z])/u', '$1' . $delimiter, $value));
        }

        return static::$snakeCache[$key][$delimiter] = $value;
    }

    /**
     * Convert a value to studly caps case (PascalCase).
     */
    public static function studly(string $value): string
    {
        if (isset(static::$studlyCache[$value])) {
            return static::$studlyCache[$value];
        }

        $value = ucwords(str_replace(['-', '_'], ' ', $value));

        return static::$studlyCache[$value] = str_replace(' ', '', $value);
    }

    /**
     * Convert a string to kebab case.
     */
    public static function kebab(string $value): string
    {
        return static::snake($value, '-');
    }

    /**
     * Convert the given string to title case.
     */
    public static function title(string $value): string
    {
        return mb_convert_case($value, MB_CASE_TITLE, 'UTF-8');
    }

    /**
     * Convert the given string to lower-case.
     */
    public static function lower(string $value): string
    {
        return mb_strtolower($value, 'UTF-8');
    }

    /**
     * Convert the given string to upper-case.
     */
    public static function upper(string $value): string
    {
        return mb_strtoupper($value, 'UTF-8');
    }

    /**
     * Return the length of the given string.
     */
    public static function length(string $value, ?string $encoding = null): int
    {
        return mb_strlen($value, $encoding);
    }

    /**
     * Limit the number of characters in a string.
     */
    public static function limit(string $value, int $limit = 100, string $end = '...'): string
    {
        if (mb_strwidth($value, 'UTF-8') <= $limit) {
            return $value;
        }

        return rtrim(mb_strimwidth($value, 0, $limit, '', 'UTF-8')) . $end;
    }

    /**
     * Limit the number of words in a string.
     */
    public static function words(string $value, int $words = 100, string $end = '...'): string
    {
        preg_match('/^\s*+(?:\S++\s*+){1,' . $words . '}/u', $value, $matches);

        if (!isset($matches[0]) || static::length($value) === static::length($matches[0])) {
            return $value;
        }

        return rtrim($matches[0]) . $end;
    }

    /**
     * Determine if a given string contains a given substring.
     */
    public static function contains(string $haystack, string|array $needles, bool $ignoreCase = false): bool
    {
        if ($ignoreCase) {
            $haystack = mb_strtolower($haystack);
        }

        foreach ((array) $needles as $needle) {
            if ($ignoreCase) {
                $needle = mb_strtolower($needle);
            }

            if ($needle !== '' && str_contains($haystack, $needle)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Determine if a given string starts with a given substring.
     */
    public static function startsWith(string $haystack, string|array $needles): bool
    {
        foreach ((array) $needles as $needle) {
            if ((string) $needle !== '' && str_starts_with($haystack, $needle)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Determine if a given string ends with a given substring.
     */
    public static function endsWith(string $haystack, string|array $needles): bool
    {
        foreach ((array) $needles as $needle) {
            if ((string) $needle !== '' && str_ends_with($haystack, $needle)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Cap a string with a single instance of a given value.
     */
    public static function finish(string $value, string $cap): string
    {
        $quoted = preg_quote($cap, '/');

        return preg_replace('/(?:' . $quoted . ')+$/u', '', $value) . $cap;
    }

    /**
     * Begin a string with a single instance of a given value.
     */
    public static function start(string $value, string $prefix): string
    {
        $quoted = preg_quote($prefix, '/');

        return $prefix . preg_replace('/^(?:' . $quoted . ')+/u', '', $value);
    }

    /**
     * Replace the first occurrence of a given value in the string.
     */
    public static function replaceFirst(string $search, string $replace, string $subject): string
    {
        $search = (string) $search;

        if ($search === '') {
            return $subject;
        }

        $position = strpos($subject, $search);

        if ($position !== false) {
            return substr_replace($subject, $replace, $position, strlen($search));
        }

        return $subject;
    }

    /**
     * Replace the last occurrence of a given value in the string.
     */
    public static function replaceLast(string $search, string $replace, string $subject): string
    {
        if ($search === '') {
            return $subject;
        }

        $position = strrpos($subject, $search);

        if ($position !== false) {
            return substr_replace($subject, $replace, $position, strlen($search));
        }

        return $subject;
    }

    /**
     * Replace all occurrences of the search string with the replacement string.
     */
    public static function replace(string|array $search, string|array $replace, string|array $subject): string|array
    {
        return str_replace($search, $replace, $subject);
    }

    /**
     * Remove all occurrences of a given value from the string.
     */
    public static function remove(string|array $search, string $subject, bool $caseSensitive = true): string
    {
        return $caseSensitive
            ? str_replace($search, '', $subject)
            : str_ireplace($search, '', $subject);
    }

    /**
     * Generate a random string of the specified length.
     */
    public static function random(int $length = 16): string
    {
        $string = '';

        while (($len = strlen($string)) < $length) {
            $size = $length - $len;
            $bytes = random_bytes($size);
            $string .= substr(str_replace(['/', '+', '='], '', base64_encode($bytes)), 0, $size);
        }

        return $string;
    }

    /**
     * Generate a URL-friendly "slug" from a given string.
     */
    public static function slug(string $title, string $separator = '-', ?string $language = 'en'): string
    {
        $title = $language ? static::ascii($title, $language) : $title;

        // Convert all dashes/underscores into separator
        $flip = $separator === '-' ? '_' : '-';
        $title = preg_replace('![' . preg_quote($flip) . ']+!u', $separator, $title);

        // Replace @ with the word 'at'
        $title = str_replace('@', $separator . 'at' . $separator, $title);

        // Remove all characters that are not the separator, letters, numbers, or whitespace
        $title = preg_replace('![^' . preg_quote($separator) . '\pL\pN\s]+!u', '', static::lower($title));

        // Replace all separator characters and whitespace by a single separator
        $title = preg_replace('![' . preg_quote($separator) . '\s]+!u', $separator, $title);

        return trim($title, $separator);
    }

    /**
     * Transliterate a UTF-8 value to ASCII.
     */
    public static function ascii(string $value, string $language = 'en'): string
    {
        return iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $value) ?: $value;
    }

    /**
     * Get the portion of a string before the first occurrence of a given value.
     */
    public static function before(string $subject, string $search): string
    {
        if ($search === '') {
            return $subject;
        }

        $result = strstr($subject, $search, true);

        return $result === false ? $subject : $result;
    }

    /**
     * Get the portion of a string after the first occurrence of a given value.
     */
    public static function after(string $subject, string $search): string
    {
        return $search === '' ? $subject : array_reverse(explode($search, $subject, 2))[0];
    }

    /**
     * Get the portion of a string before the last occurrence of a given value.
     */
    public static function beforeLast(string $subject, string $search): string
    {
        if ($search === '') {
            return $subject;
        }

        $pos = mb_strrpos($subject, $search);

        if ($pos === false) {
            return $subject;
        }

        return static::substr($subject, 0, $pos);
    }

    /**
     * Get the portion of a string after the last occurrence of a given value.
     */
    public static function afterLast(string $subject, string $search): string
    {
        if ($search === '') {
            return $subject;
        }

        $position = strrpos($subject, $search);

        if ($position === false) {
            return $subject;
        }

        return substr($subject, $position + strlen($search));
    }

    /**
     * Get the portion of a string between two given values.
     */
    public static function between(string $subject, string $from, string $to): string
    {
        if ($from === '' || $to === '') {
            return $subject;
        }

        return static::beforeLast(static::after($subject, $from), $to);
    }

    /**
     * Returns the portion of the string specified by the start and length parameters.
     */
    public static function substr(string $string, int $start, ?int $length = null): string
    {
        return mb_substr($string, $start, $length, 'UTF-8');
    }

    /**
     * Make a string's first character uppercase.
     */
    public static function ucfirst(string $string): string
    {
        return static::upper(static::substr($string, 0, 1)) . static::substr($string, 1);
    }

    /**
     * Split a string by uppercase characters.
     */
    public static function ucsplit(string $string): array
    {
        return preg_split('/(?=\p{Lu})/u', $string, -1, PREG_SPLIT_NO_EMPTY);
    }

    /**
     * Determine if a given string matches a given pattern.
     */
    public static function is(string|array $pattern, string $value): bool
    {
        $patterns = is_array($pattern) ? $pattern : [$pattern];

        foreach ($patterns as $pattern) {
            $pattern = (string) $pattern;

            if ($pattern === $value) {
                return true;
            }

            $pattern = preg_quote($pattern, '#');
            $pattern = str_replace('\*', '.*', $pattern);

            if (preg_match('#^' . $pattern . '\z#u', $value) === 1) {
                return true;
            }
        }

        return false;
    }

    /**
     * Determine if a given value is valid UUID.
     */
    public static function isUuid(string $value): bool
    {
        return preg_match('/^[\da-f]{8}-[\da-f]{4}-[\da-f]{4}-[\da-f]{4}-[\da-f]{12}$/iD', $value) > 0;
    }

    /**
     * Determine if a given string is valid JSON.
     */
    public static function isJson(string $value): bool
    {
        if (!is_string($value)) {
            return false;
        }

        try {
            json_decode($value, true, 512, JSON_THROW_ON_ERROR);
        } catch (\JsonException $e) {
            return false;
        }

        return true;
    }

    /**
     * Pad both sides of a string with another.
     */
    public static function padBoth(string $value, int $length, string $pad = ' '): string
    {
        return str_pad($value, $length, $pad, STR_PAD_BOTH);
    }

    /**
     * Pad the left side of a string with another.
     */
    public static function padLeft(string $value, int $length, string $pad = ' '): string
    {
        return str_pad($value, $length, $pad, STR_PAD_LEFT);
    }

    /**
     * Pad the right side of a string with another.
     */
    public static function padRight(string $value, int $length, string $pad = ' '): string
    {
        return str_pad($value, $length, $pad, STR_PAD_RIGHT);
    }

    /**
     * Repeat the given string.
     */
    public static function repeat(string $string, int $times): string
    {
        return str_repeat($string, $times);
    }

    /**
     * Reverse the given string.
     */
    public static function reverse(string $value): string
    {
        return implode(array_reverse(mb_str_split($value)));
    }

    /**
     * Convert the given string to proper case.
     */
    public static function headline(string $value): string
    {
        $parts = explode(' ', $value);

        $parts = count($parts) > 1
            ? array_map([static::class, 'title'], $parts)
            : array_map([static::class, 'title'], static::ucsplit(implode('_', $parts)));

        $collapsed = static::replace(['-', '_', ' '], '_', implode('_', $parts));

        return implode(' ', array_filter(explode('_', $collapsed)));
    }

    /**
     * Generate a UUID (version 4).
     */
    public static function uuid(): string
    {
        $data = random_bytes(16);

        $data[6] = chr(ord($data[6]) & 0x0f | 0x40); // version 4
        $data[8] = chr(ord($data[8]) & 0x3f | 0x80); // variant

        return vsprintf('%s%s-%s-%s-%s-%s%s%s', str_split(bin2hex($data), 4));
    }

    /**
     * Generate a ordered UUID (version 4).
     */
    public static function orderedUuid(): string
    {
        $timestamp = (int) (microtime(true) * 10000);
        $uuid = static::uuid();

        return substr(sprintf('%012x', $timestamp), 0, 8) . '-' . substr($uuid, 9);
    }

    /**
     * Swap multiple keywords in a string with other keywords.
     */
    public static function swap(array $map, string $subject): string
    {
        return strtr($subject, $map);
    }

    /**
     * Wrap the string with the given strings.
     */
    public static function wrap(string $value, string $before, ?string $after = null): string
    {
        return $before . $value . ($after ??= $before);
    }

    /**
     * Unwrap the string with the given strings.
     */
    public static function unwrap(string $value, string $before, ?string $after = null): string
    {
        if (static::startsWith($value, $before)) {
            $value = static::substr($value, static::length($before));
        }

        if (static::endsWith($value, $after ??= $before)) {
            $value = static::substr($value, 0, -static::length($after));
        }

        return $value;
    }

    /**
     * Convert a string to its plural form (English only).
     */
    public static function plural(string $value, int $count = 2): string
    {
        if ($count === 1) {
            return $value;
        }

        $plural = [
            '/(quiz)$/i' => '$1zes',
            '/^(ox)$/i' => '$1en',
            '/([m|l])ouse$/i' => '$1ice',
            '/(matr|vert|ind)ix|ex$/i' => '$1ices',
            '/(x|ch|ss|sh)$/i' => '$1es',
            '/([^aeiouy]|qu)y$/i' => '$1ies',
            '/(hive)$/i' => '$1s',
            '/(?:([^f])fe|([lr])f)$/i' => '$1$2ves',
            '/(shea|lea|loa|thie)f$/i' => '$1ves',
            '/sis$/i' => 'ses',
            '/([ti])um$/i' => '$1a',
            '/(tomat|potat|ech|her|vet)o$/i' => '$1oes',
            '/(bu)s$/i' => '$1ses',
            '/(alias)$/i' => '$1es',
            '/(octop)us$/i' => '$1i',
            '/(ax|test)is$/i' => '$1es',
            '/(us)$/i' => '$1es',
            '/s$/i' => 's',
            '/$/' => 's',
        ];

        foreach ($plural as $pattern => $replacement) {
            if (preg_match($pattern, $value)) {
                return preg_replace($pattern, $replacement, $value);
            }
        }

        return $value;
    }

    /**
     * Convert a string to its singular form (English only).
     */
    public static function singular(string $value): string
    {
        $singular = [
            '/(quiz)zes$/i' => '$1',
            '/(matr)ices$/i' => '$1ix',
            '/(vert|ind)ices$/i' => '$1ex',
            '/^(ox)en$/i' => '$1',
            '/(alias)es$/i' => '$1',
            '/(octop|vir)i$/i' => '$1us',
            '/(cris|ax|test)es$/i' => '$1is',
            '/(shoe)s$/i' => '$1',
            '/(o)es$/i' => '$1',
            '/(bus)es$/i' => '$1',
            '/([m|l])ice$/i' => '$1ouse',
            '/(x|ch|ss|sh)es$/i' => '$1',
            '/(m)ovies$/i' => '$1ovie',
            '/(s)eries$/i' => '$1eries',
            '/([^aeiouy]|qu)ies$/i' => '$1y',
            '/([lr])ves$/i' => '$1f',
            '/(tive)s$/i' => '$1',
            '/(hive)s$/i' => '$1',
            '/(li|wi|kni)ves$/i' => '$1fe',
            '/(shea|loa|lea|thie)ves$/i' => '$1f',
            '/(^analy)ses$/i' => '$1sis',
            '/((a)naly|(b)a|(d)iagno|(p)arenthe|(p)rogno|(s)ynop|(t)he)ses$/i' => '$1$2sis',
            '/([ti])a$/i' => '$1um',
            '/(n)ews$/i' => '$1ews',
            '/(h|bl)ouses$/i' => '$1ouse',
            '/(corpse)s$/i' => '$1',
            '/(us)es$/i' => '$1',
            '/s$/i' => '',
        ];

        foreach ($singular as $pattern => $replacement) {
            if (preg_match($pattern, $value)) {
                return preg_replace($pattern, $replacement, $value);
            }
        }

        return $value;
    }

    /**
     * Convert string's encoding.
     */
    public static function toEncoding(string $string, string $toEncoding, string $fromEncoding = 'UTF-8'): string
    {
        return mb_convert_encoding($string, $toEncoding, $fromEncoding);
    }

    /**
     * Mask a portion of a string with a repeated character.
     */
    public static function mask(string $string, string $character, int $index, ?int $length = null, string $encoding = 'UTF-8'): string
    {
        if ($character === '') {
            return $string;
        }

        $segment = mb_substr($string, $index, $length, $encoding);

        if ($segment === '') {
            return $string;
        }

        $strlen = mb_strlen($string, $encoding);
        $startIndex = $index;

        if ($index < 0) {
            $startIndex = $index < -$strlen ? 0 : $strlen + $index;
        }

        $start = mb_substr($string, 0, $startIndex, $encoding);
        $segmentLen = mb_strlen($segment, $encoding);
        $end = mb_substr($string, $startIndex + $segmentLen);

        return $start . str_repeat(mb_substr($character, 0, 1, $encoding), $segmentLen) . $end;
    }

    /**
     * Trim the string of the given characters.
     */
    public static function trim(string $value, ?string $charlist = null): string
    {
        return trim($value, $charlist ?? " \t\n\r\0\x0B");
    }

    /**
     * Left trim the string of the given characters.
     */
    public static function ltrim(string $value, ?string $charlist = null): string
    {
        return ltrim($value, $charlist ?? " \t\n\r\0\x0B");
    }

    /**
     * Right trim the string of the given characters.
     */
    public static function rtrim(string $value, ?string $charlist = null): string
    {
        return rtrim($value, $charlist ?? " \t\n\r\0\x0B");
    }

    /**
     * Get the string matching the given pattern.
     */
    public static function match(string $pattern, string $subject): string
    {
        preg_match($pattern, $subject, $matches);

        return $matches[1] ?? $matches[0] ?? '';
    }

    /**
     * Get all strings matching the given pattern.
     */
    public static function matchAll(string $pattern, string $subject): array
    {
        preg_match_all($pattern, $subject, $matches);

        return $matches[1] ?? $matches[0] ?? [];
    }

    /**
     * Parse a Class@method style callback into class and method.
     */
    public static function parseCallback(string $callback, ?string $default = null): array
    {
        return static::contains($callback, '@') ? explode('@', $callback, 2) : [$callback, $default];
    }

    /**
     * Get the plural form of an English word based on count.
     */
    public static function pluralStudly(string $value, int $count = 2): string
    {
        $parts = preg_split('/(.)(?=[A-Z])/u', $value, -1, PREG_SPLIT_DELIM_CAPTURE);

        $lastWord = array_pop($parts);

        return implode('', $parts) . static::studly(static::plural($lastWord, $count));
    }

    /**
     * Get a new stringable object from the given string.
     */
    public static function of(string $string): Stringable
    {
        return new Stringable($string);
    }
}

/**
 * Fluent string manipulation class
 */
class Stringable
{
    protected string $value;

    public function __construct(string $value = '')
    {
        $this->value = $value;
    }

    public function camel(): self
    {
        $this->value = Str::camel($this->value);
        return $this;
    }

    public function studly(): self
    {
        $this->value = Str::studly($this->value);
        return $this;
    }

    public function snake(string $delimiter = '_'): self
    {
        $this->value = Str::snake($this->value, $delimiter);
        return $this;
    }

    public function kebab(): self
    {
        $this->value = Str::kebab($this->value);
        return $this;
    }

    public function title(): self
    {
        $this->value = Str::title($this->value);
        return $this;
    }

    public function lower(): self
    {
        $this->value = Str::lower($this->value);
        return $this;
    }

    public function upper(): self
    {
        $this->value = Str::upper($this->value);
        return $this;
    }

    public function limit(int $limit = 100, string $end = '...'): self
    {
        $this->value = Str::limit($this->value, $limit, $end);
        return $this;
    }

    public function slug(string $separator = '-', ?string $language = 'en'): self
    {
        $this->value = Str::slug($this->value, $separator, $language);
        return $this;
    }

    public function trim(?string $charlist = null): self
    {
        $this->value = Str::trim($this->value, $charlist);
        return $this;
    }

    public function append(string $values): self
    {
        $this->value .= $values;
        return $this;
    }

    public function prepend(string $values): self
    {
        $this->value = $values . $this->value;
        return $this;
    }

    public function replace(string|array $search, string|array $replace): self
    {
        $this->value = Str::replace($search, $replace, $this->value);
        return $this;
    }

    public function contains(string|array $needles, bool $ignoreCase = false): bool
    {
        return Str::contains($this->value, $needles, $ignoreCase);
    }

    public function startsWith(string|array $needles): bool
    {
        return Str::startsWith($this->value, $needles);
    }

    public function endsWith(string|array $needles): bool
    {
        return Str::endsWith($this->value, $needles);
    }

    public function length(): int
    {
        return Str::length($this->value);
    }

    public function substr(int $start, ?int $length = null): self
    {
        $this->value = Str::substr($this->value, $start, $length);
        return $this;
    }

    public function ucfirst(): self
    {
        $this->value = Str::ucfirst($this->value);
        return $this;
    }

    public function value(): string
    {
        return $this->value;
    }

    public function toString(): string
    {
        return $this->value();
    }

    public function __toString(): string
    {
        return $this->value();
    }

    public function jsonSerialize(): string
    {
        return $this->value();
    }
}
