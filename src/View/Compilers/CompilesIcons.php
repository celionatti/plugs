<?php

declare(strict_types=1);

namespace Plugs\View\Compilers;

/**
 * Trait CompilesIcons
 * 
 * Handles the @icon directive for injecting SVGs from resources/icons.
 */
trait CompilesIcons
{
    /**
     * Compile the @icon directive.
     * 
     * Usage: @icon('home', ['class' => 'w-5 h-5'])
     * 
     * @param string $content
     * @return string
     */
    protected function compileIcon(string $content): string
    {
        return preg_replace_callback('/@icon\s*\((.+?)\)/s', function ($matches) {
            return "<?php 
                echo (function(\$iconName, \$attributes = []) {
                    \$basePath = defined('BASE_PATH') ? BASE_PATH : getcwd();
                    \$iconPath = rtrim(\$basePath, '/\\\\') . DIRECTORY_SEPARATOR . 'resources' . DIRECTORY_SEPARATOR . 'icons' . DIRECTORY_SEPARATOR . \$iconName . '.svg';
                    
                    if (!file_exists(\$iconPath)) {
                        return '<!-- Icon not found: ' . htmlspecialchars(\$iconName) . ' -->';
                    }
                    
                    \$svg = file_get_contents(\$iconPath);
                    
                    // Merge attributes into the <svg> tag
                    if (!empty(\$attributes)) {
                        \$attrString = '';
                        foreach (\$attributes as \$key => \$value) {
                            \$attrString .= sprintf(' %s=\"%s\"', htmlspecialchars((string)\$key), htmlspecialchars((string)\$value));
                        }
                        
                        // Insert attributes before the first '>'
                        \$svg = preg_replace('/^<svg/i', '<svg' . \$attrString, \$svg);
                    }
                    
                    return \$svg;
                })({$matches[1]}); 
            ?>";
        }, $content) ?? $content;
    }
}
