<?php

declare(strict_types=1);

namespace Plugs\View\Compilers;

trait CompilesFormatDirectives
{
    /**
     * Compile the date statements in the given string.
     *
     * @param  string  $content
     * @return string
     */
    protected function compileDate(string $content): string
    {
        $balanced = '([^()]*+(?:\((?1)\)[^()]*+)*+)';

        return preg_replace_callback(
            '/@date\s*\(' . $balanced . '\)/s',
            function ($matches) {
                $head = $matches[1];
                if (!preg_match('/^(.+?)\s*,\s*[\'"](.+?)[\'"]$/s', $head, $headMatches)) {
                    return $matches[0];
                }

                $timestamp = trim($headMatches[1]);
                $format = $headMatches[2];

                return sprintf(
                    '<?php echo (function($ts, $fmt) {
                    if ($ts instanceof DateTime || $ts instanceof DateTimeInterface) {
                        return $ts->format($fmt);
                    } elseif (is_numeric($ts)) {
                        return date($fmt, (int)$ts);
                    } else {
                        return date($fmt, strtotime($ts));
                    }
                })(%s, \'%s\'); ?>',
                    $timestamp,
                    addslashes($format)
                );
            },
            $content
        );
    }

    /**
     * Compile the time statements in the given string.
     *
     * @param  string  $content
     * @return string
     */
    protected function compileTime(string $content): string
    {
        $balanced = '([^()]*+(?:\((?1)\)[^()]*+)*+)';

        return preg_replace_callback(
            '/@time\s*\(' . $balanced . '\)/s',
            function ($matches) {
                $head = $matches[1];
                $parts = preg_split('/\s*,\s*/', $head, 2);
                $timestamp = trim($parts[0]);
                $format = isset($parts[1]) ? trim($parts[1], ' "\'') : 'H:i:s';

                return sprintf(
                    '<?php echo (function($ts, $fmt) {
                    if ($ts instanceof DateTime || $ts instanceof DateTimeInterface) {
                        return $ts->format($fmt);
                    } elseif (is_numeric($ts)) {
                        return date($fmt, (int)$ts);
                    } else {
                        return date($fmt, strtotime($ts));
                    }
                })(%s, \'%s\'); ?>',
                    $timestamp,
                    addslashes($format)
                );
            },
            $content
        );
    }

    /**
     * Compile the datetime statements in the given string.
     *
     * @param  string  $content
     * @return string
     */
    protected function compileDatetime(string $content): string
    {
        $balanced = '([^()]*+(?:\((?1)\)[^()]*+)*+)';

        return preg_replace_callback(
            '/@datetime\s*\(' . $balanced . '\)/s',
            function ($matches) {
                $head = $matches[1];
                $parts = preg_split('/\s*,\s*/', $head, 2);
                $timestamp = trim($parts[0]);
                $format = isset($parts[1]) ? trim($parts[1], ' "\'') : 'Y-m-d H:i:s';

                return sprintf(
                    '<?php echo (function($ts, $fmt) {
                    if ($ts instanceof DateTime || $ts instanceof DateTimeInterface) {
                        return $ts->format($fmt);
                    } elseif (is_numeric($ts)) {
                        return date($fmt, (int)$ts);
                    } else {
                        return date($fmt, strtotime($ts));
                    }
                })(%s, \'%s\'); ?>',
                    $timestamp,
                    addslashes($format)
                );
            },
            $content
        );
    }

    /**
     * Compile the human-friendly date statements in the given string.
     *
     * @param  string  $content
     * @return string
     */
    protected function compileHumanDate(string $content): string
    {
        $balanced = '([^()]*+(?:\((?1)\)[^()]*+)*+)';

        return preg_replace_callback(
            '/@humanDate\s*\(' . $balanced . '\)/s',
            function ($matches) {
                return sprintf(
                    '<?php echo (function($ts) {
                    if ($ts instanceof DateTime || $ts instanceof DateTimeInterface) {
                        return $ts->format(\'F j, Y\');
                    } elseif (is_numeric($ts)) {
                        return date(\'F j, Y\', (int)$ts);
                    } else {
                        return date(\'F j, Y\', strtotime($ts));
                    }
                })(%s); ?>',
                    trim($matches[1])
                );
            },
            $content
        );
    }

    /**
     * Compile the diffForHumans statements in the given string.
     *
     * @param  string  $content
     * @return string
     */
    protected function compileDiffForHumans(string $content): string
    {
        return preg_replace_callback(
            '/@diffForHumans\s*\(\s*(.+?)\s*\)/s',
            function ($matches) {
                $timestamp = trim($matches[1]);

                return sprintf(
                    '<?php echo (function($ts) {
                    if ($ts instanceof DateTime || $ts instanceof DateTimeInterface) {
                        $time = $ts->getTimestamp();
                    } elseif (is_numeric($ts)) {
                        $time = (int)$ts;
                    } elseif (is_string($ts)) {
                        $time = strtotime($ts);
                    } else {
                        return "Invalid date";
                    }
                    
                    $diff = time() - $time;
                    
                    if ($diff < 0) {
                        $diff = abs($diff);
                        if ($diff < 60) return "in " . $diff . " second" . ($diff != 1 ? "s" : "");
                        if ($diff < 3600) return "in " . floor($diff / 60) . " minute" . (floor($diff / 60) != 1 ? "s" : "");
                        if ($diff < 86400) return "in " . floor($diff / 3600) . " hour" . (floor($diff / 3600) != 1 ? "s" : "");
                        if ($diff < 604800) return "in " . floor($diff / 86400) . " day" . (floor($diff / 86400) != 1 ? "s" : "");
                        if ($diff < 2592000) return "in " . floor($diff / 604800) . " week" . (floor($diff / 604800) != 1 ? "s" : "");
                        if ($diff < 31536000) return "in " . floor($diff / 2592000) . " month" . (floor($diff / 2592000) != 1 ? "s" : "");
                        return "in " . floor($diff / 31536000) . " year" . (floor($diff / 31536000) != 1 ? "s" : "");
                    }
                    
                    if ($diff < 60) return $diff . " second" . ($diff != 1 ? "s" : "") . " ago";
                    if ($diff < 3600) return floor($diff / 60) . " minute" . (floor($diff / 60) != 1 ? "s" : "") . " ago";
                    if ($diff < 86400) return floor($diff / 3600) . " hour" . (floor($diff / 3600) != 1 ? "s" : "") . " ago";
                    if ($diff < 604800) return floor($diff / 86400) . " day" . (floor($diff / 86400) != 1 ? "s" : "") . " ago";
                    if ($diff < 2592000) return floor($diff / 604800) . " week" . (floor($diff / 604800) != 1 ? "s" : "") . " ago";
                    if ($diff < 31536000) return floor($diff / 2592000) . " month" . (floor($diff / 2592000) != 1 ? "s" : "") . " ago";
                    return floor($diff / 31536000) . " year" . (floor($diff / 31536000) != 1 ? "s" : "") . " ago";
                })(%s); ?>',
                    $timestamp
                );
            },
            $content
        );
    }

    /**
     * Compile the number statements in the given string.
     *
     * @param  string  $content
     * @return string
     */
    protected function compileNumber(string $content): string
    {
        $balanced = '([^()]*+(?:\((?1)\)[^()]*+)*+)';

        return preg_replace_callback(
            '/@number\s*\(' . $balanced . '\)/s',
            function ($matches) {
                $head = $matches[1];
                $parts = preg_split('/\s*,\s*/', $head, 2);
                $value = trim($parts[0]);
                $decimals = isset($parts[1]) ? (int) trim($parts[1]) : 0;

                return sprintf(
                    '<?php echo number_format(%s, %d); ?>',
                    $value,
                    $decimals
                );
            },
            $content
        );
    }

    /**
     * Compile the currency statements in the given string.
     *
     * @param  string  $content
     * @return string
     */
    protected function compileCurrency(string $content): string
    {
        $balanced = '([^()]*+(?:\((?1)\)[^()]*+)*+)';

        return preg_replace_callback(
            '/@currency\s*\(' . $balanced . '\)/s',
            function ($matches) {
                $head = $matches[1];
                $parts = preg_split('/\s*,\s*/', $head, 2);
                $amount = trim($parts[0]);
                $currency = isset($parts[1]) ? trim($parts[1], ' "\'') : 'USD';

                return sprintf(
                    '<?php echo (function($amt, $curr) {
                    $symbols = [
                        \'USD\' => \'$\', \'EUR\' => \'€\', \'GBP\' => \'£\',
                        \'JPY\' => \'¥\', \'NGN\' => \'₦\', \'INR\' => \'₹\'
                    ];
                    $symbol = $symbols[$curr] ?? $curr . \' \';
                    return $symbol . number_format($amt, 2);
                })(%s, \'%s\'); ?>',
                    $amount,
                    addslashes($currency)
                );
            },
            $content
        );
    }

    /**
     * Compile the percent statements in the given string.
     *
     * @param  string  $content
     * @return string
     */
    protected function compilePercent(string $content): string
    {
        $balanced = '([^()]*+(?:\((?1)\)[^()]*+)*+)';

        return preg_replace_callback(
            '/@percent\s*\(' . $balanced . '\)/s',
            function ($matches) {
                $head = $matches[1];
                $parts = preg_split('/\s*,\s*/', $head, 2);
                $value = trim($parts[0]);
                $decimals = isset($parts[1]) ? (int) trim($parts[1]) : 2;

                return sprintf(
                    '<?php echo number_format(%s, %d) . \'%%\'; ?>',
                    $value,
                    $decimals
                );
            },
            $content
        );
    }

    protected function compileUpper(string $content): string
    {
        $balanced = '([^()]*+(?:\((?1)\)[^()]*+)*+)';

        return preg_replace_callback(
            '/@upper\s*\(' . $balanced . '\)/s',
            function ($matches) {
                $string = trim($matches[1]);
                return sprintf('<?php echo strtoupper(%s); ?>', $string);
            },
            $content
        );
    }

    protected function compileLower(string $content): string
    {
        $balanced = '([^()]*+(?:\((?1)\)[^()]*+)*+)';

        return preg_replace_callback(
            '/@lower\s*\(' . $balanced . '\)/s',
            function ($matches) {
                $string = trim($matches[1]);
                return sprintf('<?php echo strtolower(%s); ?>', $string);
            },
            $content
        );
    }

    protected function compileTitle(string $content): string
    {
        $balanced = '([^()]*+(?:\((?1)\)[^()]*+)*+)';

        return preg_replace_callback(
            '/@title\s*\(' . $balanced . '\)/s',
            function ($matches) {
                $string = trim($matches[1]);
                return sprintf('<?php echo ucwords(strtolower(%s)); ?>', $string);
            },
            $content
        );
    }

    protected function compileTitleTruncate(string $content): string
    {
        $balanced = '([^()]*+(?:\((?1)\)[^()]*+)*+)';

        return preg_replace_callback(
            '/@titleTruncate\s*\(' . $balanced . '\)/s',
            function ($matches) {
                $head = $matches[1];
                $parts = preg_split('/\s*,\s*/', $head, 3);
                $string = trim($parts[0]);
                $length = isset($parts[1]) ? (int) trim($parts[1]) : 100;
                $end = isset($parts[2]) ? trim($parts[2], ' "\'') : '...';

                return sprintf(
                    '<?php echo (function($str, $len, $ending) {
                    $str = ucwords(strtolower($str));
                    return mb_strlen($str) > $len ? mb_substr($str, 0, $len) . $ending : $str;
                })(%s, %d, \'%s\'); ?>',
                    $string,
                    $length,
                    addslashes($end)
                );
            },
            $content
        );
    }

    protected function compileUcfirst(string $content): string
    {
        $balanced = '([^()]*+(?:\((?1)\)[^()]*+)*+)';

        return preg_replace_callback(
            '/@ucfirst\s*\(' . $balanced . '\)/s',
            function ($matches) {
                $string = trim($matches[1]);
                return sprintf('<?php echo ucfirst(%s); ?>', $string);
            },
            $content
        );
    }

    protected function compileSlug(string $content): string
    {
        $balanced = '([^()]*+(?:\((?1)\)[^()]*+)*+)';

        return preg_replace_callback(
            '/@slug\s*\(' . $balanced . '\)/s',
            function ($matches) {
                $string = trim($matches[1]);
                return sprintf(
                    '<?php echo strtolower(trim(preg_replace(\'/[^A-Za-z0-9-]+/\', \'-\', %s), \'-\')); ?>',
                    $string
                );
            },
            $content
        );
    }

    protected function compileTruncate(string $content): string
    {
        $balanced = '([^()]*+(?:\((?1)\)[^()]*+)*+)';

        return preg_replace_callback(
            '/@truncate\s*\(' . $balanced . '\)/s',
            function ($matches) {
                $head = $matches[1];
                $parts = preg_split('/\s*,\s*/', $head, 3);
                $string = trim($parts[0]);
                $length = isset($parts[1]) ? (int) trim($parts[1]) : 100;
                $end = isset($parts[2]) ? trim($parts[2], ' "\'') : '...';

                return sprintf(
                    '<?php echo mb_strlen(%s) > %d ? mb_substr(%s, 0, %d) . \'%s\' : %s; ?>',
                    $string,
                    $length,
                    $string,
                    $length,
                    addslashes($end),
                    $string
                );
            },
            $content
        );
    }

    protected function compileExcerpt(string $content): string
    {
        $balanced = '([^()]*+(?:\((?1)\)[^()]*+)*+)';

        return preg_replace_callback(
            '/@excerpt\s*\(' . $balanced . '\)/s',
            function ($matches) {
                $head = $matches[1];
                $parts = preg_split('/\s*,\s*/', $head, 2);
                $string = trim($parts[0]);
                $length = isset($parts[1]) ? (int) trim($parts[1]) : 150;

                return sprintf(
                    '<?php echo (function($str, $len) {
                    $str = strip_tags($str);
                    if (mb_strlen($str) <= $len) return $str;
                    $str = mb_substr($str, 0, $len);
                    return mb_substr($str, 0, mb_strrpos($str, \' \')) . \'...\';
                })(%s, %d); ?>',
                    $string,
                    $length
                );
            },
            $content
        );
    }

    protected function compileCount(string $content): string
    {
        $balanced = '([^()]*+(?:\((?1)\)[^()]*+)*+)';

        return preg_replace_callback(
            '/@count\s*\(' . $balanced . '\)/s',
            function ($matches) {
                $array = trim($matches[1]);
                return sprintf('<?php echo count(%s); ?>', $array);
            },
            $content
        );
    }

    protected function compileJoin(string $content): string
    {
        $balanced = '([^()]*+(?:\((?1)\)[^()]*+)*+)';

        return preg_replace_callback(
            '/@join\s*\(' . $balanced . '\)/s',
            function ($matches) {
                $head = $matches[1];
                $parts = preg_split('/\s*,\s*/', $head, 2);
                $array = trim($parts[0]);
                $separator = isset($parts[1]) ? trim($parts[1], ' "\'') : '';

                return sprintf(
                    '<?php echo implode(\'%s\', %s); ?>',
                    addslashes($separator),
                    $array
                );
            },
            $content
        );
    }

    protected function compileImplode(string $content): string
    {
        return $this->compileJoin($content);
    }

    protected function compileDefault(string $content): string
    {
        $balanced = '([^()]*+(?:\((?1)\)[^()]*+)*+)';

        return preg_replace_callback(
            '/@default\s*\(' . $balanced . '\)/s',
            function ($matches) {
                $head = $matches[1];
                $parts = preg_split('/\s*,\s*/', $head, 2);
                $value = trim($parts[0]);
                $default = isset($parts[1]) ? trim($parts[1], ' "\'') : '';

                return sprintf(
                    '<?php echo !empty(%s) ? %s : \'%s\'; ?>',
                    $value,
                    $value,
                    addslashes($default)
                );
            },
            $content
        );
    }

    protected function compileRoute(string $content): string
    {
        $balanced = '([^()]*+(?:\((?1)\)[^()]*+)*+)';

        return preg_replace_callback(
            '/@route\s*\(' . $balanced . '\)/s',
            function ($matches) {
                $head = $matches[1];
                $parts = preg_split('/\s*,\s*/', $head, 2);
                $routeName = trim($parts[0], ' "\'');
                $params = isset($parts[1]) ? trim($parts[1]) : '[]';

                return sprintf(
                    '<?php echo function_exists(\'route\') ? route(\'%s\', %s) : \'#\'; ?>',
                    addslashes($routeName),
                    $params
                );
            },
            $content
        );
    }

    protected function compileAsset(string $content): string
    {
        $balanced = '([^()]*+(?:\((?1)\)[^()]*+)*+)';

        return preg_replace_callback(
            '/@asset\s*\(' . $balanced . '\)/s',
            function ($matches) {
                $path = trim($matches[1], ' "\'');

                return sprintf(
                    '<?php echo (function($p) {
                    $docRoot = $_SERVER[\'DOCUMENT_ROOT\'] ?? \'\';
                    $filePath = rtrim($docRoot, \'/\') . \'/\' . ltrim($p, \'/\');
                    $version = file_exists($filePath) ? \'?v=\' . filemtime($filePath) : \'\';
                    $base = rtrim($_SERVER[\'REQUEST_SCHEME\'] . \'://\' . $_SERVER[\'HTTP_HOST\'], \'/\');
                    return $base . \'/\' . ltrim($p, \'/\') . $version;
                })(\'%s\'); ?>',
                    addslashes($path)
                );
            },
            $content
        );
    }

    protected function compileVite(string $content): string
    {
        return preg_replace_callback(
            '/@vite\s*\((.+?)\)/s',
            function ($matches) {
                $entry = $matches[1];

                return sprintf(
                    '<?php echo (function($entry) {
                    $hotFile = public_path("hot");
                    if (file_exists($hotFile)) {
                        $url = trim(file_get_contents($hotFile));
                        $scripts = "";
                        $scripts .= "<script type=\"module\" src=\"{$url}/@vite/client\"></script>";
                        if (is_array($entry)) {
                            foreach ($entry as $e) {
                                $scripts .= "<script type=\"module\" src=\"{$url}/{$e}\"></script>";
                            }
                        } else {
                            $scripts .= "<script type=\"module\" src=\"{$url}/{$entry}\"></script>";
                        }
                        return $scripts;
                    }
                    $manifestFile = public_path("build/manifest.json");
                    if (!file_exists($manifestFile)) {
                        return "<!-- Vite manifest not found -->";
                    }
                    $manifest = json_decode(file_get_contents($manifestFile), true);
                    $output = "";
                    $entries = is_array($entry) ? $entry : [$entry];
                    foreach ($entries as $e) {
                         if (isset($manifest[$e])) {
                             $file = $manifest[$e]["file"];
                             $css = $manifest[$e]["css"] ?? [];
                             $output .= "<script type=\"module\" src=\"/build/{$file}\"></script>";
                             foreach ($css as $c) {
                                 $output .= "<link rel=\"stylesheet\" href=\"/build/{$c}\">";
                             }
                         }
                    }
                    return $output;
                })(%s); ?>',
                    $entry
                );
            },
            $content
        );
    }

    protected function compileUrl(string $content): string
    {
        $balanced = '([^()]*+(?:\((?1)\)[^()]*+)*+)';

        return preg_replace_callback(
            '/@url\s*\(' . $balanced . '\)/s',
            function ($matches) {
                $path = trim($matches[1], ' "\'');

                return sprintf(
                    '<?php echo rtrim($_SERVER[\'REQUEST_SCHEME\'] . \'://\' . $_SERVER[\'HTTP_HOST\'], \'/\') . \'/%s\'; ?>',
                    ltrim(addslashes($path), '/')
                );
            },
            $content
        );
    }

    protected function compileConfig(string $content): string
    {
        $balanced = '([^()]*+(?:\((?1)\)[^()]*+)*+)';

        return preg_replace_callback(
            '/@config\s*\(' . $balanced . '\)/s',
            function ($matches) {
                $head = $matches[1];
                $parts = preg_split('/\s*,\s*/', $head, 2);
                $key = trim($parts[0], ' "\'');
                $default = isset($parts[1]) ? trim($parts[1], ' "\'') : null;

                if ($default !== null) {
                    return sprintf(
                        '<?php echo function_exists(\'config\') ? config(\'%s\', \'%s\') : \'%s\'; ?>',
                        addslashes($key),
                        addslashes($default),
                        addslashes($default)
                    );
                }

                return sprintf(
                    '<?php echo function_exists(\'config\') ? config(\'%s\') : \'\'; ?>',
                    addslashes($key)
                );
            },
            $content
        );
    }

    protected function compileEnv(string $content): string
    {
        $balanced = '([^()]*+(?:\((?1)\)[^()]*+)*+)';

        return preg_replace_callback(
            '/@env\s*\(' . $balanced . '\)/s',
            function ($matches) {
                $head = $matches[1];
                $parts = preg_split('/\s*,\s*/', $head, 2);
                $key = trim($parts[0], ' "\'');
                $default = isset($parts[1]) ? trim($parts[1], ' "\'') : '';

                return sprintf(
                    '<?php echo $_ENV[\'%s\'] ?? getenv(\'%s\') ?: \'%s\'; ?>',
                    addslashes($key),
                    addslashes($key),
                    addslashes($default)
                );
            },
            $content
        );
    }

    /**
     * Compile the class statements in the given string.
     * Support for dynamic class merging like: @class(['p-4', 'font-bold' => $isBold])
     *
     * @param  string  $content
     * @return string
     */
    protected function compileClass(string $content): string
    {
        $balanced = '([^()]*+(?:\((?1)\)[^()]*+)*+)';

        return preg_replace_callback(
            '/@class\s*\(' . $balanced . '\)/s',
            function ($matches) {
                $expression = $matches[1];

                return "class=\"<?php echo \Plugs\View\ComponentAttributes::escapeClass(
                    \Plugs\View\ComponentAttributes::resolveClass($expression)
                ); ?>\"";
            },
            $content
        );
    }

    /**
     * Compile the style statements in the given string.
     * Support for dynamic style merging like: @style(['background: red', 'font-weight: bold' => $isActive])
     *
     * @param  string  $content
     * @return string
     */
    protected function compileStyle(string $content): string
    {
        $balanced = '([^()]*+(?:\((?1)\)[^()]*+)*+)';

        return preg_replace_callback(
            '/@style\s*\(' . $balanced . '\)/s',
            function ($matches) {
                $expression = $matches[1];

                return "style=\"<?php echo \Plugs\View\ComponentAttributes::escapeStyle(
                    \Plugs\View\ComponentAttributes::resolveStyle($expression)
                ); ?>\"";
            },
            $content
        );
    }

    /**
     * Compile the use statements in the given string.
     * Translates @use(App\Models\User) -> <?php use App\Models\User; ?>
     * 
     * @param  string  $content
     * @return string
     */
    protected function compileUse(string $content): string
    {
        $balanced = '([^()]*+(?:\((?1)\)[^()]*+)*+)';

        return preg_replace_callback(
            '/@use\s*\(' . $balanced . '\)/s',
            function ($matches) {
                $head = trim($matches[1]);

                // Allow specifying as e.g. @use('App\Models\User', 'UserAlias') -> use App\Models\User as UserAlias;
                $parts = preg_split('/\s*,\s*/', $head, 2);
                $class = trim($parts[0], ' "\'');

                if (isset($parts[1])) {
                    $alias = trim($parts[1], ' "\'');
                    return sprintf("<?php use %s as %s; ?>", $class, $alias);
                }

                return sprintf("<?php use %s; ?>", $class);
            },
            $content
        );
    }

    /**
     * Compile the json statements in the given string.
     *
     * @param  string  $content
     * @return string
     */
    protected function compileJson(string $content): string
    {
        return preg_replace_callback(
            '/@json\s*\((.+?)\)/s',
            function ($matches) {
                return "<?php echo json_encode({$matches[1]}, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_QUOT); ?>";
            },
            $content
        );
    }

    /**
     * Compile the js statements in the given string.
     *
     * @param  string  $content
     * @return string
     */
    protected function compileJs(string $content): string
    {
        return preg_replace_callback(
            '/@js\s*\((.+?)\)/s',
            function ($matches) {
                return "<?php echo \Plugs\Support\Js::from({$matches[1]}); ?>";
            },
            $content
        );
    }

    /**
     * Compile the translation statements in the given string.
     *
     * @param  string  $content
     * @return string
     */
    protected function compileT(string $content): string
    {
        return preg_replace_callback(
            '/@t\s*\((.+?)\)/s',
            function ($matches) {
                return "<?php echo (function_exists('__') ? __({$matches[1]}) : {$matches[1]}); ?>";
            },
            $content
        );
    }

    /**
     * Compile the debug statements in the given string.
     *
     * @param  string  $content
     * @return string
     */
    protected function compileDebug(string $content): string
    {
        return preg_replace(
            '/@debug/s',
            '<?php var_dump(get_defined_vars()); ?>',
            $content
        );
    }

    /**
     * Compile the @plugcss directive.
     *
     * Outputs a <link> tag pointing to the compiled Plugs CSS utility stylesheet.
     * Usage in templates: @plugcss
     */
    protected function compilePlugCss(string $content): string
    {
        return preg_replace(
            '/@plugcss\b/',
            '<?php echo \Plugs\Css\CssCompiler::linkTag(); ?>',
            $content
        );
    }
}
