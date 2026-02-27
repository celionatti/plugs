<?php

declare(strict_types=1);

namespace Plugs\View\Compilers;

trait CompilesComponents
{
    /**
     * Compile @props directive for component default props
     * Usage: @props(['type' => 'primary', 'size' => 'md'])
     */
    protected function compileProps(string $content): string
    {
        $balanced = '([^()]*+(?:\((?1)\)[^()]*+)*+)';

        return preg_replace_callback(
            '/@props\s*\(' . $balanced . '\)/s',
            function ($matches) {
                $defaults = $matches[1];

                return sprintf(
                    '<?php $__props = %s; foreach ($__props as $__key => $__default) { if (!isset($$__key)) { $$__key = $__default; } } unset($__props, $__key, $__default); ?>',
                    $defaults
                );
            },
            $content
        );
    }

    /**
     * Compile @fragment directive for HTMX/Turbo partial rendering
     * Usage: @fragment('sidebar') ... @endfragment
     */
    protected function compileFragment(string $content): string
    {
        // Start fragment
        $content = preg_replace_callback(
            '/@fragment\s*\([\'"](.+?)[\'"]\)/s',
            function ($matches) {
                $name = addslashes($matches[1]);

                return sprintf(
                    '<?php $__fragmentRenderer = $__fragmentRenderer ?? new \Plugs\View\FragmentRenderer(); $__fragmentRenderer->startFragment(\'%s\'); ?>',
                    $name
                );
            },
            $content
        );

        // End fragment
        $content = preg_replace(
            '/@endfragment\s*/',
            '<?php echo $__fragmentRenderer->endFragment(); ?>',
            $content
        );

        return $content;
    }

    /**
     * Compile @teleport directive for content relocation
     * Usage: @teleport('#modals') ... @endteleport
     */
    protected function compileTeleport(string $content): string
    {
        // Start teleport
        $content = preg_replace_callback(
            '/@teleport\s*\([\'"](.+?)[\'"]\)/s',
            function ($matches) {
                $target = addslashes($matches[1]);

                return sprintf(
                    '<?php $__fragmentRenderer = $__fragmentRenderer ?? new \Plugs\View\FragmentRenderer(); $__fragmentRenderer->startTeleport(\'%s\'); ?>',
                    $target
                );
            },
            $content
        );

        // End teleport
        $content = preg_replace(
            '/@endteleport\s*/',
            '<?php $__fragmentRenderer->endTeleport(); ?>',
            $content
        );

        return $content;
    }

    /**
     * Compile @cache directive for caching view blocks
     * Usage: @cache('sidebar', 3600) ... @endcache
     */
    protected function compileCacheBlocks(string $content): string
    {
        // Start cache block
        $content = preg_replace_callback(
            '/@cache\s*\([\'"](.+?)[\'"](?:\s*,\s*(\d+))?\)/s',
            function ($matches) {
                $key = addslashes($matches[1]);
                $ttl = $matches[2] ?? 'null';

                return sprintf(
                    '<?php $__cacheKey = \'%s\'; $__cacheTtl = %s; if (isset($__viewCache) && $__viewCache->has($__cacheKey)) { echo $__viewCache->get($__cacheKey); } else { ob_start(); ?>',
                    $key,
                    $ttl
                );
            },
            $content
        );

        // End cache block
        $content = preg_replace(
            '/@endcache\s*/',
            '<?php $__cacheContent = ob_get_clean(); if (isset($__viewCache)) { $__viewCache->put($__cacheKey, $__cacheContent, $__cacheTtl ?? null); } echo $__cacheContent; } ?>',
            $content
        );

        return $content;
    }

