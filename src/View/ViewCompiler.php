<?php

declare(strict_types=1);

namespace Plugs\View;

/*
|--------------------------------------------------------------------------
| ViewCompiler Class
|--------------------------------------------------------------------------
|
| This class is responsible for compiling view templates into executable PHP code.
| It processes various directives such as conditionals, loops, includes, sections,
| and more, transforming them into valid PHP syntax.
| It also manages component extraction and slot handling for reusable UI elements.
|
| Represents a view compiler that transforms template syntax into PHP code.
|
| Compiles template syntax into executable PHP code.
| Handles directives, components, loops, conditionals, and more.
| @package Plugs\View
*/

class ViewCompiler
{
    /**
     * Stack for tracking nested sections
     */
    private array $sectionStack = [];

    /**
     * Stack for tracking nested components
     */
    private array $componentStack = [];

    /**
     * Storage for component slot data
     */
    private array $componentData = [];

    /**
     * Reference to view engine
     */
    private ?ViewEngine $viewEngine;

    /**
     * Compilation cache for performance
     */
    private array $compilationCache = [];
    private array $customDirectives = [];

    /**
     * Maximum cache size to prevent memory bloat
     */
    private const MAX_CACHE_SIZE = 1000;

    /**
     * Create a new ViewCompiler instance
     *
     * @param ViewEngine|null $viewEngine Optional view engine reference
     */
    public function __construct(?ViewEngine $viewEngine = null)
    {
        $this->viewEngine = $viewEngine;
    }

    public function registerCustomDirective(string $name, callable $handler): void
    {
        $this->customDirectives[$name] = $handler;
    }

    public function getCustomDirectives(): array
    {
        return array_keys($this->customDirectives);
    }

    /**
     * Compile template content to PHP code
     *
     * @param string $content Raw template content
     * @return string Compiled PHP code
     */
    public function compile(string $content): string
    {
        $cacheKey = md5($content);

        if (isset($this->compilationCache[$cacheKey])) {
            return $this->compilationCache[$cacheKey];
        }

        // Phase 1: Extract and process components with slots
        $content = $this->extractComponentsWithSlots($content);

        // Phase 2: Compile all other directives
        $content = $this->compileNonComponentContent($content);

        // Cache the result
        $this->cacheCompilation($cacheKey, $content);

        return $content;
    }

    /**
     * Get compiled slot content by ID
     *
     * @param string $slotId Unique slot identifier
     * @return string Compiled slot content
     */
    public function getCompiledSlot(string $slotId): string
    {
        if (isset($this->componentData[$slotId])) {
            $slotContent = $this->componentData[$slotId];
            return $this->compileNonComponentContent($slotContent);
        }

        return '';
    }

    /**
     * Clear compilation cache
     *
     * @return void
     */
    public function clearCache(): void
    {
        $this->compilationCache = [];
        $this->componentData = [];
    }

    /**
     * Extract components and their slots, replacing with placeholders
     *
     * @param string $content Template content
     * @return string Content with component placeholders
     */
    private function extractComponentsWithSlots(string $content): string
    {
        // Process self-closing components: <ComponentName attr="value" />
        $content = preg_replace_callback(
            '/<([A-Z][a-zA-Z0-9]*)((?:\s+[^>]*?)?)\/>/s',
            function ($matches) {
                $componentName = $matches[1];
                $attributes = $matches[2] ?? '';
                return $this->createComponentPlaceholder($componentName, trim($attributes), '');
            },
            $content
        );

        // Process components with content: <ComponentName>...</ComponentName>
        $content = preg_replace_callback(
            '/<([A-Z][a-zA-Z0-9]*)((?:\s+[^>]*?)?)>(.*?)<\/\1\s*>/s',
            function ($matches) {
                $componentName = $matches[1];
                $attributes = $matches[2] ?? '';
                $slotContent = $matches[3] ?? '';
                return $this->createComponentPlaceholder($componentName, trim($attributes), $slotContent);
            },
            $content
        );

        return $content;
    }

