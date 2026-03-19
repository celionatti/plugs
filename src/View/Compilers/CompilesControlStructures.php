<?php

declare(strict_types=1);

namespace Plugs\View\Compilers;

trait CompilesControlStructures
{
    /**
     * Compile the conditional statements in the given string.
     *
     * @param  string  $content
     * @return string
     */
    protected function compileConditionals(string $content): string
    {
        // Balanced parenthesis pattern
        $balanced = '([^()]*+(?:\((?1)\)[^()]*+)*+)';

        // @if
        $content = preg_replace_callback('/@if\s*\(' . $balanced . '\)/s', function ($matches) {
            $expr = $this->convertDotSyntax($matches[1]);
            return "<?php if ({$expr}): ?>";
        }, $content);

        // @elseif
        $content = preg_replace_callback('/@elseif\s*\(' . $balanced . '\)/s', function ($matches) {
            $expr = $this->convertDotSyntax($matches[1]);
            return "<?php elseif ({$expr}): ?>";
        }, $content);

        // @else
        $content = preg_replace('/@else\s*(?:\r?\n)?/', '<?php else: ?>', $content);

        // @endif
        $content = preg_replace('/@endif\s*(?:\r?\n)?/', '<?php endif; ?>', $content);

        // @unless
        $content = preg_replace_callback('/@unless\s*\(' . $balanced . '\)/s', function ($matches) {
            $expr = $this->convertDotSyntax($matches[1]);
            return "<?php if (!({$expr})): ?>";
        }, $content);
        $content = preg_replace('/@endunless\s*(?:\r?\n)?/', '<?php endif; ?>', $content);

        // @isset
        $content = preg_replace_callback('/@isset\s*\(' . $balanced . '\)/s', function ($matches) {
            $expr = $this->convertDotSyntax($matches[1]);
            return "<?php if (isset({$expr})): ?>";
        }, $content);
        $content = preg_replace('/@endisset\s*(?:\r?\n)?/', '<?php endif; ?>', $content);

        // @empty
        $content = preg_replace_callback('/@empty\s*\(' . $balanced . '\)/s', function ($matches) {
            $expr = $this->convertDotSyntax($matches[1]);
            return "<?php if (empty({$expr})): ?>";
        }, $content);
        $content = preg_replace('/@endempty\s*(?:\r?\n)?/', '<?php endif; ?>', $content);

        // @switch
        $content = preg_replace_callback('/@switch\s*\(' . $balanced . '\)/s', function ($matches) {
            $expr = $this->convertDotSyntax($matches[1]);
            return "<?php switch ({$expr}): ?>";
        }, $content);

        $content = preg_replace_callback('/@case\s*\(' . $balanced . '\)/s', function ($matches) {
            $expr = $this->convertDotSyntax($matches[1]);
            return "<?php case {$expr}: ?>";
        }, $content);

        $content = preg_replace('/@default\s*/', '<?php default: ?>', $content);
        $content = preg_replace('/@endswitch\s*(?:\r?\n)?/', '<?php endswitch; ?>', $content);

        return $content;
    }

