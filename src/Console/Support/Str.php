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
        return self::pluralize(self::studly($name));
    }

    public static function pluralize(string $word): string
    {
        $irregular = [
            'child' => 'children',
            'person' => 'people',
            'man' => 'men',
            'woman' => 'women',
            'tooth' => 'teeth',
            'foot' => 'feet',
            'mouse' => 'mice',
            'goose' => 'geese',
        ];

        $lower = strtolower($word);
        if (isset($irregular[$lower])) {
            return $irregular[$lower];
        }

        $rules = [
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
        ];

        foreach ($rules as $pattern => $replacement) {
            if (preg_match($pattern, $word)) {
                return preg_replace($pattern, $replacement, $word);
            }
        }

        return $word . 's';
    }

    public static function camel(string $name): string
    {
        return lcfirst(self::studly($name));
    }

    public static function kebab(string $name): string
    {
        return str_replace('_', '-', self::snake($name));
    }
}