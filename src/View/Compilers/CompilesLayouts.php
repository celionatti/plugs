<?php

declare(strict_types=1);

namespace Plugs\View\Compilers;

trait CompilesLayouts
{
    /**
     * Compile tag-based layout inheritance: <layout name="..."> content </layout>
     *
     * @param  string  $content
     * @return string
     */
    protected function compileLayoutTag(string $content): string
    {
        $attrRegex = '((?:\s+(?:[^>"\'\/]+|"[^"]*"|\'[^\']*\')*)*?)';

        return preg_replace_callback('/<layout' . $attrRegex . '>(.*?)<\/layout\s*>/is', function ($matches) use ($attrRegex) {
            $attrs = $this->parseAttributes($matches[1]);

            $layoutName = $attrs['name']['value'] ?? '';
            if (!$layoutName)
                return $matches[0];

            $body = $matches[2];

            $sections = [];

            // 1. Parse <slot name="..."> content </slot>
            $body = preg_replace_callback('/<slot' . $attrRegex . '>(.*?)<\/slot>/is', function ($sMatches) use (&$sections) {
                $sAttrs = $this->parseAttributes($sMatches[1]);
                if (isset($sAttrs['name'])) {
                    $sections[$sAttrs['name']['value']] = trim($sMatches[2]);
                }
                return '';
            }, $body);

            // 2. Parse <slot:name> content </slot:name> (V5 shorthand syntax)
            $body = preg_replace_callback('/<slot:([\w:-]+)\s*>(.*?)<\/slot(?::\1)?>/is', function ($sMatches) use (&$sections) {
                $sections[$sMatches[1]] = trim($sMatches[2]);
                return '';
            }, $body);

            // 3. Anything left goes to @section('content')
            $defaultContent = trim($body);

            $result = "@extends('{$layoutName}')\n\n";

            foreach ($sections as $name => $content) {
                $result .= "@section('{$name}')\n{$content}\n@endsection\n\n";
            }

            if (!empty($defaultContent)) {
                if (isset($sections['content'])) {
                    // If content slot is already defined, unrelated content (push/stack/etc) 
                    // should be appended raw, not wrapped in a duplicate content section.
                    $result .= $defaultContent;
                } else {
                    $result .= "@section('content')\n{$defaultContent}\n@endsection";
                }
            }

            return $result;
        }, $content);
    }

    /**
     * Compile tag-based directives into their @ equivalents.
     *
     * @param  string  $content
     * @return string
     */
    protected function compileTagDirectives(string $content): string
    {
        $attrRegex = '((?:\s+(?:[^>"\'\/]+|"[^"]*"|\'[^\']*\')*)*?)';

        // 1. <push:name> ... </push:name>
        $content = preg_replace_callback('/<push:([\w-]+)\s*>/s', function ($m) {
            return "@push('{$m[1]}')";
        }, $content);
        $content = preg_replace('/<\/push:[\w-]+\s*>/s', '@endpush', $content);

        // 1b. <pushOnce:stack key="..."> ... </pushOnce:stack>
        $content = preg_replace_callback('/<pushOnce:([\w-]+)' . $attrRegex . '>(.*?)<\/pushOnce:[\w-]+\s*>/is', function ($m) {
            $stack = $m[1];
            $attrs = $this->parseAttributes($m[2]);
            $key = $attrs['key']['value'] ?? 'null';
            $inner = $m[3];
            return "@pushOnce('{$key}', '{$stack}'){$inner}@endPushOnce";
        }, $content);

        // 2. <prepend:name> ... </prepend:name>
        $content = preg_replace_callback('/<prepend:([\w-]+)\s*>/s', function ($m) {
            return "@prepend('{$m[1]}')";
        }, $content);
        $content = preg_replace('/<\/prepend:[\w-]+\s*>/s', '@endprepend', $content);

        // 3. <stack:name /> — self-closing, renders the stack
        $content = preg_replace_callback('/<stack:([\w-]+)\s*\/>/s', function ($m) {
            return "@stack('{$m[1]}')";
        }, $content);

        // 4. <yield:name default="..." /> or <yield:name />
        $content = preg_replace_callback('/<yield:([\w-]+)' . $attrRegex . '\/?>/is', function ($m) {
            $attrs = $this->parseAttributes($m[2]);
            if (isset($attrs['default'])) {
                return "@yield('{$m[1]}', '{$attrs['default']['value']}')";
            }
            return "@yield('{$m[1]}')";
        }, $content);

        // 6. <include view="..." :data="[...]" /> or <include view="..." />
        $content = preg_replace_callback('/<include' . $attrRegex . '\/?>/is', function ($m) {
            $attrs = $this->parseAttributes($m[1]);
            if (isset($attrs['view'])) {
                if (isset($attrs['data'])) {
                    return "@include('{$attrs['view']['value']}', {$attrs['data']['value']})";
                }
                return "@include('{$attrs['view']['value']}')";
            }
            return $m[0];
        }, $content);

        return $content;
    }