    /**
     * Compile @lazy directive for lazy loading components
     */
    protected function compileLazy(string $content): string
    {
        $balanced = '([^()]*+(?:\((?1)\)[^()]*+)*+)';

        return preg_replace_callback(
            '/@lazy\s*\(' . $balanced . '\)/s',
            function ($matches) {
                $head = $matches[1];
                $parts = preg_split('/\s*,\s*/', $head, 2);
                $component = trim($parts[0], ' "\'');
                $data = isset($parts[1]) ? trim($parts[1]) : '[]';
                $uniqueId = 'lazy_' . substr(md5($component . uniqid()), 0, 8);

                return sprintf(
                    '<?php 
                    $__lazyId = \'%s\';
                    $__lazyComponent = \'%s\';
                    $__lazyData = %s;
                    echo \'<div id="\' . $__lazyId . \'" data-lazy-component="\' . $__lazyComponent . \'" data-lazy-data="\' . htmlspecialchars(json_encode($__lazyData), ENT_QUOTES) . \'">\';
                    echo \'<div class="lazy-placeholder" style="min-height: 50px; background: #f0f0f0; border-radius: 4px; display: flex; align-items: center; justify-content: center;">\';
                    echo \'<span style="color: #666;">Loading...</span>\';
                    echo \'</div></div>\';
                    unset($__lazyId, $__lazyComponent, $__lazyData);
                    ?>',
                    $uniqueId,
                    addslashes($component),
                    $data
                );
            },
            $content
        );
    }

    /**
     * Compile @aware directive to access parent component data
     */
    protected function compileAware(string $content): string
    {
        $balanced = '([^()]*+(?:\((?1)\)[^()]*+)*+)';

        return preg_replace_callback(
            '/@aware\s*\(' . $balanced . '\)/s',
            function ($matches) {
                $keys = $matches[1];

                return sprintf(
                    '<?php $__awareData = \Plugs\View\ViewCompiler::getParentData(%s); extract($__awareData, EXTR_SKIP); unset($__awareData); ?>',
                    $keys
                );
            },
            $content
        );
    }

