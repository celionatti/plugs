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
*/

class ViewCompiler
{
    private $sectionStack = [];
    private $componentStack = [];
    private $componentData = [];
    private $viewEngine;
    private $compilationCache = [];

    public function __construct(?ViewEngine $viewEngine = null)
    {
        $this->viewEngine = $viewEngine;
    }

    public function compile(string $content): string
    {
        $cacheKey = md5($content);

        if (isset($this->compilationCache[$cacheKey])) {
            return $this->compilationCache[$cacheKey];
        }

        $content = $this->extractComponentsWithSlots($content);
        $content = $this->compileNonComponentContent($content);

        $this->compilationCache[$cacheKey] = $content;

        return $content;
    }

    /**
     * Extract components and their slots, replacing them with placeholder calls
     */
    private function extractComponentsWithSlots(string $content): string
    {
        // Process self-closing components first
        $content = preg_replace_callback(
            '/<([A-Z][a-zA-Z0-9]*)((?:\s+[^>]*?)?)\/>/s',
            function ($matches) {
                $componentName = $matches[1];
                $attributes = isset($matches[2]) ? trim($matches[2]) : '';
                return $this->createComponentPlaceholder($componentName, $attributes, '');
            },
            $content
        );

        // Process components with slots
        $content = preg_replace_callback(
            '/<([A-Z][a-zA-Z0-9]*)((?:\s+[^>]*?)?)>(.*?)<\/\1\s*>/s',
            function ($matches) {
                $componentName = $matches[1];
                $attributes = isset($matches[2]) ? trim($matches[2]) : '';
                $slotContent = $matches[3] ?? '';
                return $this->createComponentPlaceholder($componentName, $attributes, $slotContent);
            },
            $content
        );

        return $content;
    }

    /**
     * Create a placeholder for component that includes slot content
     */
    private function createComponentPlaceholder(string $componentName, string $attributes, string $slotContent): string
    {
        $attributesArray = $this->parseAttributes($attributes);
        $dataPhp = $this->buildDataArray($attributesArray);

        // If there's slot content, compile it and store with unique ID
        if (!empty(trim($slotContent))) {
            $slotId = uniqid('slot_', true);

            // Store the UNCOMPILED slot content - will be compiled when rendered
            $this->componentData[$slotId] = $slotContent;

            if (!empty($dataPhp)) {
                $dataPhp .= ', ';
            }
            $dataPhp .= "'__slot_id' => '{$slotId}'";
        }

        return "<?php echo \$view->renderComponent('{$componentName}', [{$dataPhp}]); ?>";
    }

    /**
     * Get compiled slot content by ID
     */
    public function getCompiledSlot(string $slotId): string
    {
        if (isset($this->componentData[$slotId])) {
            $slotContent = $this->componentData[$slotId];
            // Compile the slot content (includes directives, variables, etc.)
            return $this->compileNonComponentContent($slotContent);
        }

        return '';
    }

    /**
     * Compile everything except components
     */
    private function compileNonComponentContent(string $content): string
    {
        $content = $this->compileComments($content);
        $content = $this->compilePhp($content);
        $content = $this->compileEchos($content);
        $content = $this->compileEscapedEchos($content);
        $content = $this->compileConditionals($content);
        $content = $this->compileLoops($content);
        $content = $this->compileIncludes($content);
        $content = $this->compileSections($content);
        $content = $this->compileStacks($content);
        $content = $this->compileOnce($content);
        $content = $this->compileErrorDirectives($content);
        $content = $this->compileCsrf($content);
        $content = $this->compileMethod($content);

        return $content;
    }

    public function clearCache(): void
    {
        $this->compilationCache = [];
        $this->componentData = [];
    }

