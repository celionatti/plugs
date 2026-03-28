<?php

declare(strict_types=1);

namespace Plugs\Css;

/**
 * Assembles generated CSS rules into a final stylesheet.
 *
 * Handles responsive breakpoints, state/pseudo variants,
 * dark mode, CSS reset (Preflight), minification, and
 * writing the compiled output file.
 */
class CssCompiler
{
    private string $outputPath;
    private bool $minify;
    private bool $preflight;
    private string $darkMode;
    private array $breakpoints;
    private bool $fluidTypography;

    /**
     * Variant pseudo-selector mappings.
     */
    private static array $stateVariants = [
        'hover'          => ':hover',
        'focus'          => ':focus',
        'active'         => ':active',
        'focus-within'   => ':focus-within',
        'focus-visible'  => ':focus-visible',
        'disabled'       => ':disabled',
        'first'          => ':first-child',
        'last'           => ':last-child',
        'odd'            => ':nth-child(odd)',
        'even'           => ':nth-child(even)',
        'visited'        => ':visited',
        'checked'        => ':checked',
        'required'       => ':required',
        'invalid'        => ':invalid',
        'placeholder'    => '::placeholder',
        'first-line'     => '::first-line',
        'first-letter'   => '::first-letter',
        'before'         => '::before',
        'after'          => '::after',
        'selection'      => '::selection',
    ];

    /**
     * Group hover needs special parent selector.
     */
    private static array $groupVariants = [
        'group-hover'  => '.group:hover',
        'group-focus'  => '.group:focus',
        'group-active' => '.group:active',
    ];

    /**
     * Stats from the last compilation.
     */
    private array $stats = [
        'total_classes'    => 0,
        'generated_rules'  => 0,
        'skipped'          => 0,
        'responsive'       => [],
        'state_variants'   => [],
        'original_size'    => 0,
        'final_size'       => 0,
        'build_time_ms'    => 0,
    ];

    public function __construct(
        string $outputPath = '',
        bool $minify = true,
        bool $preflight = true,
        string $darkMode = 'media',
        array $breakpoints = [],
        bool $fluidTypography = true
    ) {
        $this->outputPath = $outputPath;
        $this->minify = $minify;
        $this->preflight = $preflight;
        $this->darkMode = $darkMode;
        $this->fluidTypography = $fluidTypography;
        $this->breakpoints = $breakpoints ?: [
            'sm'  => '640px',
            'md'  => '768px',
            'lg'  => '1024px',
            'xl'  => '1280px',
            '2xl' => '1536px',
        ];
    }

