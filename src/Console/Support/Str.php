<?php

declare(strict_types=1);

namespace Plugs\Console\Support;

/*
|--------------------------------------------------------------------------
| Str Class
|--------------------------------------------------------------------------
*/

class Str
{
    public static function studly(string $name): string
    {
        return str_replace(' ', '', ucwords(str_replace(['-', '_'], ' ', $name)));
    }

    public static function snake(string $name): string
    {
        $name = preg_replace('/(.)([A-Z])/u', '$1_$2', $name);
        return strtolower((string)$name);
    }

    public static function pluralStudly(string $name): string
    {
        $studly = self::studly($name);
        return self::pluralize($studly);
    }

    private static function pluralize(string $word): string
    {
        // Basic pluralization rules
        $irregular = [
            'child' => 'children',
            'man' => 'men',
            'woman' => 'women',
            'person' => 'people',
            'mouse' => 'mice',
            'goose' => 'geese',
            'foot' => 'feet',
            'tooth' => 'teeth',
            'ox' => 'oxen'
        ];
        
        $uncountable = [
            'sheep', 'deer', 'fish', 'series', 'species', 'money', 'information', 'rice'
        ];

        $lowerWord = strtolower($word);
        
        // Check for irregular plurals
        foreach ($irregular as $singular => $plural) {
            if ($lowerWord === $singular) {
                return $plural;
            }
            if ($lowerWord === $plural) {
                return $word; // Already plural
            }
        }
        
        // Check for uncountable nouns
        if (in_array($lowerWord, $uncountable)) {
            return $word;
        }
        
        // Apply common pluralization rules
        $rules = [
            '/(s)tatus$/i' => '\1\2tatuses',
            '/(quiz)$/i' => '\1zes',
            '/^(ox)$/i' => '\1en',
            '/([m|l])ouse$/i' => '\1ice',
            '/(matr|vert|ind)(ix|ex)$/i' => '\1ices',
            '/(x|ch|ss|sh)$/i' => '\1es',
            '/([^aeiouy]|qu)y$/i' => '\1ies',
            '/(hive)$/i' => '\1s',
            '/(?:([^f])fe|([lr])f)$/i' => '\1\2ves',
            '/sis$/i' => 'ses',
            '/([ti])um$/i' => '\1a',
            '/(buffal|tomat)o$/i' => '\1oes',
            '/(bu)s$/i' => '\1ses',
            '/(alias|status)$/i' => '\1es',
            '/(octop|vir)us$/i' => '\1i',
            '/(ax|test)is$/i' => '\1es',
            '/s$/i' => 's',
            '/$/' => 's'
        ];
        
        foreach ($rules as $rule => $replacement) {
            if (preg_match($rule, $word)) {
                return preg_replace($rule, $replacement, $word);
            }
        }
        
        // Default: just add 's'
        return $word . 's';
    }
}