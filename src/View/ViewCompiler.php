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
    private $compilationCache = [];
    private $viewEngine;

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

        $content = $this->compileComments($content);
        $content = $this->compilePhp($content);
        $content = $this->compileComponents($content);
        $content = $this->compileEchos($content);
        $content = $this->compileEscapedEchos($content);
        $content = $this->compileConditionals($content);
        $content = $this->compileLoops($content);
        $content = $this->compileIncludes($content);
        $content = $this->compileSections($content);
        $content = $this->compileStacks($content); // NEW
        $content = $this->compileOnce($content); // NEW
        $content = $this->compileErrorDirectives($content); // NEW
        $content = $this->compileCsrf($content);
        $content = $this->compileMethod($content);

        $this->compilationCache[$cacheKey] = $content;

        return $content;
    }

    public function clearCache(): void
    {
        $this->compilationCache = [];
    }

    /**
     * Compile component tags like <Sidebar>{{$slot}}</Sidebar>
     */
    protected function compileComponents(string $content): string
    {
        // First, compile self-closing components: <Sidebar /> or <Sidebar/>
        $content = preg_replace_callback(
            '/<([A-Z][a-zA-Z0-9]*)((?:\s+[^>]*?)?)\/>/s',
            function ($matches) {
                $componentName = $matches[1];
                $attributes = isset($matches[2]) ? trim($matches[2]) : '';
                return $this->compileComponent($componentName, $attributes, '');
            },
            $content
        );

        // Then, compile opening and closing component tags with slot content
        $content = preg_replace_callback(
            '/<([A-Z][a-zA-Z0-9]*)((?:\s+[^>]*?)?)>(.*?)<\/\1\s*>/s',
            function ($matches) {
                $componentName = $matches[1];
                $attributes = isset($matches[2]) ? trim($matches[2]) : '';
                $slotContent = $matches[3] ?? '';
                return $this->compileComponent($componentName, $attributes, $slotContent);
            },
            $content
        );

        return $content;
    }

    /**
     * Compile a single component into PHP code
     */
    private function compileComponent(string $componentName, string $attributes, string $slotContent): string
    {
        // Parse attributes
        $attributesArray = $this->parseAttributes($attributes);

        // Build the component data array
        $dataPhp = $this->buildDataArray($attributesArray);

        // Add slot content to data - store it raw
        $slotContent = trim($slotContent);
        if (!empty($slotContent)) {
            $compiledSlot = $this->compile($slotContent); // Compile any directives in slot
            $escapedSlot = addcslashes($compiledSlot, "'");
            if (!empty($dataPhp)) {
                $dataPhp .= ', ';
            }
            $dataPhp .= "'__slot' => '{$escapedSlot}'";
        }

        // Return the compiled component
        return "<?php echo \$view->renderComponent('{$componentName}', [{$dataPhp}]); ?>";
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

        // Match attribute="value" or attribute='value'
        preg_match_all('/(\w+)\s*=\s*(["\'])(.*?)\2/s', $attributes, $matches, PREG_SET_ORDER);

        foreach ($matches as $match) {
            $result[$match[1]] = [
                'value' => $match[3],
                'quoted' => true,
                'is_variable' => false
            ];
        }

        // Match attributes without quotes: attribute=value (including $variables)
        preg_match_all('/(\w+)\s*=\s*(\$\w+)(?=\s|$|\/)/s', $attributes, $matches, PREG_SET_ORDER);

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
        $withoutQuoted = preg_replace('/\w+\s*=\s*(["\'].*?\1|\$\w+)/s', '', $attributes);
        preg_match_all('/(\w+)/', $withoutQuoted, $matches);

        foreach ($matches[1] as $attr) {
            if (!isset($result[$attr]) && !empty(trim($attr))) {
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
                // Variable reference - remove $ sign
                $varName = substr($value, 1);
                $parts[] = "'{$key}' => \${$varName}";
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
        // Consider adding a safe mode or validation
        return preg_replace('/\{\{\{\s*(.+?)\s*\}\}\}/s', '<?php echo htmlspecialchars($1, ENT_QUOTES, \'UTF-8\', false); ?>', $content);
    }

    protected function compileEscapedEchos(string $content): string
    {
        return preg_replace('/\{\{\s*(.+?)\s*\}\}/s', '<?php echo htmlspecialchars((string)($1), ENT_QUOTES, \'UTF-8\'); ?>', $content);
    }

    protected function compileConditionals(string $content): string
    {
        // @if
        $content = preg_replace('/\@if\s*\((.+?)\)/s', '<?php if ($1): ?>', $content);

        // @elseif
        $content = preg_replace('/\@elseif\s*\((.+?)\)/s', '<?php elseif ($1): ?>', $content);

        // @else
        $content = preg_replace('/\@else\b/', '<?php else: ?>', $content);

        // @endif
        $content = preg_replace('/\@endif\b/', '<?php endif; ?>', $content);

        // @unless
        $content = preg_replace('/\@unless\s*\((.+?)\)/s', '<?php if (!($1)): ?>', $content);

        // @endunless
        $content = preg_replace('/\@endunless\b/', '<?php endif; ?>', $content);

        // @isset
        $content = preg_replace('/\@isset\s*\((.+?)\)/s', '<?php if (isset($1)): ?>', $content);

        // @endisset
        $content = preg_replace('/\@endisset\b/', '<?php endif; ?>', $content);

        // @empty
        $content = preg_replace('/\@empty\s*\((.+?)\)/s', '<?php if (empty($1)): ?>', $content);

        // @endempty
        $content = preg_replace('/\@endempty\b/', '<?php endif; ?>', $content);

        return $content;
    }

    protected function compileLoops(string $content): string
    {
        // @foreach
        $content = preg_replace('/\@foreach\s*\((.+?)\)/s', '<?php foreach ($1): ?>', $content);

        // @endforeach
        $content = preg_replace('/\@endforeach\b/', '<?php endforeach; ?>', $content);

        // @for
        $content = preg_replace('/\@for\s*\((.+?)\)/s', '<?php for ($1): ?>', $content);

        // @endfor
        $content = preg_replace('/\@endfor\b/', '<?php endfor; ?>', $content);

        // @while
        $content = preg_replace('/\@while\s*\((.+?)\)/s', '<?php while ($1): ?>', $content);

        // @endwhile
        $content = preg_replace('/\@endwhile\b/', '<?php endwhile; ?>', $content);

        // @continue with optional condition
        $content = preg_replace('/\@continue(?:\s*\((.+?)\))?/s', '<?php if ($1 ?? true) continue; ?>', $content);

        // @break with optional condition
        $content = preg_replace('/\@break(?:\s*\((.+?)\))?/s', '<?php if ($1 ?? true) break; ?>', $content);

        return $content;
    }

    protected function compileIncludes(string $content): string
    {
        return preg_replace_callback(
            '/\@include\s*\([\'"](.+?)[\'"]\s*(?:,\s*(\[.+?\]))?\s*\)/s',
            function ($matches) {
                $view = addslashes($matches[1]);
                $data = $matches[2] ?? '[]';

                // Fix: Properly handle data merging
                return "<?php if (isset(\$view)) { " .
                    "echo \$view->render('{$view}', array_merge(get_defined_vars(), (array){$data})); " .
                    "} ?>";
            },
            $content
        );
    }

    protected function compileSections(string $content): string
    {
        // @extends
        $content = preg_replace_callback(
            '/\@extends\s*\([\'"](.+?)[\'"]\)/',
            function ($matches) {
                return "<?php \$__extends = '" . addslashes($matches[1]) . "'; ?>";
            },
            $content
        );

        // @section with inline content
        $content = preg_replace_callback(
            '/\@section\s*\([\'"](.+?)[\'"]\s*,\s*[\'"](.+?)[\'"]\)/',
            function ($matches) {
                $name = addslashes($matches[1]);
                $content = addslashes($matches[2]);
                return "<?php \$__sections['{$name}'] = '{$content}'; ?>";
            },
            $content
        );

        // @section with block content
        $content = preg_replace_callback(
            '/\@section\s*\([\'"](.+?)[\'"]\)/',
            function ($matches) {
                $name = addslashes($matches[1]);
                return "<?php \$__currentSection = '{$name}'; ob_start(); ?>";
            },
            $content
        );

        // @endsection
        $content = preg_replace(
            '/\@endsection\b/',
            '<?php if (isset($__currentSection)) { $__sections[$__currentSection] = ob_get_clean(); unset($__currentSection); } ?>',
            $content
        );

        // @show
        $content = preg_replace(
            '/\@show\b/',
            '<?php if (isset($__currentSection)) { $__sections[$__currentSection] = ob_get_clean(); echo $__sections[$__currentSection]; unset($__currentSection); } ?>',
            $content
        );

        // @yield with default
        $content = preg_replace_callback(
            '/\@yield\s*\([\'"](.+?)[\'"]\s*(?:,\s*[\'"]?(.*?)[\'"]?)?\)/',
            function ($matches) {
                $section = addslashes($matches[1]);
                $default = isset($matches[2]) ? addslashes($matches[2]) : '';
                return "<?php echo \$__sections['{$section}'] ?? '{$default}'; ?>";
            },
            $content
        );

        // @parent
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
        // Block @php ... @endphp syntax (must be processed first)
        $content = preg_replace('/\@php\b(.*?)\@endphp\b/s', '<?php $1 ?>', $content);

        // Inline @php(...) syntax
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
        // @error('field')
        $content = preg_replace(
            '/\@error\s*\(\s*[\'"](.+?)[\'"]\s*\)/',
            '<?php if (isset($errors) && $errors->has(\'$1\')): ?>',
            $content
        );

        // @enderror
        $content = preg_replace('/\@enderror\b/', '<?php endif; ?>', $content);

        return $content;
    }

    /**
     * Compile stack directives (@push, @stack)
     */
    protected function compileStacks(string $content): string
    {
        // @push('stack-name')
        $content = preg_replace_callback(
            '/\@push\s*\(\s*[\'"](.+?)[\'"]\s*\)/',
            function ($matches) {
                $stackName = addslashes($matches[1]);
                return "<?php \$__currentStack = '{$stackName}'; ob_start(); ?>";
            },
            $content
        );

        // @endpush
        $content = preg_replace(
            '/\@endpush\b/',
            '<?php if (isset($__currentStack)) { $__stacks[$__currentStack][] = ob_get_clean(); unset($__currentStack); } ?>',
            $content
        );

        // @stack('stack-name')
        $content = preg_replace_callback(
            '/\@stack\s*\(\s*[\'"](.+?)[\'"]\s*\)/',
            function ($matches) {
                $stackName = addslashes($matches[1]);
                return "<?php echo implode('', \$__stacks['{$stackName}'] ?? []); ?>";
            },
            $content
        );

        // @prepend('stack-name')
        $content = preg_replace_callback(
            '/\@prepend\s*\(\s*[\'"](.+?)[\'"]\s*\)/',
            function ($matches) {
                $stackName = addslashes($matches[1]);
                return "<?php \$__currentStack = '{$stackName}'; ob_start(); ?>";
            },
            $content
        );

        // @endprepend
        $content = preg_replace(
            '/\@endprepend\b/',
            '<?php if (isset($__currentStack)) { array_unshift($__stacks[$__currentStack] ?? [], ob_get_clean()); unset($__currentStack); } ?>',
            $content
        );

        return $content;
    }
}