    /**
     * Compile extracted classes into a CSS file.
     *
     * @param string[] $classes All extracted class names
     * @return string The compiled CSS content
     */
    public function compile(array $classes): string
    {
        $start = microtime(true);
        $generator = new UtilityGenerator();

        $this->stats = [
            'total_classes' => count($classes), 'generated_rules' => 0,
            'skipped' => 0, 'responsive' => [], 'state_variants' => [],
            'original_size' => 0, 'final_size' => 0, 'build_time_ms' => 0,
        ];

        // Categorize classes
        $baseRules = [];
        $responsiveRules = [];
        $stateRules = [];
        $darkRules = [];
        $groupRules = [];

        $autoDarkMode = in_array('auto-dark', $classes);

        foreach ($classes as $rawClass) {
            $variant = null;
            $responsive = null;
            $isDark = false;
            $groupVariant = null;
            $class = $rawClass;

            $isFluid = false;

            // Parse variant prefixes: sm:hover:bg-red-500, fluid:text-xl
            $safety = 0;
            while ($this->hasVariantPrefix($class) && $safety++ < 10) {
                [$prefix, $rest] = $this->splitFirstVariant($class);

                if (isset($this->breakpoints[$prefix])) {
                    $responsive = $prefix;
                } elseif ($prefix === 'dark') {
                    $isDark = true;
                } elseif ($prefix === 'fluid') {
                    $isFluid = true;
                } elseif (isset(self::$stateVariants[$prefix])) {
                    $variant = $prefix;
                } elseif (isset(self::$groupVariants[$prefix])) {
                    $groupVariant = $prefix;
                }

                $class = $rest;
            }

            // Generate the CSS for the base class
            if ($isFluid) {
                $css = $generator->generateFluid($class);
            } else {
                $css = $generator->generate($class);
            }
            if ($css === null) {
                $this->stats['skipped']++;
                continue;
            }

            $this->stats['generated_rules']++;
            $escapedSelector = $this->escapeSelector($rawClass);

            if ($responsive) {
                $responsiveRules[$responsive][] = $this->buildRule($escapedSelector, $css, $variant, $groupVariant);
                $this->stats['responsive'][$responsive] = ($this->stats['responsive'][$responsive] ?? 0) + 1;
            } elseif ($isDark) {
                $darkRules[] = $this->buildRule($escapedSelector, $css, $variant, $groupVariant);
            } elseif ($groupVariant) {
                $groupRules[] = $this->buildGroupRule($escapedSelector, $css, $groupVariant);
            } elseif ($variant) {
                $stateRules[] = $this->buildRule($escapedSelector, $css, $variant);
                $this->stats['state_variants'][$variant] = ($this->stats['state_variants'][$variant] ?? 0) + 1;
            } else {
                $baseRules[] = ".{$escapedSelector} { {$css} }";

                // Auto Dark Mode Logic: Generate inversion rule if auto-dark is present
                if ($autoDarkMode && $rawClass !== 'auto-dark') {
                    $darkCss = $generator->getAutoDarkCounterpart($rawClass);
                    if ($darkCss) {
                        $darkRules[] = ".{$escapedSelector}.auto-dark { {$darkCss} }";
                    }
                }
            }
        }

        // Assemble final CSS
        $output = $this->assembleOutput($baseRules, $responsiveRules, $stateRules, $darkRules, $groupRules);

        $this->stats['original_size'] = strlen($output);

        if ($this->minify) {
            $output = $this->minifyCss($output);
        }

        $this->stats['final_size'] = strlen($output);
        $this->stats['build_time_ms'] = round((microtime(true) - $start) * 1000, 1);

        return $output;
    }

    /**
     * Compile and write to the output file.
     *
     * @param string[] $classes
     * @return string The compiled CSS
     */
    public function compileAndWrite(array $classes): string
    {
        $css = $this->compile($classes);

        if (!empty($this->outputPath)) {
            $dir = dirname($this->outputPath);
            if (!is_dir($dir)) {
                mkdir($dir, 0755, true);
            }
            file_put_contents($this->outputPath, $css);
        }

        return $css;
    }

    /**
     * Generate the <link> tag for the compiled CSS.
     */
    public static function linkTag(): string
    {
        $config = function_exists('config') ? config('css', []) : [];
        $path = $config['output'] ?? 'public/build/plugs.css';

        // Convert filesystem path to URL path
        $url = '/build/plugs.css';
        if (str_contains($path, '/')) {
            $parts = explode('public/', $path, 2);
            if (isset($parts[1])) {
                $url = '/' . ltrim($parts[1], '/');
            }
        }

        // Cache bust with file mtime
        $basePath = defined('BASE_PATH') ? BASE_PATH : (defined('ROOT_PATH') ? ROOT_PATH : getcwd());
        $fullPath = rtrim($basePath, '/\\') . DIRECTORY_SEPARATOR . ltrim(str_replace('/', DIRECTORY_SEPARATOR, $path), '/\\');

        $version = '';
        if (file_exists($fullPath)) {
            $version = '?v=' . filemtime($fullPath);
        }

        return sprintf('<link rel="stylesheet" href="%s%s">', $url, $version);
    }

    // ─── Internal helpers ────────────────────────────────