    /**
     * Parse HTML attributes into an associative array
     */
    private function parseAttributes(string $attributes): array
    {
        $result = [];
        $attributes = trim($attributes);

        if (empty($attributes)) {
            return $result;
        }

        // First, handle attributes with {{ }} or {{{ }}} expressions
        // Replace them temporarily with placeholders
        $expressionMap = [];
        $expressionCounter = 0;

        $attributes = preg_replace_callback(
            '/\{\{\{?\s*(.+?)\s*\}\}\}?/s',
            function ($matches) use (&$expressionMap, &$expressionCounter) {
                $placeholder = "___EXPR_{$expressionCounter}___";
                $expressionMap[$placeholder] = trim($matches[1]);
                $expressionCounter++;
                return $placeholder;
            },
            $attributes
        );

        // Match attribute="value" or attribute='value'
        preg_match_all('/(\w+)\s*=\s*(["\'])(.*?)\2/s', $attributes, $matches, PREG_SET_ORDER);

        foreach ($matches as $match) {
            $value = $match[3];

            // Check if value contains expression placeholders
            $hasExpression = false;
            foreach ($expressionMap as $placeholder => $expression) {
                if (strpos($value, $placeholder) !== false) {
                    // Replace placeholder back with the PHP expression
                    $value = str_replace($placeholder, $expression, $value);
                    $hasExpression = true;
                }
            }

            $result[$match[1]] = [
                'value' => $value,
                'quoted' => true,
                'is_variable' => $hasExpression
            ];
        }

        // Match attributes without quotes: attribute=$variable or attribute=$variable->property
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

        // Match boolean attributes (just the attribute name)
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
     * Build PHP array code from attribute data
     */
    private function buildDataArray(array $attributes): string
    {
        $parts = [];

        foreach ($attributes as $key => $info) {
            $value = $info['value'];
            $isVariable = $info['is_variable'];

            if ($isVariable) {
                // Variable reference - keep as is (with $)
                $parts[] = "'{$key}' => {$value}";
            } else {
                // String value - properly escape
                $escapedValue = addslashes($value);
                $parts[] = "'{$key}' => '{$escapedValue}'";
            }
        }

        return implode(', ', $parts);
    }

    protected function compileComments(string $content): string
    {
        return preg_replace('/\{\{--(.+?)--\}\}/s', '', $content);
    }

    protected function compileEchos(string $content): string
    {
        // Fixed: Triple braces should NOT escape HTML (raw output)
        return preg_replace('/\{\{\{\s*(.+?)\s*\}\}\}/s', '<?php echo $1; ?>', $content);
    }

    protected function compileEscapedEchos(string $content): string
    {
        // Fixed: Double braces SHOULD escape HTML (safe output)
        return preg_replace('/\{\{\s*(.+?)\s*\}\}/s', '<?php echo htmlspecialchars((string)($1), ENT_QUOTES, \'UTF-8\'); ?>', $content);
    }

    protected function compileConditionals(string $content): string
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

    protected function compileLoops(string $content): string
    {
        // Foreach with automatic isset check for safer iteration
        $content = preg_replace_callback(
            '/\@foreach\s*\((.+?)\s+as\s+(.+?)\)/s',
            function ($matches) {
                $array = trim($matches[1]);
                $iteration = trim($matches[2]);

                // Extract just the array variable name for isset check
                preg_match('/^\$[\w\[\]\-\>\'\"]+/', $array, $varMatch);
                $varName = $varMatch[0] ?? $array;

                return "<?php if(isset({$varName}) && is_iterable({$varName})): foreach ({$array} as {$iteration}): ?>";
            },
            $content
        );

        $content = preg_replace('/\@endforeach\b/', '<?php endforeach; endif; ?>', $content);

        $content = preg_replace('/\@for\s*\((.+?)\)/s', '<?php for ($1): ?>', $content);
        $content = preg_replace('/\@endfor\b/', '<?php endfor; ?>', $content);
        $content = preg_replace('/\@while\s*\((.+?)\)/s', '<?php while ($1): ?>', $content);
        $content = preg_replace('/\@endwhile\b/', '<?php endwhile; ?>', $content);

        // Fixed: Continue and break directives with proper condition handling
        $content = preg_replace_callback('/\@continue(?:\s*\((.+?)\))?/s', function ($matches) {
            if (isset($matches[1]) && !empty(trim($matches[1]))) {
                return '<?php if (' . $matches[1] . ') continue; ?>';
            }
            return '<?php continue; ?>';
        }, $content);

        $content = preg_replace_callback('/\@break(?:\s*\((.+?)\))?/s', function ($matches) {
            if (isset($matches[1]) && !empty(trim($matches[1]))) {
                return '<?php if (' . $matches[1] . ') break; ?>';
            }
            return '<?php break; ?>';
        }, $content);

        return $content;
    }

    protected function compileIncludes(string $content): string
    {
        return preg_replace_callback(
            '/\@include\s*\([\'"](.+?)[\'"]\s*(?:,\s*(\[.+?\]))?\s*\)/s',
            function ($matches) {
                $view = addslashes($matches[1]);
                $data = $matches[2] ?? '[]';

                return "<?php if (isset(\$view)) { " .
                    "echo \$view->render('{$view}', array_merge(get_defined_vars(), (array){$data})); " .
                    "} ?>";
            },
            $content
        );
    }

    protected function compileSections(string $content): string
    {
        $content = preg_replace_callback(
            '/\@extends\s*\([\'"](.+?)[\'"]\)/',
            function ($matches) {
                return "<?php \$__extends = '" . addslashes($matches[1]) . "'; ?>";
            },
            $content
        );

        $content = preg_replace_callback(
            '/\@section\s*\([\'"](.+?)[\'"]\s*,\s*[\'"](.+?)[\'"]\)/',
            function ($matches) {
                $name = addslashes($matches[1]);
                $content = addslashes($matches[2]);
                return "<?php \$__sections['{$name}'] = '{$content}'; ?>";
            },
            $content
        );

        $content = preg_replace_callback(
            '/\@section\s*\([\'"](.+?)[\'"]\)/',
            function ($matches) {
                $name = addslashes($matches[1]);
                return "<?php \$__currentSection = '{$name}'; ob_start(); ?>";
            },
            $content
        );

        $content = preg_replace(
            '/\@endsection\b/',
            '<?php if (isset($__currentSection)) { $__sections[$__currentSection] = ob_get_clean(); unset($__currentSection); } ?>',
            $content
        );

        $content = preg_replace(
            '/\@show\b/',
            '<?php if (isset($__currentSection)) { $__sections[$__currentSection] = ob_get_clean(); echo $__sections[$__currentSection]; unset($__currentSection); } ?>',
            $content
        );

        $content = preg_replace_callback(
            '/\@yield\s*\([\'"](.+?)[\'"]\s*(?:,\s*[\'"]?(.*?)[\'"]?)?\)/',
            function ($matches) {
                $section = addslashes($matches[1]);
                $default = isset($matches[2]) ? addslashes($matches[2]) : '';
                return "<?php echo \$__sections['{$section}'] ?? '{$default}'; ?>";
            },
            $content
        );

        $content = preg_replace('/\@parent\b/', '<?php echo $__parentContent ?? \'\'; ?>', $content);

        return $content;
    }

    protected function compileCsrf(string $content): string
    {
        return preg_replace(
            '/\@csrf\b/',
            '<?php echo function_exists(\'csrf_field\') ? csrf_field() : \'<input type="hidden" name="_token" value="\' . ($_SESSION[\'csrf_token\'] ?? \'\') . \'">\'; ?>',
            $content
        );
    }

    protected function compileMethod(string $content): string
    {
        return preg_replace(
            '/\@method\s*\([\'"](.+?)[\'"]\)/',
            '<?php echo \'<input type="hidden" name="_method" value="$1">\'; ?>',
            $content
        );
    }

    protected function compilePhp(string $content): string
    {
        $content = preg_replace('/\@php\b(.*?)\@endphp\b/s', '<?php $1 ?>', $content);
        $content = preg_replace('/\@php\s*\((.+?)\)/s', '<?php $1; ?>', $content);

        return $content;
    }

    protected function compileOnce(string $content): string
    {
        return preg_replace_callback(
            '/\@once\b(.*?)\@endonce\b/s',
            function ($matches) {
                $id = md5($matches[1]);
                return "<?php if (!isset(\$__once_{$id})): \$__once_{$id} = true; ?>" .
                    $matches[1] .
                    "<?php endif; ?>";
            },
            $content
        );
    }

    protected function compileErrorDirectives(string $content): string
    {
        $content = preg_replace(
            '/\@error\s*\(\s*[\'"](.+?)[\'"]\s*\)/',
            '<?php if (isset($errors) && $errors->has(\'$1\')): ?>',
            $content
        );

        $content = preg_replace('/\@enderror\b/', '<?php endif; ?>', $content);

        return $content;
    }

    protected function compileStacks(string $content): string
    {
        $content = preg_replace_callback(
            '/\@push\s*\(\s*[\'"](.+?)[\'"]\s*\)/',
            function ($matches) {
                $stackName = addslashes($matches[1]);
                return "<?php \$__currentStack = '{$stackName}'; ob_start(); ?>";
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

        $content = preg_replace_callback(
            '/\@stack\s*\(\s*[\'"](.+?)[\'"]\s*\)/',
            function ($matches) {
                $stackName = addslashes($matches[1]);
                return "<?php echo implode('', \$__stacks['{$stackName}'] ?? []); ?>";
            },
            $content
        );

        $content = preg_replace_callback(
            '/\@prepend\s*\(\s*[\'"](.+?)[\'"]\s*\)/',
            function ($matches) {
                $stackName = addslashes($matches[1]);
                return "<?php \$__currentStack = '{$stackName}'; \$__isPrepend = true; ob_start(); ?>";
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

        return $content;
    }
}