    /**
     * Compile the loop statements in the given string.
     *
     * @param  string  $content
     * @return string
     */
    protected function compileLoops(string $content): string
    {
        $balanced = '([^()]*+(?:\((?1)\)[^()]*+)*+)';

        // @forelse
        $content = preg_replace_callback(
            '/@forelse\s*\(' . $balanced . '\)(.*?)@empty(.*?)@endforelse/s',
            function ($matches) {
                $head = $matches[1];
                if (!preg_match('/(.+?)\s+as\s+(.+)/s', $head, $headMatches)) {
                    return $matches[0];
                }

                $array = $this->convertDotSyntax(trim($headMatches[1]));
                $iteration = trim($headMatches[2]);
                $loopContent = $matches[2];
                $emptyContent = $matches[3];
                $emptyVar = '__empty_' . md5($array . uniqid());

                $checkIsset = preg_match('/^\$[\w\->]+$/', $array) ? "isset($array) && " : '';

                $initLoop = '$loop = new \Plugs\View\Loop(' . $array . ', $loop ?? null, (($loop->depth ?? 0) + 1));';
                $tick = '$loop->tick(); if (isset($this) && method_exists($this, "isAutoFlushEnabled") && $this->isAutoFlushEnabled() && $loop->shouldFlush($this->getAutoFlushFrequency())) flush();';

                return sprintf(
                    '<?php $%s = true; if(%sis_iterable(%s)): %s foreach (%s as %s): $%s = false; ?>%s<?php %s endforeach; $loop = $loop->parent ?? null; endif; if($%s): ?>%s<?php endif; ?>',
                    $emptyVar,
                    $checkIsset,
                    $array,
                    $initLoop,
                    $array,
                    $iteration,
                    $emptyVar,
                    $loopContent,
                    $tick,
                    $emptyVar,
                    $emptyContent
                );
            },
            $content
        );

        // @foreach
        $content = preg_replace_callback(
            '/@foreach\s*\(' . $balanced . '\)/s',
            function ($matches) {
                $head = $matches[1];
                if (!preg_match('/(.+?)\s+as\s+(.+)/s', $head, $headMatches)) {
                    return $matches[0];
                }

                $array = $this->convertDotSyntax(trim($headMatches[1]));
                $iteration = trim($headMatches[2]);
                $checkIsset = preg_match('/^\$[\w\->]+$/', $array) ? "isset($array) && " : '';

                $initLoop = '$loop = new \Plugs\View\Loop(' . $array . ', $loop ?? null, (($loop->depth ?? 0) + 1));';

                return sprintf(
                    '<?php if(%sis_iterable(%s)): %s foreach (%s as %s): ?>',
                    $checkIsset,
                    $array,
                    $initLoop,
                    $array,
                    $iteration
                );
            },
            $content
        );

        $content = preg_replace('/@endforeach\s*(?:\r?\n)?/', '<?php $loop->tick(); if (isset($this) && method_exists($this, "isAutoFlushEnabled") && $this->isAutoFlushEnabled() && $loop->shouldFlush($this->getAutoFlushFrequency())) flush(); endforeach; $loop = $loop->parent ?? null; endif; ?>', $content);

        // @for
        $content = preg_replace_callback('/@for\s*\(' . $balanced . '\)/s', function ($matches) {
            $expr = $this->convertDotSyntax($matches[1]);
            return "<?php for ({$expr}): ?>";
        }, $content);
        $content = preg_replace('/@endfor\s*(?:\r?\n)?/', '<?php endfor; ?>', $content);

        // @while
        $content = preg_replace_callback('/@while\s*\(' . $balanced . '\)/s', function ($matches) {
            $expr = $this->convertDotSyntax($matches[1]);
            return "<?php while ({$expr}): ?>";
        }, $content);
        $content = preg_replace('/@endwhile\s*(?:\r?\n)?/', '<?php endwhile; ?>', $content);

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

    /**
     * Compile tag-based conditionals: <if :condition="...">
     */
    protected function compileTagConditionals(string $content): string
    {
        $attrRegex = '((?:[^>"\']+|"[^"]*"|\'[^\']*\')*)';

        // <if condition="..." or check="..." or test="..." or :when="...">
        $content = preg_replace_callback('/<if' . $attrRegex . '>/i', function ($m) {
            $attrs = $this->parseAttributes($m[1]);
            $condition = $attrs['condition']['value'] ?? ($attrs['check']['value'] ?? ($attrs['test']['value'] ?? ($attrs['when']['value'] ?? '')));
            if ($condition) {
                return "@if({$condition})";
            }
            return $m[0];
        }, $content);

        // <unless condition="..." or check="..." or test="...">
        $content = preg_replace_callback('/<unless' . $attrRegex . '>/i', function ($m) {
            $attrs = $this->parseAttributes($m[1]);
            $condition = $attrs['condition']['value'] ?? ($attrs['check']['value'] ?? ($attrs['test']['value'] ?? ''));
            if ($condition) {
                return "@unless({$condition})";
            }
            return $m[0];
        }, $content);

        // <elseif condition="..." or check="..." or test="...">
        $content = preg_replace_callback('/<elseif' . $attrRegex . '\s*\/?>/i', function ($m) {
            $attrs = $this->parseAttributes($m[1]);
            $condition = $attrs['condition']['value'] ?? ($attrs['check']['value'] ?? ($attrs['test']['value'] ?? ''));
            if ($condition) {
                return "@elseif({$condition})";
            }
            return $m[0];
        }, $content);

        // <else />
        $content = preg_replace('/<else\s*\/?>/s', '@else', $content);

        // </if> and </unless>
        $content = preg_replace('/<\/if\s*>/s', '@endif', $content);
        $content = preg_replace('/<\/unless\s*>/s', '@endunless', $content);

        // Strip closing tags for elseif/else as they are not needed in Blade/PHP
        $content = preg_replace('/<\/(elseif|else)\s*>/is', '', $content);

        return $content;
    }

    /**
     * Compile tag-based loops: <loop :items="..." as="...">
     */
    protected function compileTagLoops(string $content): string
    {
        $attrRegex = '((?:[^>"\']+|"[^"]*"|\'[^\']*\')*)';

        // <loop items="..." as="..."> OR <foreach items="..." as="...">
        $content = preg_replace_callback('/<(loop|foreach)' . $attrRegex . '>/i', function ($m) {
            $attrs = $this->parseAttributes($m[2]);

            $items = $attrs['items']['value'] ?? ($attrs['from']['value'] ?? '');
            $as = $attrs['as']['value'] ?? '';

            if ($items && $as) {
                return "@foreach({$items} as {$as})";
            }
            return $m[0];
        }, $content);

        // <forelse items="..." as="...">
        $content = preg_replace_callback('/<forelse' . $attrRegex . '>/i', function ($m) {
            $attrs = $this->parseAttributes($m[1]);

            $items = $attrs['items']['value'] ?? ($attrs['from']['value'] ?? '');
            $as = $attrs['as']['value'] ?? '';

            if ($items && $as) {
                return "@forelse({$items} as {$as})";
            }
            return $m[0];
        }, $content);

        // <while condition="...">
        $content = preg_replace_callback('/<while' . $attrRegex . '>/i', function ($m) {
            $attrs = $this->parseAttributes($m[1]);
            $condition = $attrs['condition']['value'] ?? ($attrs['check']['value'] ?? ($attrs['test']['value'] ?? ''));

            if ($condition) {
                return "@while({$condition})";
            }
            return $m[0];
        }, $content);

        // <for init="..." condition="..." step="...">
        $content = preg_replace_callback('/<for' . $attrRegex . '>/i', function ($m) {
            $attrs = $this->parseAttributes($m[1]);

            $init = $attrs['init']['value'] ?? '';
            $cond = $attrs['condition']['value'] ?? ($attrs['check']['value'] ?? ($attrs['test']['value'] ?? ''));
            $step = $attrs['step']['value'] ?? '';

            if ($init && $cond && $step) {
                return "@for({$init}; {$cond}; {$step})";
            }
            return $m[0];
        }, $content);

        // Closing tags
        $content = preg_replace('/<\/(loop|foreach)\s*>/is', '@endforeach', $content);
        $content = preg_replace('/<empty\s*\/?>/is', '@empty', $content);
        $content = preg_replace('/<\/forelse\s*>/is', '@endforelse', $content);
        $content = preg_replace('/<\/while\s*>/is', '@endwhile', $content);
        $content = preg_replace('/<\/for\s*>/is', '@endfor', $content);

        return $content;
    }

    /**
     * Compile <php> block.
     */
    protected function compilePhpTag(string $content): string
    {
        return preg_replace_callback('/<php\s*>(.*?)<\/php>/is', function ($m) {
            return "@php{$m[1]}@endphp";
        }, $content) ?? $content;
    }

    /**
     * Compile the @let and @calc directives.
     *
     * @param  string  $content
     * @return string
     */
    protected function compileLet(string $content): string
    {
        $callback = function ($matches) {
            $variable = $matches[1];
            $expression = $this->convertDotSyntax(trim($matches[2]));
            return "<?php \${$variable} = {$expression}; ?>";
        };

        $content = preg_replace_callback('/@let\s+([a-zA-Z0-9_]+)\s*=\s*(.*?)(?=\r?\n|$)/s', $callback, $content);
        $content = preg_replace_callback('/@calc\s+([a-zA-Z0-9_]+)\s*=\s*(.*?)(?=\r?\n|$)/s', $callback, $content);

        return $content;
    }

    /**
     * Compile the @needs directive.
     *
     * @needs user posts
     *
     * Transforms into a runtime check that throws a ViewException
     * if any of the required variables are missing from the view scope.
     *
     * @param  string  $content
     * @return string
     */
    protected function compileNeeds(string $content): string
    {
        return preg_replace_callback(
            '/@needs\s+(.+?)(?=\r?\n|$)/s',
            function ($matches) {
                $varsRaw = trim($matches[1]);
                // Support comma-separated or space-separated variable names
                $vars = preg_split('/[\s,]+/', $varsRaw, -1, PREG_SPLIT_NO_EMPTY);

                if (empty($vars)) {
                    return '';
                }

                $checks = [];
                $names = [];
                foreach ($vars as $var) {
                    $var = ltrim($var, '$'); // Allow @needs $user or @needs user
                    $checks[] = "!isset(\${$var})";
                    $names[] = $var;
                }

                $condition = implode(' || ', $checks);
                $namesList = implode(', ', $names);

                return sprintf(
                    '<?php if (%s) { throw new \Plugs\Exceptions\ViewException('
                    . '"View requires the following variables: %s", 0, null, "", '
                    . '\Plugs\Exceptions\ViewException::MISSING_VARIABLE'
                    . '); } ?>',
                    $condition,
                    $namesList
                );
            },
            $content
        );
    }

    /**
     * Compile the @defaults directive.
     *
     * @defaults(['theme' => 'light', 'showSidebar' => true])
     *
     * Transforms into a PHP block that uses extract() with EXTR_SKIP
     * to only set variables if they are not already defined.
     *
     * @param  string  $content
     * @return string
     */
    protected function compileDefaults(string $content): string
    {
        return preg_replace_callback(
            '/@defaults\s*\((.*?)\)/s',
            function ($matches) {
                // The argument should be an array literal: ['key' => 'val']
                $arrayExpr = trim($matches[1]);
                if (empty($arrayExpr)) {
                    return '';
                }

                return "<?php extract({$arrayExpr}, EXTR_SKIP); ?>";
            },
            $content
        );
    }
}