    private function hasVariantPrefix(string $class): bool
    {
        if (!str_contains($class, ':')) return false;
        $prefix = substr($class, 0, strpos($class, ':'));
        return isset($this->breakpoints[$prefix])
            || isset(self::$stateVariants[$prefix])
            || isset(self::$groupVariants[$prefix])
            || $prefix === 'dark'
            || $prefix === 'fluid';
    }

    private function splitFirstVariant(string $class): array
    {
        $pos = strpos($class, ':');
        return [substr($class, 0, $pos), substr($class, $pos + 1)];
    }

    private function escapeSelector(string $class): string
    {
        // Escape special CSS selector characters
        $special = [':', '/', '.', '[', ']', '#', '(', ')', '%', ',', '!', '@'];
        $escaped = $class;
        foreach ($special as $char) {
            $escaped = str_replace($char, '\\' . $char, $escaped);
        }
        return $escaped;
    }

    private function buildRule(string $selector, string $css, ?string $stateVariant = null, ?string $groupVariant = null): string
    {
        if ($groupVariant) {
            return $this->buildGroupRule($selector, $css, $groupVariant);
        }

        $pseudo = $stateVariant ? (self::$stateVariants[$stateVariant] ?? '') : '';
        return ".{$selector}{$pseudo} { {$css} }";
    }

    private function buildGroupRule(string $selector, string $css, string $groupVariant): string
    {
        $parentSelector = self::$groupVariants[$groupVariant] ?? '.group:hover';
        return "{$parentSelector} .{$selector} { {$css} }";
    }

    private function assembleOutput(array $base, array $responsive, array $state, array $dark, array $group): string
    {
        $sections = [];

        // Header
        $sections[] = "/*! Plugs CSS v1.0 | Built " . date('Y-m-d H:i:s') . " | https://plugsframework.com */";

        // Preflight
        if ($this->preflight) {
            $sections[] = Preflight::css(['fluid_typography' => $this->fluidTypography]);
        }

        // Base utilities
        if (!empty($base)) {
            $sections[] = "/* === Base Utilities === */";
            $sections[] = implode("\n", $base);
        }

        // State variants
        if (!empty($state)) {
            $sections[] = "\n/* === State Variants === */";
            $sections[] = implode("\n", $state);
        }

        // Group variants
        if (!empty($group)) {
            $sections[] = "\n/* === Group Variants === */";
            $sections[] = implode("\n", $group);
        }

        // Dark mode
        if (!empty($dark)) {
            $sections[] = "\n/* === Dark Mode === */";
            if ($this->darkMode === 'class') {
                $sections[] = ".dark {\n  " . implode("\n  ", $dark) . "\n}";
            } else {
                $sections[] = "@media (prefers-color-scheme: dark) {\n  " . implode("\n  ", $dark) . "\n}";
            }
        }

        // Responsive variants (mobile-first, ascending)
        foreach ($this->breakpoints as $bp => $minWidth) {
            if (!isset($responsive[$bp]) || empty($responsive[$bp])) continue;
            $sections[] = "\n/* === Responsive: {$bp} ({$minWidth}) === */";
            $sections[] = "@media (min-width: {$minWidth}) {\n  " . implode("\n  ", $responsive[$bp]) . "\n}";
        }

        return implode("\n", $sections) . "\n";
    }

    private function minifyCss(string $css): string
    {
        // Remove comments (but keep /*! ... */ banners)
        $css = preg_replace('/\/\*(?!!)[^*]*\*+([^\/][^*]*\*+)*\//', '', $css);

        // Remove whitespace
        $css = preg_replace('/\s+/', ' ', $css);
        $css = preg_replace('/\s*([{}:;,>~+])\s*/', '$1', $css);
        $css = str_replace(';}', '}', $css);

        // Remove empty rules
        $css = preg_replace('/[^{}]+\{\s*\}/', '', $css);

        return trim($css);
    }

    /**
     * Get statistics from the last compilation.
     */
    public function getStats(): array
    {
        return $this->stats;
    }

    /**
     * Get output path.
     */
    public function getOutputPath(): string
    {
        return $this->outputPath;
    }
}
