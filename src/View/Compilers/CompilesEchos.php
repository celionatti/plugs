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
        return preg_replace_callback(self::$patterns['escaped_echo'], function ($matches) {
            $expr = trim($matches[1]);
            if ($expr === '') {
                return '';
            }
            return "<?php echo \Plugs\View\Escaper::html({$expr}); ?>";
        }, $content);
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
                $expression = $matches[4];

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
}