    /**
     * Compile @sanitize directive for HTML sanitization
     */
    protected function compileSanitize(string $content): string
    {
        return preg_replace_callback(
            '/@sanitize\s*\((.+?)(?:\s*,\s*[\'"](.+?)[\'"])?\)/s',
            function ($matches) {
                $input = trim($matches[1]);
                $mode = $matches[2] ?? 'default';

                $allowedTags = match ($mode) {
                    'strict' => '',
                    'basic' => '<p><br><strong><em><b><i>',
                    'rich' => '<p><br><strong><em><b><i><ul><ol><li><a><h1><h2><h3><h4><h5><h6><blockquote><code><pre>',
                    default => '<p><br><strong><em><b><i><ul><ol><li><a>',
                };

                return sprintf(
                    '<?php 
                    $__sanitized = strip_tags(%s, \'%s\'); 
                    $__sanitized = preg_replace(\'/\s+on\w+\s*=\s*(["\\\'])(?:(?!\1).)*\1/i\', \'\', $__sanitized);
                    $__sanitized = preg_replace(\'/(href|src|style)\s*=\s*(["\\\'])\s*(javascript|data|vbscript):(?:(?!\2).)*\2/i\', \'\', $__sanitized);
                    echo $__sanitized;
                    unset($__sanitized);
                    ?>',
                    $input,
                    $allowedTags
                );
            },
            $content
        );
    }

    /**
     * Compile @csp directive for automatic Content-Security-Policy meta tag
     */
    protected function compileCsp(string $content): string
    {
        return preg_replace(
            '/@csp\s*/',
            '<?php echo \'<meta http-equiv="Content-Security-Policy" content="default-src \\\'self\\\'; script-src \\\'self\\\' \\\'nonce-\' . ($__cspNonce ?? "") . \'\\\'; style-src \\\'self\\\' \\\'unsafe-inline\\\'; img-src \\\'self\\\' data:;">\'; ?>',
            $content
        );
    }

    /**
     * Compile @id directive for safe element IDs
     */
    protected function compileId(string $content): string
    {
        $balanced = '([^()]*+(?:\((?1)\)[^()]*+)*+)';

        return preg_replace_callback(
            '/@id\s*\(' . $balanced . '\)/s',
            function ($matches) {
                return sprintf('<?php echo \Plugs\View\Escaper::id(%s); ?>', $matches[1]);
            },
            $content
        );
    }

    /**
     * Compile @entangle directive for Livewire-style two-way binding
     */
    protected function compileEntangle(string $content): string
    {
        return preg_replace_callback(
            '/@entangle\s*\([\'"](.+?)[\'"]\)/s',
            function ($matches) {
                $property = addslashes($matches[1]);

                return sprintf(
                    '<?php echo "data-entangle=\"%s\" data-entangle-value=\"" . htmlspecialchars(json_encode($%s ?? null), ENT_QUOTES) . "\""; ?>',
                    $property,
                    $property
                );
            },
            $content
        );
    }

    protected function compileAutofocus(string $content): string
    {
        return preg_replace(
            '/@autofocus(?:\s*\((.+?)\))?/',
            '<?php if(${1:-true}) echo "autofocus"; ?>',
            $content
        );
    }

    protected function compileReadTime(string $content): string
    {
        return preg_replace_callback(
            '/@readtime\s*\((.+?)(?:\s*,\s*(\d+))?(?:\s*,\s*[\'"](.+?)[\'"])?\)/s',
            function ($matches) {
                $input = trim($matches[1]);
                $wpm = $matches[2] ?? '200';
                $format = $matches[3] ?? 'short';

                return sprintf(
                    '<?php
                    echo (function($text, $wpm, $format) {
                        $plainText = strip_tags($text);
                        $wordCount = str_word_count($plainText);
                        $minutes = max(1, (int) ceil($wordCount / $wpm));
                        
                        return match($format) {
                            "minutes" => (string) $minutes,
                            "long" => $minutes . " " . ($minutes === 1 ? "minute" : "minutes") . " read",
                            default => $minutes . " min read",
                        };
                    })(%s, %s, \'%s\');
                    ?>',
                    $input,
                    $wpm,
                    $format
                );
            },
            $content
        );
    }

    protected function compileWordCount(string $content): string
    {
        return preg_replace_callback(
            '/@wordcount\s*\((.+?)\)/s',
            function ($matches) {
                $input = trim($matches[1]);

                return sprintf(
                    '<?php echo str_word_count(strip_tags(%s)); ?>',
                    $input
                );
            },
            $content
        );
    }

    protected function compileAuthDirectives(string $content): string
    {
        // @auth('guard') or @auth
        $content = preg_replace_callback(
            '/@auth\s*(?:\(\s*[\'"](.+?)[\'"]\s*\))?/',
            function ($matches) {
                $guard = $matches[1] ?? 'null';
                if ($guard !== 'null') {
                    $guard = "'{$guard}'";
                }
                return "<?php if(function_exists('auth') && auth({$guard})->check()): ?>";
            },
            $content
        );

        // @guest('guard') or @guest
        $content = preg_replace_callback(
            '/@guest\s*(?:\(\s*[\'"](.+?)[\'"]\s*\))?/',
            function ($matches) {
                $guard = $matches[1] ?? 'null';
                if ($guard !== 'null') {
                    $guard = "'{$guard}'";
                }
                return "<?php if(!function_exists('auth') || auth({$guard})->guest()): ?>";
            },
            $content
        );

        // @role('admin')
        $content = preg_replace_callback(
            '/@role\s*\(\s*[\'"](.+?)[\'"]\s*\)/',
            function ($matches) {
                $role = addslashes($matches[1]);
                return "<?php if(function_exists('auth') && auth()->check() && auth()->user()->hasRole('{$role}')): ?>";
            },
            $content
        );

        // @can('ability')
        $content = preg_replace_callback(
            '/@can\s*\(\s*[\'"](.+?)[\'"]\s*\)/',
            function ($matches) {
                $ability = addslashes($matches[1]);
                return "<?php if(function_exists('auth') && auth()->check() && auth()->user()->can('{$ability}')): ?>";
            },
            $content
        );

        // @cannot('ability')
        $content = preg_replace_callback(
            '/@cannot\s*\(\s*[\'"](.+?)[\'"]\s*\)/',
            function ($matches) {
                $ability = addslashes($matches[1]);
                return "<?php if(!function_exists('auth') || !auth()->check() || !auth()->user()->can('{$ability}')): ?>";
            },
            $content
        );

        $content = strtr($content, [
            '@endcannot' => '<?php endif; ?>',
            '@endguest' => '<?php endif; ?>',
            '@endrole' => '<?php endif; ?>',
            '@endauth' => '<?php endif; ?>',
            '@endcan' => '<?php endif; ?>',
        ]);

        return $content;
    }

    protected function compileEnvironmentDirectives(string $content): string
    {
        // @production
        $content = str_replace(
            '@production',
            "<?php if((getenv('APP_ENV') ?: (\$_ENV['APP_ENV'] ?? 'production')) === 'production'): ?>",
            $content
        );


        // @local
        $content = str_replace(
            '@local',
            "<?php if(in_array(getenv('APP_ENV') ?: (\$_ENV['APP_ENV'] ?? 'production'), ['local', 'development'])): ?>",
            $content
        );

        // @envIs('staging')
        $content = preg_replace_callback(
            '/@envIs\s*\(\s*[\'"](.+?)[\'"]\s*\)/',
            function ($matches) {
                $env = addslashes($matches[1]);
                return "<?php if((getenv('APP_ENV') ?: (\$_ENV['APP_ENV'] ?? 'production')) === '{$env}'): ?>";
            },
            $content
        );

        // @debug
        $content = str_replace(
            '@debug',
            "<?php if(function_exists('config') ? config('app.debug', false) : (filter_var(getenv('APP_DEBUG') ?: (\$_ENV['APP_DEBUG'] ?? false), FILTER_VALIDATE_BOOLEAN))): ?>",
            $content
        );

        $content = strtr($content, [
            '@endproduction' => '<?php endif; ?>',
            '@endlocal' => '<?php endif; ?>',
            '@endenvIs' => '<?php endif; ?>',
            '@enddebug' => '<?php endif; ?>',
        ]);

        return $content;
    }

    protected function compileNonce(string $content): string
    {
        return str_replace(
            '@nonce',
            '<?php echo isset($__cspNonce) ? $__cspNonce : ""; ?>',
            $content
        );
    }

    protected function compileHoneypot(string $content): string
    {
        return preg_replace_callback(
            '/@honeypot\s*(?:\(\s*[\'"](.+?)[\'"]\s*\))?/',
            function ($matches) {
                $fieldName = $matches[1] ?? '_hp_' . substr(md5(uniqid()), 0, 8);
                return '<div style="position:absolute;left:-9999px;top:-9999px;opacity:0;height:0;width:0;overflow:hidden;" aria-hidden="true">'
                    . '<input type="text" name="' . htmlspecialchars($fieldName, ENT_QUOTES) . '" value="" tabindex="-1" autocomplete="off">'
                    . '</div>';
            },
            $content
        );
    }

    protected function compileActive(string $content): string
    {
        return preg_replace_callback(
            '/@active\s*\(\s*[\'"](.+?)[\'"]\s*(?:,\s*[\'"](.+?)[\'"]\s*)?\)/',
            function ($matches) {
                $route = addslashes($matches[1]);
                $class = $matches[2] ?? 'active';

                return "<?php echo (function_exists('request_path') ? request_path() : (\$_SERVER['REQUEST_URI'] ?? '')) === '/{$route}' || (function_exists('request_path') ? request_path() : (\$_SERVER['REQUEST_URI'] ?? '')) === '{$route}' ? '{$class}' : ''; ?>";
            },
            $content
        );
    }

    protected function compileSvg(string $content): string
    {
        return preg_replace_callback(
            '/@svg\s*\(\s*[\'"](.+?)[\'"]\s*(?:,\s*[\'"](.+?)[\'"]\s*)?\)/',
            function ($matches) {
                $icon = addslashes($matches[1]);
                $class = $matches[2] ?? '';

                return sprintf(
                    '<?php echo (function($name, $class) {
                    $paths = [
                        rtrim($_SERVER["DOCUMENT_ROOT"] ?? "", "/") . "/../resources/svg/" . $name . ".svg",
                        rtrim($_SERVER["DOCUMENT_ROOT"] ?? "", "/") . "/assets/svg/" . $name . ".svg",
                    ];
                    foreach ($paths as $path) {
                        if (file_exists($path)) {
                            $svg = file_get_contents($path);
                            if ($class) {
                                $svg = preg_replace("/<svg/", "<svg class=\"" . htmlspecialchars($class, ENT_QUOTES) . "\"", $svg, 1);
                            }
                            return $svg;
                        }
                    }
                    return "<!-- SVG \'$name\' not found -->";
                })(\'%s\', \'%s\'); ?>',
                    $icon,
                    addslashes($class)
                );
            },
            $content
        );
    }

    protected function compileSkeleton(string $content): string
    {
        return preg_replace_callback(
            '/@skeleton\s*\(\s*[\'"](.+?)[\'"]\s*(?:,\s*[\'"](.+?)[\'"]\s*)?(?:,\s*[\'"](.+?)[\'"]\s*)?\)/s',
            function ($matches) {
                return $this->getSkeletonHtml($matches[1], $matches[2] ?? '100%', $matches[3] ?? '20px');
            },
            $content
        );
    }

    protected function compileConfirm(string $content): string
    {
        return preg_replace_callback(
            '/@confirm\s*\(\s*[\'"](.+?)[\'"]\s*\)/',
            function ($matches) {
                $message = htmlspecialchars($matches[1], ENT_QUOTES);
                return 'onclick="return confirm(\'' . addslashes($message) . '\')"';
            },
            $content
        );
    }

    protected function compileTooltip(string $content): string
    {
        return preg_replace_callback(
            '/@tooltip\s*\(\s*[\'"](.+?)[\'"]\s*\)/',
            function ($matches) {
                $text = htmlspecialchars($matches[1], ENT_QUOTES);
                return 'title="' . $text . '" data-tooltip="' . $text . '"';
            },
            $content
        );
    }

    protected function compileAssets(string $content): string
    {
        // @css
        $content = preg_replace_callback(
            '/@css\s*\(\s*[\'\"](.+?)[\'\"](?:\s*,\s*(\[.+?\]))?\s*\)/',
            function ($matches) {
                $href = addslashes($matches[1]);
                if (isset($matches[2])) {
                    $attrsExpr = $matches[2];
                    return '<?php $__cssAttrs = ' . $attrsExpr . '; '
                        . '$__cssExtra = \'\'; '
                        . 'foreach ($__cssAttrs as $__k => $__v) { '
                        . '    if ($__v === true) { $__cssExtra .= \' \' . $__k; } '
                        . '    elseif ($__v !== false && $__v !== null) { $__cssExtra .= \' \' . $__k . \'="\' . htmlspecialchars((string)$__v, ENT_QUOTES, \'UTF-8\') . \'"\'; } '
                        . '} '
                        . 'echo \'<link rel="stylesheet" href="' . $href . '"\' . $__cssExtra . \'>\'; ?>';
                }
                return '<link rel="stylesheet" href="' . $href . '">';
            },
            $content
        );

        // @js
        $content = preg_replace_callback(
            '/@js\s*\(\s*[\'\"](.+?)[\'\"](?:\s*,\s*(\[.+?\]))?\s*\)/',
            function ($matches) {
                $src = addslashes($matches[1]);
                if (isset($matches[2])) {
                    $attrsExpr = $matches[2];
                    return '<?php $__jsAttrs = ' . $attrsExpr . '; '
                        . '$__jsExtra = \'\'; '
                        . 'foreach ($__jsAttrs as $__k => $__v) { '
                        . '    if ($__v === true) { $__jsExtra .= \' \' . $__k; } '
                        . '    elseif ($__v !== false && $__v !== null) { $__jsExtra .= \' \' . $__k . \'="\' . htmlspecialchars((string)$__v, ENT_QUOTES, \'UTF-8\') . \'"\'; } '
                        . '} '
                        . 'echo \'<script src="' . $src . '"\' . $__jsExtra . \'></script>\'; ?>';
                }
                return '<script src="' . $src . '"></script>';
            },
            $content
        );

        return $content;
    }

    protected function compileDump(string $content): string
    {
        $balanced = '([^()]*+(?:\((?1)\)[^()]*+)*+)';

        return preg_replace_callback(
            '/@dump\s*\(' . $balanced . '\)/s',
            function ($matches) {
                $var = trim($matches[1]);
                return sprintf(
                    '<?php if(function_exists("config") ? config("app.debug", false) : true) { echo "<pre style=\"background:#1e1e2e;color:#cdd6f4;padding:12px;border-radius:8px;font-size:13px;overflow-x:auto;margin:8px 0;\">"; var_export(%s); echo "</pre>"; } ?>',
                    $var
                );
            },
            $content
        );
    }

    protected function compileMarkdown(string $content): string
    {
        return preg_replace_callback(
            '/@markdown\s*\n?(.*?)@endmarkdown/s',
            function ($matches) {
                return $this->renderMarkdown($matches[1]);
            },
            $content
        );
    }

    protected function compileMarkdownTag(string $content): string
    {
        return preg_replace_callback(
            '/<markdown\s*>(.*?)<\/markdown>/s',
            function ($matches) {
                return $this->renderMarkdown($matches[1]);
            },
            $content
        );
    }

    protected function compileRawDirective(string $content): string
    {
        // Use a generic pattern if self::$patterns['raw'] is not available in the trait
        $pattern = '/@raw\s*\((.+?)\)/s';
        return preg_replace_callback($pattern, function ($matches) {
            return "<?php echo {$matches[1]}; ?>";
        }, $content);
    }

    protected function renderMarkdown(string $markdown): string
    {
        $md = addslashes(trim($markdown));

        return sprintf(
            '<?php echo (function($md) {
                $md = preg_replace_callback("/```(\\\\w+)?\\\\n(.*?)```/s", function($m) {
                    $lang = $m[1] ?? "";
                    return "<pre><code class=\"language-" . htmlspecialchars($lang) . "\">" . htmlspecialchars($m[2]) . "</code></pre>";
                }, $md);
                $md = preg_replace("/`([^`]+)`/", "<code>$1</code>", $md);
                $md = preg_replace("/^######\\\\s+(.+)$/m", "<h6>$1</h6>", $md);
                $md = preg_replace("/^#####\\\\s+(.+)$/m", "<h5>$1</h5>", $md);
                $md = preg_replace("/^####\\\\s+(.+)$/m", "<h4>$1</h4>", $md);
                $md = preg_replace("/^###\\\\s+(.+)$/m", "<h3>$1</h3>", $md);
                $md = preg_replace("/^##\\\\s+(.+)$/m", "<h2>$1</h2>", $md);
                $md = preg_replace("/^#\\\\s+(.+)$/m", "<h1>$1</h1>", $md);
                $md = preg_replace("/\\\\*\\\\*\\\\*(.+?)\\\\*\\\\*\\\\*/s", "<strong><em>$1</em></strong>", $md);
                $md = preg_replace("/\\\\*\\\\*(.+?)\\\\*\\\\*/s", "<strong>$1</strong>", $md);
                $md = preg_replace("/\\\\*(.+?)\\\\*/s", "<em>$1</em>", $md);
                $md = preg_replace("/\\\\[([^\\\\]]+)\\\\]\\\\(([^)]+)\\\\)/", "<a href=\\\"$2\\\">$1</a>", $md);
                $md = preg_replace("/!\\\\[([^\\\\]]*?)\\\\]\\\\(([^)]+)\\\\)/", "<img src=\\\"$2\\\" alt=\\\"$1\\\">", $md);
                $md = preg_replace("/^>\\\\s+(.+)$/m", "<blockquote>$1</blockquote>", $md);
                $md = preg_replace("/^---$/m", "<hr>", $md);
                $md = preg_replace("/^[\\\\-\\\\*]\\\\s+(.+)$/m", "<li>$1</li>", $md);
                $md = preg_replace("/((?:<li>.*?<\\\\/li>\\\\n?)+)/s", "<ul>$1</ul>", $md);
                $md = preg_replace("/\\\\n\\\\n+/", "</p><p>", trim($md));
                $md = "<p>" . $md . "</p>";
                $md = str_replace("<p></p>", "", $md);
                return $md;
            })(\'%s\'); ?>',
            $md
        );
    }

    protected function getSkeletonHtml(string $type, string $width = '100%', string $height = '20px'): string
    {
        $style = 'background: linear-gradient(90deg, #f0f0f0 25%, #e0e0e0 50%, #f0f0f0 75%); background-size: 200% 100%; animation: skeleton-pulse 1.5s ease-in-out infinite;';
        $border = 'border-radius: 4px;';

        switch ($type) {
            case 'avatar':
            case 'avatar-dark':
                $width = $height = ($width !== '100%' && $width !== '') ? $width : '48px';
                $border = 'border-radius: 50%;';
                break;
            case 'text':
            case 'text-dark':
                $height = ($height !== '20px' && $height !== '') ? $height : '16px';
                break;
            case 'image':
            case 'image-dark':
                $height = ($height !== '20px' && $height !== '') ? $height : '200px';
                break;
            case 'button':
            case 'button-dark':
                $height = ($height !== '20px' && $height !== '') ? $height : '40px';
                $width = ($width !== '100%' && $width !== '') ? $width : '120px';
                $border = 'border-radius: 8px;';
                break;
        }

        if (str_contains($type, 'dark')) {
            $style = 'background: linear-gradient(90deg, #333 25%, #444 50%, #333 75%); background-size: 200% 100%; animation: skeleton-pulse 1.5s ease-in-out infinite;';
        }

        return sprintf(
            '<style>@keyframes skeleton-pulse{0%%{background-position:200%% 0}100%%{background-position:-200%% 0}}</style>' .
            '<div class="skeleton-loader %s" style="display:inline-block;width:%s;height:%s;%s%s"></div>',
            htmlspecialchars($type, ENT_QUOTES),
            htmlspecialchars($width, ENT_QUOTES),
            htmlspecialchars($height, ENT_QUOTES),
            $border,
            $style
        );
    }

    /**
     * Compile tag-based UI and Auth directives.
     */
    protected function compileComponentTags(string $content): string
    {
        // 1. <fragment name="..."> ... </fragment>
        $content = preg_replace_callback('/<fragment\s+([^>]+)>/is', function ($m) {
            $attrs = $this->parseAttributes($m[1]);
            if (isset($attrs['name'])) {
                return "@fragment('{$attrs['name']['value']}')";
            }
            return $m[0];
        }, $content);
        $content = preg_replace('/<\/fragment\s*>/is', '@endfragment', $content);

        // 2. <teleport to="..."> ... </teleport>
        $content = preg_replace_callback('/<teleport\s+([^>]+)>/is', function ($m) {
            $attrs = $this->parseAttributes($m[1]);
            if (isset($attrs['to'])) {
                return "@teleport('{$attrs['to']['value']}')";
            }
            return $m[0];
        }, $content);
        $content = preg_replace('/<\/teleport\s*>/is', '@endteleport', $content);

        // 3. <cache key="..." ttl="..."> ... </cache>
        $content = preg_replace_callback('/<cache\s+([^>]+)>/is', function ($m) {
            $attrs = $this->parseAttributes($m[1]);
            if (isset($attrs['key'])) {
                $ttl = $attrs['ttl']['value'] ?? 'null';
                return "@cache('{$attrs['key']['value']}', {$ttl})";
            }
            return $m[0];
        }, $content);
        $content = preg_replace('/<\/cache\s*>/is', '@endcache', $content);

        // 4. <csp />
        $content = preg_replace('/<csp\s*\/?>/is', '@csp', $content);

        // 5. <id value="..." />
        $content = preg_replace_callback('/<id\s+([^>]+)\/?>/is', function ($m) {
            $attrs = $this->parseAttributes($m[1]);
            if (isset($attrs['value'])) {
                return "@id({$attrs['value']['value']})";
            }
            return $m[0];
        }, $content);

        // 6. <vite entry="..." />
        $content = preg_replace_callback('/<vite\s+([^>]+)\/?>/is', function ($m) {
            $attrs = $this->parseAttributes($m[1]);
            if (isset($attrs['entries'])) {
                return "@vite({$attrs['entries']['value']})";
            }
            if (isset($attrs['entry'])) {
                return "@vite('{$attrs['entry']['value']}')";
            }
            return $m[0];
        }, $content);

        // 7. <skeleton type="..." width="..." height="..." />
        $content = preg_replace_callback('/<skeleton\s+([^>]+)\/?>/is', function ($m) {
            $attrs = $this->parseAttributes($m[1]);
            if (isset($attrs['type'])) {
                $width = $attrs['width']['value'] ?? '100%';
                $height = $attrs['height']['value'] ?? '20px';
                return "@skeleton('{$attrs['type']['value']}', '{$width}', '{$height}')";
            }
            return $m[0];
        }, $content);

        // 8. <skeletonStyles />
        $content = preg_replace('/<skeletonStyles\s*\/?>/is', '@skeletonStyles', $content);

        // 9. <auth> ... </auth>
        $content = preg_replace_callback('/<auth\s*([^>]*)>/is', function ($m) {
            if (trim($m[1]) === '')
                return "@auth";
            $attrs = $this->parseAttributes($m[1]);
            $guard = $attrs['guard']['value'] ?? null;
            return $guard ? "@auth('{$guard}')" : "@auth";
        }, $content);
        $content = preg_replace('/<\/auth\s*>/is', '@endauth', $content);

        // 10. <guest> ... </guest>
        $content = preg_replace_callback('/<guest\s*([^>]*)>/is', function ($m) {
            if (trim($m[1]) === '')
                return "@guest";
            $attrs = $this->parseAttributes($m[1]);
            $guard = $attrs['guard']['value'] ?? null;
            return $guard ? "@guest('{$guard}')" : "@guest";
        }, $content);
        $content = preg_replace('/<\/guest\s*>/is', '@endguest', $content);

        // 11. <production> ... </production>
        $content = preg_replace('/<production\s*>/is', '@production', $content);
        $content = preg_replace('/<\/production\s*>/is', '@endproduction', $content);

        // 12. <env is="..."> ... </env>
        $content = preg_replace_callback('/<env\s+([^>]+)>/is', function ($m) {
            $attrs = $this->parseAttributes($m[1]);
            if (isset($attrs['is'])) {
                return "@envIs('{$attrs['is']['value']}')";
            }
            return $m[0];
        }, $content);
        $content = preg_replace('/<\/env\s*>/is', '@endenvIs', $content);

        // 13. RBAC Tags
        $content = preg_replace_callback('/<can\s+([^>]+)>/is', function ($m) {
            $attrs = $this->parseAttributes($m[1]);
            if (isset($attrs['ability'])) {
                return "@can('{$attrs['ability']['value']}')";
            }
            return $m[0];
        }, $content);
        $content = preg_replace('/<\/can\s*>/is', '@endcan', $content);

        $content = preg_replace_callback('/<cannot\s+([^>]+)>/is', function ($m) {
            $attrs = $this->parseAttributes($m[1]);
            if (isset($attrs['ability'])) {
                return "@cannot('{$attrs['ability']['value']}')";
            }
            return $m[0];
        }, $content);
        $content = preg_replace('/<\/cannot\s*>/is', '@endcannot', $content);

        $content = preg_replace_callback('/<role\s+([^>]+)>/is', function ($m) {
            $attrs = $this->parseAttributes($m[1]);
            if (isset($attrs['name'])) {
                return "@role('{$attrs['name']['value']}')";
            }
            return $m[0];
        }, $content);
        $content = preg_replace('/<\/role\s*>/is', '@endrole', $content);

        // 14. <active route="..." class="..." />
        $content = preg_replace_callback('/<active\s+([^>]+)\/?>/is', function ($m) {
            $attrs = $this->parseAttributes($m[1]);
            if (isset($attrs['route'])) {
                $class = $attrs['class']['value'] ?? 'active';
                return "@active('{$attrs['route']['value']}', '{$class}')";
            }
            return $m[0];
        }, $content);

        // 15. <svg icon="..." class="..." />
        $content = preg_replace_callback('/<svg\s+([^>]+)\/?>/is', function ($m) {
            $attrs = $this->parseAttributes($m[1]);
            if (isset($attrs['icon'])) {
                $class = $attrs['class']['value'] ?? '';
                return "@svg('{$attrs['icon']['value']}', '{$class}')";
            }
            return $m[0];
        }, $content);

        return $content;
    }
}