    /**
     * Create component placeholder with data
     *
     * @param string $componentName Component name
     * @param string $attributes Raw attributes string
     * @param string $slotContent Slot content
     * @return string PHP component render call
     */
    private function createComponentPlaceholder(string $componentName, string $attributes, string $slotContent): string
    {
        $attributesArray = $this->parseAttributes($attributes);
        $dataPhp = $this->buildDataArray($attributesArray);

        // Store uncompiled slot content if present
        if (!empty(trim($slotContent))) {
            $slotId = uniqid('slot_', true);
            $this->componentData[$slotId] = $slotContent;

            if (!empty($dataPhp)) {
                $dataPhp .= ', ';
            }
            $dataPhp .= sprintf("'__slot_id' => '%s'", $slotId);
        }

        return sprintf(
            '<?php echo $view->renderComponent(\'%s\', [%s]); ?>',
            addslashes($componentName),
            $dataPhp
        );
    }

    /**
     * Compile all non-component template features
     *
     * @param string $content Template content
     * @return string Compiled content
     */
    private function compileNonComponentContent(string $content): string
    {
        // Order matters - compile in correct sequence
        $content = $this->compileComments($content);
        $content = $this->compilePhp($content);
        $content = $this->compileRawEchos($content);
        $content = $this->compileEscapedEchos($content);
        $content = $this->compileCustomDirectives($content);
        $content = $this->compileConditionals($content);
        $content = $this->compileLoops($content);
        $content = $this->compileIncludes($content);
        $content = $this->compileSections($content);
        $content = $this->compileStacks($content);
        $content = $this->compileOnce($content);
        $content = $this->compileErrorDirectives($content);
        $content = $this->compileCsrf($content);
        $content = $this->compileMethod($content);
        $content = $this->compileJson($content);

        return $content;
    }

    /**
     * Parse HTML-style attributes into structured array
     *
     * @param string $attributes Raw attributes string
     * @return array Parsed attributes with metadata
     */
    private function parseAttributes(string $attributes): array
    {
        $result = [];
        $attributes = trim($attributes);

        if (empty($attributes)) {
            return $result;
        }

        // Handle template expressions {{ }} and {{{ }}}
        $expressionMap = [];
        $expressionCounter = 0;

        $attributes = preg_replace_callback(
            '/\{\{\{?\s*(.+?)\s*\}\}\}?/s',
            function ($matches) use (&$expressionMap, &$expressionCounter) {
                $placeholder = sprintf('___EXPR_%d___', $expressionCounter);
                $expressionMap[$placeholder] = trim($matches[1]);
                $expressionCounter++;
                return $placeholder;
            },
            $attributes
        );

        // Match quoted attributes: attr="value" or attr='value'
        preg_match_all(
            '/(\w+)\s*=\s*(["\'])((?:[^\2\\\\]|\\\\.)*)\2/s',
            $attributes,
            $matches,
            PREG_SET_ORDER
        );

        foreach ($matches as $match) {
            $key = $match[1];
            $value = $match[3];
            $hasExpression = false;

            // Unescape quotes
            $value = str_replace('\\' . $match[2], $match[2], $value);

            foreach ($expressionMap as $placeholder => $expression) {
                if (strpos((string) $value, (string) $placeholder) !== false) {
                    $value = str_replace((string) $placeholder, (string) $expression, (string) $value);
                    $hasExpression = true;
                }
            }

            $result[$key] = [
                'value' => $value,
                'quoted' => true,
                'is_variable' => $hasExpression
            ];
        }

        // Match unquoted variable attributes: attr=$variable
        preg_match_all('/(\w+)\s*=\s*(\$[\w\[\]\'\"\-\>]+)(?=\s|$|\/)/s', $attributes, $matches, PREG_SET_ORDER);

        foreach ($matches as $match) {
            if (!isset($result[$match[1]])) {
                $result[$match[1]] = [
                    'value' => $match[2],
                    'quoted' => false,
                    'is_variable' => true
                ];
            }
        }

        // Match boolean attributes
        $withoutQuoted = preg_replace('/\w+\s*=\s*(["\'].*?\1|\$[\w\[\]\'\"\-\>]+)/s', '', $attributes);
        preg_match_all('/(\w+)/', $withoutQuoted, $matches);

        foreach ($matches[1] as $attr) {
            if (!isset($result[$attr]) && !empty(trim($attr)) && !preg_match('/^___EXPR_\d+___$/', $attr)) {
                $result[$attr] = [
                    'value' => 'true',
                    'quoted' => false,
                    'is_variable' => false
                ];
            }
        }

        return $result;
    }

