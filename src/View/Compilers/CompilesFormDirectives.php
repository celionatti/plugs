<?php

declare(strict_types=1);

namespace Plugs\View\Compilers;

trait CompilesFormDirectives
{
    /**
     * Compile the CSRF statements in the given string.
     *
     * @param  string  $content
     * @return string
     */
    protected function compileCsrf(string $content): string
    {
        return preg_replace(
            '/@csrf\s*(?:\r?\n)?/',
            '<?php echo function_exists(\'csrf_field\') ? csrf_field() : ' .
            '\'<input type="hidden" name="_token" value="\' . ($_SESSION[\'_csrf_token\'] ?? \'\') . \'">\'; ?>',
            $content
        );
    }

    /**
     * Compile the method statements in the given string.
     *
     * @param  string  $content
     * @return string
     */
    protected function compileMethod(string $content): string
    {
        return preg_replace(
            '/@method\s*\([\'"](.+?)[\'"]\)/',
            '<?php echo \'<input type="hidden" name="_method" value="$1">\'; ?>',
            $content
        );
    }

    protected function compileStyle(string $content): string
    {
        $balanced = '([^()]*+(?:\((?1)\)[^()]*+)*+)';

        return preg_replace_callback(
            '/@style\s*\(' . $balanced . '\)/s',
            function ($matches) {
                return sprintf(
                    '<?php echo \'style="\' . implode(\'; \', array_filter(array_map(function($k, $v) {
                    return is_int($k) ? $v : ($v ? $k : null);
                }, array_keys(%s), array_values(%s)))) . \'"\'; ?>',
                    $matches[1],
                    $matches[1]
                );
            },
            $content
        );
    }

    protected function compileOnce(string $content): string
    {
        return preg_replace_callback(
            '/@once(?:\s*\(\s*[\'"](.+?)[\'"]\s*\))?\s*\n?(.*?)\n?\s*@endonce/s',
            function ($matches) {
                $key = !empty($matches[1]) ? $matches[1] : md5($matches[2]);
                $id = preg_replace('/[^a-zA-Z0-9_]/', '_', $key);

                return sprintf(
                    '<?php if (!isset($__once_%s)): $__once_%s = true; ?>%s<?php endif; ?>',
                    $id,
                    $id,
                    $matches[2]
                );
            },
            $content
        );
    }

    protected function compileStream(string $content): string
    {
        $balanced = '([^()]*+(?:\((?1)\)[^()]*+)*+)';

        return preg_replace_callback(
            '/@stream\s*\(' . $balanced . '\)/s',
            function ($matches) {
                return sprintf('<?php $view->renderToStream(%s, $__data ?? []); ?>', $matches[1]);
            },
            $content
        );
    }

    protected function compileError(string $content): string
    {
        $balanced = '([^()]*+(?:\((?1)\)[^()]*+)*+)';

        return preg_replace_callback(
            '/@error\s*\(' . $balanced . '\)/s',
            function ($matches) {
                $field = $matches[1];
                return sprintf(
                    '<?php if ($errors->has(%s)): ?>
                    <?php $message = $errors->first(%s); ?>',
                    $field,
                    $field
                );
            },
            $content
        );
    }

    protected function compileEndError(string $content): string
    {
        return str_replace('@enderror', '<?php unset($message); endif; ?>', $content);
    }

    protected function compileErrorDirectives(string $content): string
    {
        // @error directive with message variable
        $content = preg_replace_callback(
            '/@error\s*\(\s*[\'"](.+?)[\'"]\s*\)/',
            function ($matches) {
                $field = $matches[1];

                return sprintf(
                    '<?php if (isset($errors) && $errors->has(\'%s\')): ' .
                    '$message = $errors->first(\'%s\'); ?>',
                    addslashes($field),
                    addslashes($field)
                );
            },
            $content
        );

        $content = preg_replace('/@enderror\s*(?:\r?\n)?/', '<?php unset($message); endif; ?>', $content);

        return $content;
    }

    protected function compileJson(string $content): string
    {
        $balanced = '([^()]*+(?:\((?1)\)[^()]*+)*+)';

        return preg_replace(
            '/@json\s*\(' . $balanced . '\)/',
            '<?php echo json_encode($1, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_QUOT); ?>',
            $content
        );
    }

    protected function compileJsonScript(string $content): string
    {
        $balanced = '([^()]*+(?:\((?1)\)[^()]*+)*+)';

        return preg_replace_callback(
            '/@jsonScript\s*\(' . $balanced . '\)/s',
            function ($matches) {
                $head = $matches[1];
                $parts = preg_split('/\s*,\s*/', $head, 2);
                $data = $parts[0];
                $varName = isset($parts[1]) ? trim($parts[1], ' "\'') : 'data';

                return sprintf(
                    '<?php $__nonce = isset($view) ? $view->getCspNonce() : null; ' .
                    'echo "<script" . ($__nonce ? " nonce=\"{$__nonce}\"" : "") . ">" . ' .
                    '"var %s = " . json_encode(%s, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_QUOT) . ";" . ' .
                    '"</script>"; ?>',
                    addslashes($varName),
                    $data
                );
            },
            $content
        );
    }

    protected function compileClass(string $content): string
    {
        $balanced = '([^()]*+(?:\((?1)\)[^()]*+)*+)';

        return preg_replace_callback(
            '/@class\s*\(' . $balanced . '\)/s',
            function ($matches) {
                return sprintf(
                    '<?php echo \'class="\' . \Plugs\Utils\Arr::toCssClasses(%s) . \'"\'; ?>',
                    $matches[1]
                );
            },
            $content
        );
    }

    protected function compileCustomDirectives(string $content): string
    {
        // Sort directives by length descending to prevent prefix conflicts
        $directives = $this->customDirectives;
        uksort($directives, fn($a, $b) => strlen($b) <=> strlen($a));

        foreach ($directives as $name => $handler) {
            $content = preg_replace_callback(
                '/@' . preg_quote($name, '/') . '(?!\w)(?:\s*\(([^()]*+(?:\((?1)\)[^()]*+)*+)\))?/s',
                function ($matches) use ($handler) {
                    $expression = $matches[1] ?? null;

                    return call_user_func($handler, $expression);
                },
                $content
            );
        }

        return $content;
    }

    protected function compileOld(string $content): string
    {
        return preg_replace_callback(
            '/@old\s*\(\s*[\'"](.+?)[\'"]\s*(?:,\s*[\'"]?(.*?)[\'"]?)?\s*\)/s',
            function ($matches) {
                $key = addslashes($matches[1]);
                $default = isset($matches[2]) ? addslashes($matches[2]) : '';

                return sprintf(
                    '<?php echo htmlspecialchars((string)(isset($old) && is_callable($old) ? $old(\'%s\', \'%s\') : (isset($_SESSION[\'_old_input\'][\'%s\']) ? $_SESSION[\'_old_input\'][\'%s\'] : \'%s\')), ENT_QUOTES, \'UTF-8\'); ?>',
                    $key,
                    $default,
                    $key,
                    $key,
                    $default
                );
            },
            $content
        );
    }

    protected function compileFlashMessages(string $content): string
    {
        $content = preg_replace_callback(
            '/@success\s*(?:\r?\n)?/',
            function ($matches) {
                return '<?php if (isset($_SESSION[\'_success\'])): $message = $_SESSION[\'_success\']; unset($_SESSION[\'_success\']); ?>';
            },
            $content
        );

        $content = preg_replace('/@endsuccess\s*(?:\r?\n)?/', '<?php unset($message); endif; ?>', $content);

        return $content;
    }

    protected function compileHelperDirectives(string $content): string
    {
        // Date & Time
        $content = $this->compileDate($content);
        $content = $this->compileTime($content);
        $content = $this->compileDatetime($content);
        $content = $this->compileHumanDate($content);
        $content = $this->compileDiffForHumans($content);

        // Numbers & Currency
        $content = $this->compileNumber($content);
        $content = $this->compileCurrency($content);
        $content = $this->compilePercent($content);

        // String Manipulation
        $content = $this->compileUpper($content);
        $content = $this->compileLower($content);
        $content = $this->compileTitle($content);
        $content = $this->compileTitleTruncate($content);
        $content = $this->compileSlug($content);
        $content = $this->compileTruncate($content);
        $content = $this->compileExcerpt($content);
        $content = $this->compileUcfirst($content);

        // Arrays & Collections
        $content = $this->compileCount($content);
        $content = $this->compileJoin($content);
        $content = $this->compileImplode($content);

        // Utility
        $content = $this->compileDefault($content);
        $content = $this->compileRoute($content);
        $content = $this->compileAsset($content);
        $content = $this->compileUrl($content);
        $content = $this->compileConfig($content);
        $content = $this->compileEnv($content);
        $content = $this->compileJsonScript($content);
        $content = $this->compileClass($content);
        $content = $this->compileStyle($content);
        $content = $this->compileVite($content);
        $content = $this->compileFlush($content);

        return $content;
    }

    protected function compileFlush(string $content): string
    {
        return preg_replace('/@flush\s*/', '<?php flush(); ?>', $content);
    }

    protected function compileFormHelpers(string $content): string
    {
        $balanced = '([^()]*+(?:\((?1)\)[^()]*+)*+)';

        // @checked(condition)
        $content = preg_replace(
            '/@checked\s*\(' . $balanced . '\)/',
            '<?php if($1) echo "checked"; ?>',
            $content
        );

        // @selected(condition)
        $content = preg_replace(
            '/@selected\s*\(' . $balanced . '\)/',
            '<?php if($1) echo "selected"; ?>',
            $content
        );

        // @disabled(condition)
        $content = preg_replace(
            '/@disabled\s*\(' . $balanced . '\)/',
            '<?php if($1) echo "disabled"; ?>',
            $content
        );

        // @readonly(condition)
        $content = preg_replace(
            '/@readonly\s*\(' . $balanced . '\)/',
            '<?php if($1) echo "readonly"; ?>',
            $content
        );

        // @required(condition)
        $content = preg_replace(
            '/@required\s*\(' . $balanced . '\)/',
            '<?php if($1) echo "required"; ?>',
            $content
        );

        return $content;
    }

    protected function compileInject(string $content): string
    {
        return preg_replace_callback(
            '/@inject\s*\(\s*[\'"](.+?)[\'"]\s*,\s*[\'"](.+?)[\'"]\s*\)/',
            function ($matches) {
                $variable = $matches[1];
                $service = $matches[2];

                return sprintf(
                    '<?php $%s = \Plugs\Container\Container::getInstance()->make(\'%s\'); ?>',
                    $variable,
                    $service
                );
            },
            $content
        );
    }

    /**
     * Compile tag-based form/utility directives.
     */
    protected function compileFormTags(string $content): string
    {
        $attrRegex = '((?:[^>"\']+|"[^"]*"|\'[^\']*\')*)';

        // <csrf />
        $content = preg_replace('/<csrf\s*\/?>/is', '@csrf', $content);

        // 2. <method type="..." />
        $content = preg_replace_callback('/<method' . $attrRegex . '\/?>/is', function ($m) {
            $attrs = $this->parseAttributes($m[1]);
            if (isset($attrs['type'])) {
                return "@method('{$attrs['type']['value']}')";
            }
            return $m[0];
        }, $content);

        // <error field="..." />
        $content = preg_replace_callback('/<error' . $attrRegex . '>/is', function ($m) {
            $attrs = $this->parseAttributes($m[1]);
            if (isset($attrs['field'])) {
                if (str_ends_with(trim($m[1]), '/')) {
                    return "@error('{$attrs['field']['value']}') <?php echo \$message; ?> @enderror";
                }
                return "@error('{$attrs['field']['value']}')";
            }
            return $m[0];
        }, $content);
        $content = preg_replace('/<\/error\s*>/is', '@enderror', $content);


        // 4. <errors> ... </errors>
        $content = preg_replace('/<errors\s*>/is', '@errors', $content);
        $content = preg_replace('/<\/errors\s*>/is', '@enderrors', $content);

        // <class :map="..." />
        $content = preg_replace_callback('/<class' . $attrRegex . '\/?>/is', function ($m) {
            $attrs = $this->parseAttributes($m[1]);
            if (isset($attrs['map'])) {
                return "@class({$attrs['map']['value']})";
            }
            return $m[0];
        }, $content);

        // <style :map="..." />
        $content = preg_replace_callback('/<style' . $attrRegex . '\/?>/is', function ($m) {
            $attrs = $this->parseAttributes($m[1]);
            if (isset($attrs['map'])) {
                return "@style({$attrs['map']['value']})";
            }
            return $m[0];
        }, $content);

        // 7. <once [key="..."]> ... </once>
        $content = preg_replace_callback('/<once\s*([^>]*)>(.*?)<\/once>/is', function ($m) {
            $inner = $m[2];
            if (trim($m[1]) === '')
                return "@once{$inner}@endonce";

            $attrs = $this->parseAttributes($m[1]);
            $key = isset($attrs['key']) ? "('{$attrs['key']['value']}')" : "";
            return "@once{$key}{$inner}@endonce";
        }, $content);

        // <stream view="..." [:data="..."] />
        $content = preg_replace_callback('/<stream' . $attrRegex . '\/?>/is', function ($m) {
            $attrs = $this->parseAttributes($m[1]);
            if (isset($attrs['view'])) {
                $view = $attrs['view']['value'];
                $data = $attrs['data']['value'] ?? '$__data ?? []';
                return "@stream('{$view}', {$data})";
            }
            return $m[0];
        }, $content);

        // <checked :when="..." />
        $content = preg_replace_callback('/<checked' . $attrRegex . '\/?>/is', function ($m) {
            $attrs = $this->parseAttributes($m[1]);
            if (isset($attrs['when'])) {
                return "@checked({$attrs['when']['value']})";
            }
            return $m[0];
        }, $content);

        // <selected :when="..." />
        $content = preg_replace_callback('/<selected' . $attrRegex . '\/?>/is', function ($m) {
            $attrs = $this->parseAttributes($m[1]);
            if (isset($attrs['when'])) {
                return "@selected({$attrs['when']['value']})";
            }
            return $m[0];
        }, $content);

        // <disabled :when="..." />
        $content = preg_replace_callback('/<disabled' . $attrRegex . '\/?>/is', function ($m) {
            $attrs = $this->parseAttributes($m[1]);
            if (isset($attrs['when'])) {
                return "@disabled({$attrs['when']['value']})";
            }
            return $m[0];
        }, $content);

        // <readonly :when="..." />
        $content = preg_replace_callback('/<readonly\s+([^>]+)\/?>/is', function ($m) {
            $attrs = $this->parseAttributes($m[1]);
            if (isset($attrs['when'])) {
                return "@readonly({$attrs['when']['value']})";
            }
            return $m[0];
        }, $content);

        return $content;
    }
}
