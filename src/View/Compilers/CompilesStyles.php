<?php

declare(strict_types=1);

namespace Plugs\View\Compilers;

/**
 * Trait CompilesStyles
 * 
 * Handles compilation of CSS-related directives like @theme, @tw, @class, and @style.
 */
trait CompilesStyles
{
    /**
     * Compile the @theme directive.
     * 
     * @param string $content
     * @return string
     */
    protected function compileTheme(string $content): string
    {
        return preg_replace_callback('/@theme\s*\((.+?)\)/s', function ($matches) {
            return "<?php 
                \$themeData = {$matches[1]};
                echo '<style>:root {';
                foreach (\$themeData as \$key => \$value) {
                    echo '--' . htmlspecialchars((string)\$key) . ': ' . htmlspecialchars((string)\$value) . ';';
                }
                echo '}</style>';
            ?>";
        }, $content) ?? $content;
    }

    /**
     * Compile the @tw directive.
     * 
     * @param string $content
     * @return string
     */
    protected function compileTw(string $content): string
    {
        return preg_replace_callback('/@tw\s*\((.+?)\)/s', function ($matches) {
            return "class=\"<?php echo \Plugs\View\ComponentAttributes::resolveClass({$matches[1]}); ?>\"";
        }, $content) ?? $content;
    }

    /**
     * Compile the @class directive.
     * 
     * @param string $content
     * @return string
     */
    protected function compileClass(string $content): string
    {
        return preg_replace_callback('/@class\s*\((.+?)\)/s', function ($matches) {
            return "class=\"<?php echo \Plugs\View\ComponentAttributes::escapeClass(\Plugs\View\ComponentAttributes::resolveClass({$matches[1]})); ?>\"";
        }, $content) ?? $content;
    }

    /**
     * Compile the @style directive.
     * 
     * @param string $content
     * @return string
     */
    protected function compileStyle(string $content): string
    {
        return preg_replace_callback('/@style\s*\((.+?)\)/s', function ($matches) {
            return "style=\"<?php echo \Plugs\View\ComponentAttributes::escapeStyle(\Plugs\View\ComponentAttributes::resolveStyle({$matches[1]})); ?>\"";
        }, $content) ?? $content;
    }

    /**
     * Compile the @plugcss directive.
     * 
     * @param string $content
     * @return string
     */
    protected function compilePlugCss(string $content): string
    {
        return preg_replace('/@plugcss\b/', '<?php echo \Plugs\Css\CssCompiler::linkTag(); ?>', $content) ?? $content;
    }
}