    /**
     * Build PHP array code from parsed attributes
     *
     * @param array $attributes Parsed attributes
     * @return string PHP array code
     */
    private function buildDataArray(array $attributes): string
    {
        $parts = [];

        foreach ($attributes as $key => $info) {
            $value = $info['value'];
            $isVariable = $info['is_variable'];

            if ($isVariable) {
                $parts[] = sprintf("'%s' => %s", addslashes($key), $value);
            } else {
                $parts[] = sprintf("'%s' => '%s'", addslashes($key), addslashes($value));
            }
        }

        return implode(', ', $parts);
    }

    /**
     * Compile comment syntax: {{-- comment --}}
     */
    private function compileComments(string $content): string
    {
        return preg_replace('/\{\{--(.+?)--\}\}/s', '', $content);
    }

    /**
     * Compile raw echo syntax: {{{ $var }}}
     */
    private function compileRawEchos(string $content): string
    {
        return preg_replace(
            '/\{\{\{\s*(.+?)\s*\}\}\}/s',
            '<?php echo $1; ?>',
            $content
        );
    }

    /**
     * Compile escaped echo syntax: {{ $var }}
     */
    private function compileEscapedEchos(string $content): string
    {
        return preg_replace(
            '/\{\{\s*(.+?)\s*\}\}/s',
            '<?php echo htmlspecialchars((string)($1), ENT_QUOTES, \'UTF-8\'); ?>',
            $content
        );
    }

    /**
     * Compile conditional directives
     */
    private function compileConditionals(string $content): string
    {
        $content = preg_replace('/\@if\s*\((.+?)\)/s', '<?php if ($1): ?>', $content);
        $content = preg_replace('/\@elseif\s*\((.+?)\)/s', '<?php elseif ($1): ?>', $content);
        $content = preg_replace('/\@else\b/', '<?php else: ?>', $content);
        $content = preg_replace('/\@endif\b/', '<?php endif; ?>', $content);
        $content = preg_replace('/\@unless\s*\((.+?)\)/s', '<?php if (!($1)): ?>', $content);
        $content = preg_replace('/\@endunless\b/', '<?php endif; ?>', $content);
        $content = preg_replace('/\@isset\s*\((.+?)\)/s', '<?php if (isset($1)): ?>', $content);
        $content = preg_replace('/\@endisset\b/', '<?php endif; ?>', $content);
        $content = preg_replace('/\@empty\s*\((.+?)\)/s', '<?php if (empty($1)): ?>', $content);
        $content = preg_replace('/\@endempty\b/', '<?php endif; ?>', $content);

        return $content;
    }

    /**
     * Compile loop directives
     */
    private function compileLoops(string $content): string
    {
        // Foreach with safety checks
        $content = preg_replace_callback(
            '/\@foreach\s*\((.+?)\s+as\s+(.+?)\)/s',
            function ($matches) {
                $array = trim($matches[1]);
                $iteration = trim($matches[2]);

                // Extract base variable name for safety check
                // Handle: $var, $obj->prop, $arr['key'], $this->method()
                if (preg_match('/^(\$[\w]+)/', $array, $varMatch)) {
                    $varName = $varMatch[1];
                } else {
                    // Fallback for complex expressions
                    $varName = $array;
                }

                return sprintf(
                    '<?php if(isset(%s) && is_iterable(%s)): foreach (%s as %s): ?>',
                    $varName,
                    $array,
                    $array,
                    $iteration
                );
            },
            $content
        );

        $content = preg_replace('/\@endforeach\b/', '<?php endforeach; endif; ?>', $content);
        $content = preg_replace('/\@for\s*\((.+?)\)/s', '<?php for ($1): ?>', $content);
        $content = preg_replace('/\@endfor\b/', '<?php endfor; ?>', $content);
        $content = preg_replace('/\@while\s*\((.+?)\)/s', '<?php while ($1): ?>', $content);
        $content = preg_replace('/\@endwhile\b/', '<?php endwhile; ?>', $content);

        // Continue and break with optional conditions
        $content = preg_replace_callback(
            '/\@continue(?:\s*\((.+?)\))?/s',
            function ($matches) {
                if (isset($matches[1]) && !empty(trim($matches[1]))) {
                    return sprintf('<?php if (%s) continue; ?>', $matches[1]);
                }
                return '<?php continue; ?>';
            },
            $content
        );

        $content = preg_replace_callback(
            '/\@break(?:\s*\((.+?)\))?/s',
            function ($matches) {
                if (isset($matches[1]) && !empty(trim($matches[1]))) {
                    return sprintf('<?php if (%s) break; ?>', $matches[1]);
                }
                return '<?php break; ?>';
            },
            $content
        );

        return $content;
    }