    /**
     * Compile the include statements in the given string.
     *
     * @param  string  $content
     * @return string
     */
    protected function compileIncludes(string $content): string
    {
        $balanced = '([^()]*+(?:\((?1)\)[^()]*+)*+)';

        return preg_replace_callback(
            '/@include\s*\(' . $balanced . '\)/s',
            function ($matches) {
                // Parse @include('view', ['data'])
                $head = $matches[1];
                if (!preg_match('/^[\'"](.+?)[\'"]\s*(?:,\s*(.+))?$/s', $head, $headMatches)) {
                    return $matches[0];
                }

                $view = $headMatches[1];
                $data = $headMatches[2] ?? '[]';

                // Normalize and validate path
                $view = str_replace(['\\', '/'], '.', $view);
                if (preg_match('/[^a-zA-Z0-9._-]/', $view) || strpos($view, '..') !== false) {
                    return sprintf('<?php /* Invalid include path: %s */ ?>', htmlspecialchars($view));
                }

                $view = addslashes($view);

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
     * Compile the section statements in the given string.
     *
     * @param  string  $content
     * @return string
     */
    protected function compileSections(string $content): string
    {
        $balanced = '([^()]*+(?:\((?1)\)[^()]*+)*+)';

        // @extends
        $content = preg_replace_callback('/@extends\s*\(' . $balanced . '\)/', function ($matches) {
            $view = trim($matches[1], ' "\'');
            return sprintf('<?php $__extends = \'%s\'; ?>', addslashes($view));
        }, $content);

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
        $content = preg_replace_callback('/@yield\s*\(' . $balanced . '\)/', function ($matches) {
            $head = $matches[1];
            $parts = preg_split('/\s*,\s*/', $head, 2);
            $section = trim($parts[0], ' "\'');
            $default = isset($parts[1]) ? trim($parts[1], ' "\'') : '';

            return sprintf('<?php echo $__sections[\'%s\'] ?? \'%s\'; ?>', addslashes($section), addslashes($default));
        }, $content);

        // @parent
        $content = preg_replace('/@parent\s*(?:\r?\n)?/', '<?php echo $__parentContent ?? \'\'; ?>', $content);

        return $content;
    }

    /**
     * Compile the stack statements in the given string.
     *
     * @param  string  $content
     * @return string
     */
    protected function compileStacks(string $content): string
    {
        $balanced = '([^()]*+(?:\((?1)\)[^()]*+)*+)';

        // @push
        $content = preg_replace_callback('/@push\s*\(' . $balanced . '\)/', function ($matches) {
            $stackName = trim($matches[1], ' "\'');
            return sprintf('<?php $__currentStack = \'%s\'; ob_start(); ?>', addslashes($stackName));
        }, $content);

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

    /**
     * Compile @pushOnce directive to push content to a stack only once.
     *
     * @param  string  $content
     * @return string
     */
    protected function compilePushOnce(string $content): string
    {
        return preg_replace_callback(
            '/@pushOnce\s*\(\s*[\'"](.+?)[\'"]\s*,\s*[\'"](.+?)[\'"]\s*\)\s*\n?(.*?)\n?\s*@endPushOnce/s',
            function ($matches) {
                $key = $matches[1];
                $stackName = addslashes($matches[2]);
                $innerContent = $matches[3];
                $id = preg_replace('/[^a-zA-Z0-9_]/', '_', $key);

                return sprintf(
                    '<?php if (!isset($__pushOnce_%s)): $__pushOnce_%s = true; $__currentStack = \'%s\'; ob_start(); ?>%s<?php if (isset($__currentStack)) { if (!isset($__stacks[$__currentStack])) { $__stacks[$__currentStack] = []; } $__stacks[$__currentStack][] = ob_get_clean(); unset($__currentStack); } endif; ?>',
                    $id,
                    $id,
                    $stackName,
                    $innerContent
                );
            },
            $content
        );
    }
}
