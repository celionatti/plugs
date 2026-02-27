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
            return "<?php if ({$matches[1]}): ?>";
        }, $content);

        // @elseif
        $content = preg_replace_callback('/@elseif\s*\(' . $balanced . '\)/s', function ($matches) {
            return "<?php elseif ({$matches[1]}): ?>";
        }, $content);

        // @else
        $content = preg_replace('/@else\s*(?:\r?\n)?/', '<?php else: ?>', $content);

        // @endif
        $content = preg_replace('/@endif\s*(?:\r?\n)?/', '<?php endif; ?>', $content);

        // @unless
        $content = preg_replace_callback('/@unless\s*\(' . $balanced . '\)/s', function ($matches) {
            return "<?php if (!({$matches[1]})): ?>";
        }, $content);
        $content = preg_replace('/@endunless\s*(?:\r?\n)?/', '<?php endif; ?>', $content);

        // @isset
        $content = preg_replace_callback('/@isset\s*\(' . $balanced . '\)/s', function ($matches) {
            return "<?php if (isset({$matches[1]})): ?>";
        }, $content);
        $content = preg_replace('/@endisset\s*(?:\r?\n)?/', '<?php endif; ?>', $content);

        // @empty
        $content = preg_replace_callback('/@empty\s*\(' . $balanced . '\)/s', function ($matches) {
            return "<?php if (empty({$matches[1]})): ?>";
        }, $content);
        $content = preg_replace('/@endempty\s*(?:\r?\n)?/', '<?php endif; ?>', $content);

        // @switch
        $content = preg_replace_callback('/@switch\s*\(' . $balanced . '\)/s', function ($matches) {
            return "<?php switch ({$matches[1]}): ?>";
        }, $content);

        $content = preg_replace_callback('/@case\s*\(' . $balanced . '\)/s', function ($matches) {
            return "<?php case {$matches[1]}: ?>";
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

                $array = trim($headMatches[1]);
                $iteration = trim($headMatches[2]);
                $loopContent = $matches[2];
                $emptyContent = $matches[3];
                $emptyVar = '__empty_' . md5($array . uniqid());

                $checkIsset = preg_match('/^\$[\w]+$/', $array) ? "isset($array) && " : '';

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

                $array = trim($headMatches[1]);
                $iteration = trim($headMatches[2]);
                $checkIsset = preg_match('/^\$[\w]+$/', $array) ? "isset($array) && " : '';

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
            return "<?php for ({$matches[1]}): ?>";
        }, $content);
        $content = preg_replace('/@endfor\s*(?:\r?\n)?/', '<?php endfor; ?>', $content);

        // @while
        $content = preg_replace_callback('/@while\s*\(' . $balanced . '\)/s', function ($matches) {
            return "<?php while ({$matches[1]}): ?>";
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
        $attrRegex = '((?:\s+(?:[^>"\'\/]+|"[^"]*"|\'[^\']*\')*)*?)';

        // <if :condition="...">
        $content = preg_replace_callback('/<if' . $attrRegex . '>/i', function ($m) {
            $attrs = $this->parseAttributes($m[1]);
            if (isset($attrs['condition'])) {
                return "@if({$attrs['condition']['value']})";
            }
            return $m[0];
        }, $content);

        // <unless :condition="...">
        $content = preg_replace_callback('/<unless' . $attrRegex . '>/i', function ($m) {
            $attrs = $this->parseAttributes($m[1]);
            if (isset($attrs['condition'])) {
                return "@unless({$attrs['condition']['value']})";
            }
            return $m[0];
        }, $content);

        // <elseif :condition="...">
        $content = preg_replace_callback('/<elseif' . $attrRegex . '\s*\/?>/i', function ($m) {
            $attrs = $this->parseAttributes($m[1]);
            if (isset($attrs['condition'])) {
                return "@elseif({$attrs['condition']['value']})";
            }
            return $m[0];
        }, $content);

        // <else />
        $content = preg_replace('/<else\s*\/?>/s', '@else', $content);

        // </if> and </unless>
        $content = preg_replace('/<\/if\s*>/s', '@endif', $content);
        $content = preg_replace('/<\/unless\s*>/s', '@endunless', $content);

        return $content;
    }

    /**
     * Compile tag-based loops: <loop :items="..." as="...">
     */
    protected function compileTagLoops(string $content): string
    {
        $attrRegex = '((?:\s+(?:[^>"\'\/]+|"[^"]*"|\'[^\']*\')*)*?)';

        // <loop :items="..." :as="...">
        $content = preg_replace_callback('/<loop' . $attrRegex . '>/i', function ($m) {
            $attrs = $this->parseAttributes($m[1]);

            $items = $attrs['items']['value'] ?? '';
            $as = $attrs['as']['value'] ?? '';

            if ($items && $as) {
                return "@foreach({$items} as {$as})";
            }
            return $m[0];
        }, $content);

        // <forelse :items="..." :as="...">
        $content = preg_replace_callback('/<forelse' . $attrRegex . '>/i', function ($m) {
            $attrs = $this->parseAttributes($m[1]);

            $items = $attrs['items']['value'] ?? '';
            $as = $attrs['as']['value'] ?? '';

            if ($items && $as) {
                return "@forelse({$items} as {$as})";
            }
            return $m[0];
        }, $content);

        // <while :condition="...">
        $content = preg_replace_callback('/<while' . $attrRegex . '>/i', function ($m) {
            $attrs = $this->parseAttributes($m[1]);

            if (isset($attrs['condition'])) {
                return "@while({$attrs['condition']['value']})";
            }
            return $m[0];
        }, $content);

        // <for :init="..." :condition="..." :step="...">
        $content = preg_replace_callback('/<for' . $attrRegex . '>/i', function ($m) {
            $attrs = $this->parseAttributes($m[1]);

            $init = $attrs['init']['value'] ?? '';
            $cond = $attrs['condition']['value'] ?? '';
            $step = $attrs['step']['value'] ?? '';

            if ($init && $cond && $step) {
                return "@for({$init}; {$cond}; {$step})";
            }
            return $m[0];
        }, $content);

        // Closing tags
        $content = preg_replace('/<\/loop\s*>/is', '@endforeach', $content);
        $content = preg_replace('/<empty\s*\/?>/is', '@empty', $content);
        $content = preg_replace('/<\/forelse\s*>/is', '@endforelse', $content);
        $content = preg_replace('/<\/while\s*>/is', '@endwhile', $content);
        $content = preg_replace('/<\/for\s*>/is', '@endfor', $content);

        return $content;
    }
}