    /**
     * Compile include directives
     */
    private function compileIncludes(string $content): string
    {
        return preg_replace_callback(
            '/\@include\s*\([\'"](.+?)[\'"]\s*(?:,\s*(\[.+?\]))?\s*\)/s',
            function ($matches) {
                $view = $matches[1];

                // Basic validation
                if (strpos($view, '..') !== false || strpos($view, DIRECTORY_SEPARATOR) === 0) {
                    // Let getViewPath handle validation, but flag obvious issues
                    return sprintf('<?php /* Invalid include path: %s */ ?>', htmlspecialchars($view));
                }

                $view = addslashes($view);
                $data = $matches[2] ?? '[]';

                return sprintf(
                    '<?php if (isset($view)) { echo $view->render(\'%s\', array_merge(get_defined_vars(), (array)%s)); } ?>',
                    $view,
                    $data
                );
            },
            $content
        );
    }

    /**
     * Compile section and inheritance directives
     */
    private function compileSections(string $content): string
    {
        // @extends directive
        $content = preg_replace_callback(
            '/\@extends\s*\([\'"](.+?)[\'"]\)/',
            function ($matches) {
                return sprintf('<?php $__extends = \'%s\'; ?>', addslashes($matches[1]));
            },
            $content
        );

        // @section with inline content
        $content = preg_replace_callback(
            '/\@section\s*\([\'"](.+?)[\'"]\s*,\s*[\'"](.+?)[\'"]\)/',
            function ($matches) {
                $name = addslashes($matches[1]);
                $sectionContent = addslashes($matches[2]);
                return sprintf('<?php $__sections[\'%s\'] = \'%s\'; ?>', $name, $sectionContent);
            },
            $content
        );

        // @section block start
        $content = preg_replace_callback(
            '/\@section\s*\([\'"](.+?)[\'"]\)/',
            function ($matches) {
                $name = addslashes($matches[1]);
                return sprintf('<?php $__currentSection = \'%s\'; ob_start(); ?>', $name);
            },
            $content
        );

        $content = preg_replace(
            '/\@endsection\b/',
            '<?php if (isset($__currentSection)) { $__sections[$__currentSection] = ob_get_clean(); unset($__currentSection); } ?>',
            $content
        );

        // Add @append directive
        $content = preg_replace_callback(
            '/\@append\s*\([\'"](.+?)[\'"]\)/',
            function ($matches) {
                $name = addslashes($matches[1]);
                return sprintf(
                    '<?php $__currentSection = \'%s\'; $__appendMode = true; ob_start(); ?>',
                    $name
                );
            },
            $content
        );

        $content = preg_replace(
            '/\@endappend\b/',
            '<?php if (isset($__currentSection)) { ' .
            '$__sections[$__currentSection] = ($__sections[$__currentSection] ?? \'\') . ob_get_clean(); ' .
            'unset($__currentSection, $__appendMode); } ?>',
            $content
        );

        $content = preg_replace(
            '/\@show\b/',
            '<?php if (isset($__currentSection)) { $__sections[$__currentSection] = ob_get_clean(); echo $__sections[$__currentSection]; unset($__currentSection); } ?>',
            $content
        );

        // @yield directive
        $content = preg_replace_callback(
            '/\@yield\s*\([\'"](.+?)[\'"]\s*(?:,\s*[\'"]?(.*?)[\'"]?)?\)/',
            function ($matches) {
                $section = addslashes($matches[1]);
                $default = isset($matches[2]) ? addslashes($matches[2]) : '';
                return sprintf('<?php echo $__sections[\'%s\'] ?? \'%s\'; ?>', $section, $default);
            },
            $content
        );

        $content = preg_replace('/\@parent\b/', '<?php echo $__parentContent ?? \'\'; ?>', $content);

        return $content;
    }

