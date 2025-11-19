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
    private array $sectionStack = [];
    private array $componentStack = [];
    private array $componentData = [];
    private ?ViewEngine $viewEngine;
    private array $compilationCache = [];
    private array $customDirectives = [];
    private const MAX_CACHE_SIZE = 1000;

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

    public function compile(string $content): string
    {
        $cacheKey = md5($content);

        if (isset($this->compilationCache[$cacheKey])) {
            return $this->compilationCache[$cacheKey];
        }

        // Phase 1: Extract components first (they have highest priority)
        $content = $this->extractComponentsWithSlots($content);

        // Phase 2: Compile all other directives in correct order
        $content = $this->compileNonComponentContent($content);

        $this->cacheCompilation($cacheKey, $content);

        return $content;
    }

    public function getCompiledSlot(string $slotId): string
    {
        if (isset($this->componentData[$slotId])) {
            $slotContent = $this->componentData[$slotId];
            return $this->compileNonComponentContent($slotContent);
        }

        return '';
    }

    public function clearCache(): void
    {
        $this->compilationCache = [];
        $this->componentData = [];
    }

    private function extractComponentsWithSlots(string $content): string
    {
        // Self-closing components: <ComponentName attr="value" />
        $content = preg_replace_callback(
            '/<([A-Z][a-zA-Z0-9]*)((?:\s+[^>]*?)?)\/>/s',
            function ($matches) {
                $componentName = $matches[1];
                $attributes = $matches[2] ?? '';
                return $this->createComponentPlaceholder($componentName, trim($attributes), '');
            },
            $content
        );

        // Components with content: <ComponentName>...</ComponentName>
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

    private function createComponentPlaceholder(string $componentName, string $attributes, string $slotContent): string
    {
        $attributesArray = $this->parseAttributes($attributes);
        $dataPhp = $this->buildDataArray($attributesArray);

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

    private function compileNonComponentContent(string $content): string
    {
        // CRITICAL: Order matters for correct compilation
        // 1. Comments first (remove them entirely)
        $content = $this->compileComments($content);

        // 2. Verbatim blocks (protect from compilation)
        $content = $this->compileVerbatim($content);

        // 3. PHP blocks (before template syntax to avoid conflicts)
        $content = $this->compilePhp($content);

        // 4. Control structures (conditionals and loops)
        $content = $this->compileConditionals($content);
        $content = $this->compileLoops($content);

        // 5. Custom directives (user-defined)
        $content = $this->compileCustomDirectives($content);

        // 6. Template inheritance and sections
        $content = $this->compileSections($content);
        $content = $this->compileIncludes($content);

        // 7. Stacks and assets
        $content = $this->compileStacks($content);
        $content = $this->compileOnce($content);

        // 8. Form helpers
        $content = $this->compileCsrf($content);
        $content = $this->compileMethod($content);

        // 9. Utilities
        $content = $this->compileJson($content);
        $content = $this->compileErrorDirectives($content);

        // 10. Echo statements LAST (after all directives)
        $content = $this->compileRawEchos($content);
        $content = $this->compileEscapedEchos($content);

        return $content;
    }

    private function parseAttributes(string $attributes): array
    {
        $result = [];
        $attributes = trim($attributes);

        if (empty($attributes)) {
            return $result;
        }

        // Store expressions temporarily
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

        // Quoted attributes
        preg_match_all(
            '/(\w+)\s*=\s*(["\'])((?:[^\2\\\\]|\\\\.)*)\2/s',
            $attributes,
            $matches,
            PREG_SET_ORDER
        );

        foreach ($matches as $match) {
            $key = $match[1];
            $value = str_replace('\\' . $match[2], $match[2], $match[3]);
            $hasExpression = false;

            foreach ($expressionMap as $placeholder => $expression) {
                if (strpos($value, $placeholder) !== false) {
                    $value = str_replace($placeholder, $expression, $value);
                    $hasExpression = true;
                }
            }

            $result[$key] = [
                'value' => $value,
                'quoted' => true,
                'is_variable' => $hasExpression
            ];
        }

        // Unquoted variables
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

        // Boolean attributes
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

    private function compileComments(string $content): string
    {
        return preg_replace('/\{\{--.*?--\}\}/s', '', $content);
    }

    private function compileVerbatim(string $content): string
    {
        // Store verbatim blocks and replace them with placeholders
        static $verbatimBlocks = [];

        return preg_replace_callback(
            '/@verbatim(.*?)@endverbatim/s',
            function ($matches) use (&$verbatimBlocks) {
                $placeholder = '___VERBATIM_' . md5($matches[1]) . '___';
                $verbatimBlocks[$placeholder] = $matches[1];
                return $placeholder;
            },
            $content
        );
    }

    private function compilePhp(string $content): string
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

    private function compileRawEchos(string $content): string
    {
        // Raw output: {{{ $var }}}
        return preg_replace(
            '/\{\{\{\s*(.+?)\s*\}\}\}/s',
            '<?php echo $1; ?>',
            $content
        );
    }

    private function compileEscapedEchos(string $content): string
    {
        // Escaped output: {{ $var }}
        return preg_replace(
            '/\{\{\s*(.+?)\s*\}\}/s',
            '<?php echo htmlspecialchars((string)($1), ENT_QUOTES, \'UTF-8\'); ?>',
            $content
        );
    }

    private function compileConditionals(string $content): string
    {
        // @if
        $content = preg_replace('/@if\s*\((.+?)\)/s', '<?php if ($1): ?>', $content);

        // @elseif
        $content = preg_replace('/@elseif\s*\((.+?)\)/s', '<?php elseif ($1): ?>', $content);

        // @else
        $content = preg_replace('/@else\s*(?:\r?\n)?/', '<?php else: ?>', $content);

        // @endif
        $content = preg_replace('/@endif\s*(?:\r?\n)?/', '<?php endif; ?>', $content);

        // @unless (inverted if)
        $content = preg_replace('/@unless\s*\((.+?)\)/s', '<?php if (!($1)): ?>', $content);
        $content = preg_replace('/@endunless\s*(?:\r?\n)?/', '<?php endif; ?>', $content);

        // @isset
        $content = preg_replace('/@isset\s*\((.+?)\)/s', '<?php if (isset($1)): ?>', $content);
        $content = preg_replace('/@endisset\s*(?:\r?\n)?/', '<?php endif; ?>', $content);

        // @empty
        $content = preg_replace('/@empty\s*\((.+?)\)/s', '<?php if (empty($1)): ?>', $content);
        $content = preg_replace('/@endempty\s*(?:\r?\n)?/', '<?php endif; ?>', $content);

        // @switch
        $content = preg_replace('/@switch\s*\((.+?)\)/', '<?php switch($1): ?>', $content);
        $content = preg_replace('/@case\s*\((.+?)\)/', '<?php case $1: ?>', $content);
        $content = preg_replace('/@default\s*/', '<?php default: ?>', $content);
        $content = preg_replace('/@endswitch\s*(?:\r?\n)?/', '<?php endswitch; ?>', $content);

        return $content;
    }

    private function compileLoops(string $content): string
    {
        // @foreach with safety checks
        $content = preg_replace_callback(
            '/@foreach\s*\((.+?)\s+as\s+(.+?)\)/s',
            function ($matches) {
                $array = trim($matches[1]);
                $iteration = trim($matches[2]);

                if (preg_match('/^(\$[\w]+)/', $array, $varMatch)) {
                    $varName = $varMatch[1];
                } else {
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
        $content = preg_replace('/@endforeach\s*(?:\r?\n)?/', '<?php endforeach; endif; ?>', $content);

        // @for
        $content = preg_replace('/@for\s*\((.+?)\)/s', '<?php for ($1): ?>', $content);
        $content = preg_replace('/@endfor\s*(?:\r?\n)?/', '<?php endfor; ?>', $content);

        // @while
        $content = preg_replace('/@while\s*\((.+?)\)/s', '<?php while ($1): ?>', $content);
        $content = preg_replace('/@endwhile\s*(?:\r?\n)?/', '<?php endwhile; ?>', $content);

        // @forelse
        $content = preg_replace_callback(
            '/@forelse\s*\((.+?)\s+as\s+(.+?)\)/s',
            function ($matches) {
                $array = trim($matches[1]);
                $iteration = trim($matches[2]);
                $emptyVar = '__empty_' . md5($array);

                return sprintf(
                    '<?php $%s = true; if(isset(%s) && is_iterable(%s)): foreach (%s as %s): $%s = false; ?>',
                    $emptyVar,
                    $array,
                    $array,
                    $array,
                    $iteration,
                    $emptyVar
                );
            },
            $content
        );
        $content = preg_replace('/@empty\s*(?:\r?\n)?/', '<?php endforeach; endif; if($__empty_' . '[^:]+' . '): ?>', $content);
        $content = preg_replace('/@endforelse\s*(?:\r?\n)?/', '<?php endif; ?>', $content);

        // @continue
        $content = preg_replace_callback(
            '/@continue(?:\s*\((.+?)\))?/s',
            function ($matches) {
                if (isset($matches[1]) && !empty(trim($matches[1]))) {
                    return sprintf('<?php if (%s) continue; ?>', $matches[1]);
                }
                return '<?php continue; ?>';
            },
            $content
        );

        // @break
        $content = preg_replace_callback(
            '/@break(?:\s*\((.+?)\))?/s',
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

    private function compileIncludes(string $content): string
    {
        return preg_replace_callback(
            '/@include\s*\([\'"](.+?)[\'"]\s*(?:,\s*(\[.+?\]|\$\w+))?\s*\)/s',
            function ($matches) {
                $view = $matches[1];

                if (strpos($view, '..') !== false || strpos($view, DIRECTORY_SEPARATOR) === 0) {
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

    private function compileSections(string $content): string
    {
        // @extends
        $content = preg_replace_callback(
            '/@extends\s*\([\'"](.+?)[\'"]\)/',
            function ($matches) {
                return sprintf('<?php $__extends = \'%s\'; ?>', addslashes($matches[1]));
            },
            $content
        );

        // @section with inline content
        $content = preg_replace_callback(
            '/@section\s*\([\'"](.+?)[\'"]\s*,\s*[\'"](.+?)[\'"]\)/',
            function ($matches) {
                $name = addslashes($matches[1]);
                $sectionContent = addslashes($matches[2]);
                return sprintf('<?php $__sections[\'%s\'] = \'%s\'; ?>', $name, $sectionContent);
            },
            $content
        );

        // @section block
        $content = preg_replace_callback(
            '/@section\s*\([\'"](.+?)[\'"]\)/',
            function ($matches) {
                $name = addslashes($matches[1]);
                return sprintf('<?php $__currentSection = \'%s\'; ob_start(); ?>', $name);
            },
            $content
        );

        $content = preg_replace(
            '/@endsection\s*(?:\r?\n)?/',
            '<?php if (isset($__currentSection)) { $__sections[$__currentSection] = ob_get_clean(); unset($__currentSection); } ?>',
            $content
        );

        // @show
        $content = preg_replace(
            '/@show\s*(?:\r?\n)?/',
            '<?php if (isset($__currentSection)) { $__sections[$__currentSection] = ob_get_clean(); echo $__sections[$__currentSection]; unset($__currentSection); } ?>',
            $content
        );

        // @yield
        $content = preg_replace_callback(
            '/@yield\s*\([\'"](.+?)[\'"]\s*(?:,\s*[\'"]?(.*?)[\'"]?)?\)/',
            function ($matches) {
                $section = addslashes($matches[1]);
                $default = isset($matches[2]) ? addslashes($matches[2]) : '';
                return sprintf('<?php echo $__sections[\'%s\'] ?? \'%s\'; ?>', $section, $default);
            },
            $content
        );

        // @parent
        $content = preg_replace('/@parent\s*(?:\r?\n)?/', '<?php echo $__parentContent ?? \'\'; ?>', $content);

        return $content;
    }

    private function compileStacks(string $content): string
    {
        // @push
        $content = preg_replace_callback(
            '/@push\s*\(\s*[\'"](.+?)[\'"]\s*\)/',
            function ($matches) {
                $stackName = addslashes($matches[1]);
                return sprintf('<?php $__currentStack = \'%s\'; ob_start(); ?>', $stackName);
            },
            $content
        );

        $content = preg_replace(
            '/@endpush\s*(?:\r?\n)?/',
            '<?php if (isset($__currentStack)) { ' .
                'if (!isset($__stacks[$__currentStack])) { $__stacks[$__currentStack] = []; } ' .
                '$__stacks[$__currentStack][] = ob_get_clean(); unset($__currentStack); } ?>',
            $content
        );

        // @prepend
        $content = preg_replace_callback(
            '/@prepend\s*\(\s*[\'"](.+?)[\'"]\s*\)/',
            function ($matches) {
                $stackName = addslashes($matches[1]);
                return sprintf('<?php $__currentStack = \'%s\'; $__isPrepend = true; ob_start(); ?>', $stackName);
            },
            $content
        );

        $content = preg_replace(
            '/@endprepend\s*(?:\r?\n)?/',
            '<?php if (isset($__currentStack)) { ' .
                'if (!isset($__stacks[$__currentStack])) { $__stacks[$__currentStack] = []; } ' .
                'array_unshift($__stacks[$__currentStack], ob_get_clean()); unset($__currentStack, $__isPrepend); } ?>',
            $content
        );

        // @stack
        $content = preg_replace_callback(
            '/@stack\s*\(\s*[\'"](.+?)[\'"]\s*\)/',
            function ($matches) {
                $stackName = addslashes($matches[1]);
                return sprintf('<?php echo implode(\'\', $__stacks[\'%s\'] ?? []); ?>', $stackName);
            },
            $content
        );

        return $content;
    }

    private function compileCsrf(string $content): string
    {
        return preg_replace(
            '/@csrf\s*(?:\r?\n)?/',
            '<?php echo function_exists(\'csrf_field\') ? csrf_field() : ' .
                '\'<input type="hidden" name="_token" value="\' . ($_SESSION[\'_csrf_token\'] ?? \'\') . \'">\'; ?>',
            $content
        );
    }

    private function compileMethod(string $content): string
    {
        return preg_replace(
            '/@method\s*\([\'"](.+?)[\'"]\)/',
            '<?php echo \'<input type="hidden" name="_method" value="$1">\'; ?>',
            $content
        );
    }

    private function compileOnce(string $content): string
    {
        return preg_replace_callback(
            '/@once\s*\n?(.*?)\n?\s*@endonce/s',
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

    private function compileErrorDirectives(string $content): string
    {
        $content = preg_replace(
            '/@error\s*\(\s*[\'"](.+?)[\'"]\s*\)/',
            '<?php if (isset($errors) && $errors->has(\'$1\')): ?>',
            $content
        );

        $content = preg_replace('/@enderror\s*(?:\r?\n)?/', '<?php endif; ?>', $content);

        return $content;
    }

    private function compileJson(string $content): string
    {
        return preg_replace(
            '/@json\s*\((.+?)\)/',
            '<?php echo json_encode($1, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_QUOT); ?>',
            $content
        );
    }

    private function compileCustomDirectives(string $content): string
    {
        foreach ($this->customDirectives as $name => $handler) {
            $content = preg_replace_callback(
                '/@' . preg_quote($name, '/') . '(?:\s*\((.*?)\))?/s',
                function ($matches) use ($handler) {
                    $expression = $matches[1] ?? null;
                    return call_user_func($handler, $expression);
                },
                $content
            );
        }

        return $content;
    }

    private function cacheCompilation(string $key, string $content): void
    {
        if (count($this->compilationCache) >= self::MAX_CACHE_SIZE) {
            $this->compilationCache = array_slice($this->compilationCache, -500, null, true);
        }

        $this->compilationCache[$key] = $content;
    }
}
