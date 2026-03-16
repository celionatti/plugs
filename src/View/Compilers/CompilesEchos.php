<?php

declare(strict_types=1);

namespace Plugs\View\Compilers;

trait CompilesEchos
{
    /**
     * Compile the echo statements in the given string.
     *
     * @param  string  $content
     * @return string
     */
    protected function compileRawEchos(string $content): string
    {
        return preg_replace_callback(self::$patterns['raw_echo'], function ($matches) {
            $expr = trim($matches[1]);
            if ($expr === '') {
                return '';
            }
            $expr = $this->convertDotSyntax($expr);
            return "<?php echo {$expr}; ?>";
        }, $content);
    }

    /**
     * Compile the escaped echo statements in the given string.
     *
     * @param  string  $content
     * @return string
     */
    protected function compileEscapedEchos(string $content): string
    {
        $callback = function ($matches) {
            $expr = trim($matches[1]);
            if ($expr === '') {
                return '';
            }
            $expr = $this->convertDotSyntax($expr);
            return "<?php echo \Plugs\View\Escaper::html({$expr}); ?>";
        };

        $content = preg_replace_callback(self::$patterns['escaped_echo'], $callback, $content);
        $content = preg_replace_callback(self::$patterns['inline_echo'], $callback, $content);

        // Strip `@` escapes that were used to bypass compilation (e.g. @{{ obj.foo }} -> {{ obj.foo }})
        $content = preg_replace('/@(\{+.*?\}+)/s', '$1', $content);
        
        return $content;
    }

    /**
     * Compile the curly braces statements in the given string.
     *
     * @param  string  $content
     * @return string
     */
    protected function compileComments(string $content): string
    {
        // Simple comments: {{-- comment --}}
        $content = preg_replace('/\{\{--.*?--\}\}/s', '', $content);

        // HTML-style hidden comments: <!--# comment #-->
        return preg_replace('/<!--#.*?#-->/s', '', $content);
    }

    /**
     * Compile short-attribute binding: :class="$active ? 'a' : 'b'"
     */
    protected function compileShortAttributes(string $content): string
    {
        return preg_replace_callback(
            '/\s:([\w-]+)=((["\'])(.*?)\3)/s',
            function ($matches) {
                $attr = $matches[1];
                $expression = $this->convertDotSyntax($matches[4]);

                return sprintf(' %s="<?php echo \Plugs\View\Escaper::attr(%s); ?>"', $attr, $expression);
            },
            $content
        );
    }

    /**
     * Compile the verbatim blocks in the given string.
     *
     * @param  string  $content
     * @return string
     */
    protected function compileVerbatim(string $content): string
    {
        $verbatimBlocks = [];
        $content = preg_replace_callback(
            '/@verbatim(.*?)@endverbatim/s',
            function ($matches) use (&$verbatimBlocks) {
                $placeholder = '___VERBATIM_' . md5($matches[1]) . '___';
                $verbatimBlocks[$placeholder] = $matches[1];

                return $placeholder;
            },
            $content
        );

        $this->verbatimBlocks = array_merge($this->verbatimBlocks, $verbatimBlocks);

        return $content;
    }

    /**
     * Compile the PHP statements in the given string.
     *
     * @param  string  $content
     * @return string
     */
    protected function compilePhp(string $content): string
    {
        // Multi-line PHP blocks: @php ... @endphp
        $content = preg_replace_callback(
            '/@php\s*\n?(.*?)\n?\s*@endphp/s',
            function ($matches) {
                $code = trim($matches[1]);

                return "<?php {$code} ?>";
            },
            $content
        );

        // Inline PHP: @php($expression)
        $content = preg_replace('/@php\s*\((.+?)\)/s', '<?php $1; ?>', $content);

        return $content;
    }

    /**
     * Convert JS-style dot notation and standalone variable names into PHP syntax.
     * Example: "user.name" -> "$user->name", "isActive" -> "$isActive".
     *
     * @param string $expression
     * @return string
     */
    protected function convertDotSyntax(string $expression): string
    {
        // Split by strings (single and double quotes) to avoid replacing inside them
        $parts = preg_split('/(["\'].*?["\'])/s', $expression, -1, PREG_SPLIT_DELIM_CAPTURE);

        foreach ($parts as $i => $part) {
            // Only process unquoted parts (even indices)
            if ($i % 2 === 0) {
                // 1. Prefix variables with $
                // Look for standalone words. 
                // Negative lookbehind: Not preceded by $, word character, :, -, >, ., or \ 
                // Word boundary \b to prevent partial matches and backtracking.
                // Negative lookahead: Not followed by an opening parenthesis (which means it's a function call)
                $part = preg_replace_callback('/(?<![\$\w:\->\.\\\\])\b([a-zA-Z_][a-zA-Z0-9_]*)\b(?!\s*\()/ism', function ($matches) {
                    $word = $matches[1];
                    
                    // Ignore PHP keywords/literals
                    $reserved = ['true', 'false', 'null', 'and', 'or', 'xor', 'new', 'clone', 'instanceof', 'empty', 'isset', 'yield'];
                    if (in_array(strtolower($word), $reserved, true)) {
                        return $word;
                    }
                    
                    return '$' . $word;
                }, $part);

                // 2. Convert dot notation `.property` into object access `->property`
                $part = preg_replace('/(?<![0-9])\.([a-zA-Z_][a-zA-Z0-9_]*)/ism', '->$1', $part);

                $parts[$i] = $part;
            }
        }

        return implode('', $parts);
    }
}