    /**
     * Compile stack directives for asset management
     */
    private function compileStacks(string $content): string
    {
        // @push directive
        $content = preg_replace_callback(
            '/\@push\s*\(\s*[\'"](.+?)[\'"]\s*\)/',
            function ($matches) {
                $stackName = addslashes($matches[1]);
                return sprintf('<?php $__currentStack = \'%s\'; ob_start(); ?>', $stackName);
            },
            $content
        );

        $content = preg_replace(
            '/\@endpush\b/',
            '<?php if (isset($__currentStack)) { ' .
            'if (!isset($__stacks[$__currentStack])) { $__stacks[$__currentStack] = []; } ' .
            '$__stacks[$__currentStack][] = ob_get_clean(); unset($__currentStack); } ?>',
            $content
        );

        // @prepend directive
        $content = preg_replace_callback(
            '/\@prepend\s*\(\s*[\'"](.+?)[\'"]\s*\)/',
            function ($matches) {
                $stackName = addslashes($matches[1]);
                return sprintf('<?php $__currentStack = \'%s\'; $__isPrepend = true; ob_start(); ?>', $stackName);
            },
            $content
        );

        $content = preg_replace(
            '/\@endprepend\b/',
            '<?php if (isset($__currentStack)) { ' .
            'if (!isset($__stacks[$__currentStack])) { $__stacks[$__currentStack] = []; } ' .
            'array_unshift($__stacks[$__currentStack], ob_get_clean()); unset($__currentStack, $__isPrepend); } ?>',
            $content
        );

        // @stack directive
        $content = preg_replace_callback(
            '/\@stack\s*\(\s*[\'"](.+?)[\'"]\s*\)/',
            function ($matches) {
                $stackName = addslashes($matches[1]);
                return sprintf('<?php echo implode(\'\', $__stacks[\'%s\'] ?? []); ?>', $stackName);
            },
            $content
        );

        return $content;
    }

    /**
     * Compile CSRF token directive
     */
    private function compileCsrf(string $content): string
    {
        return preg_replace(
            '/\@csrf\b/',
            '<?php echo function_exists(\'csrf_field\') ? csrf_field() : ' .
            '\'<input type="hidden" name="_token" value="\' . ($_SESSION[\'_csrf_token\'] ?? \'\') . \'">\'; ?>',
            $content
        );
    }

    /**
     * Compile method field directive
     */
    private function compileMethod(string $content): string
    {
        return preg_replace(
            '/\@method\s*\([\'"](.+?)[\'"]\)/',
            '<?php echo \'<input type="hidden" name="_method" value="$1">\'; ?>',
            $content
        );
    }

    /**
     * Compile PHP block directives
     */
    private function compilePhp(string $content): string
    {
        $content = preg_replace('/\@php\b(.*?)\@endphp\b/s', '<?php $1 ?>', $content);
        $content = preg_replace('/\@php\s*\((.+?)\)/s', '<?php $1; ?>', $content);

        return $content;
    }

    /**
     * Compile @once directive for single execution blocks
     */
    private function compileOnce(string $content): string
    {
        return preg_replace_callback(
            '/\@once\b(.*?)\@endonce\b/s',
            function ($matches) {
                $id = md5($matches[1]);
                return sprintf(
                    '<?php if (!isset($__once_%s)): $__once_%s = true; ?>%s<?php endif; ?>',
                    $id,
                    $id,
                    $matches[1]
                );
            },
            $content
        );
    }

    /**
     * Compile error handling directives
     */
    private function compileErrorDirectives(string $content): string
    {
        $content = preg_replace(
            '/\@error\s*\(\s*[\'"](.+?)[\'"]\s*\)/',
            '<?php if (isset($errors) && $errors->has(\'$1\')): ?>',
            $content
        );

        $content = preg_replace('/\@enderror\b/', '<?php endif; ?>', $content);

        return $content;
    }

    /**
     * Compile JSON output directive
     */
    private function compileJson(string $content): string
    {
        return preg_replace(
            '/\@json\s*\((.+?)\)/',
            '<?php echo json_encode($1, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_QUOT); ?>',
            $content
        );
    }

    private function compileCustomDirectives(string $content): string
    {
        foreach ($this->customDirectives as $name => $handler) {
            $content = preg_replace_callback(
                '/\@' . preg_quote($name, '/') . '(?:\s*\((.*?)\))?/s',
                function ($matches) use ($handler) {
                    $expression = $matches[1] ?? null;
                    return call_user_func($handler, $expression);
                },
                $content
            );
        }

        return $content;
    }

    /**
     * Cache compiled content with size limit
     *
     * @param string $key Cache key
     * @param string $content Compiled content
     * @return void
     */
    private function cacheCompilation(string $key, string $content): void
    {
        if (count($this->compilationCache) >= self::MAX_CACHE_SIZE) {
            // Keep most recent 500 entries (remove oldest 500)
            $this->compilationCache = array_slice($this->compilationCache, -500, null, true);
        }

        $this->compilationCache[$key] = $content;
    }
}
