<?php

declare(strict_types=1);

namespace Plugs\Css;

class UtilityGenerator
{
    private static array $spacingScale = [
        '0' => '0px', 'px' => '1px', '0.5' => '0.125rem', '1' => '0.25rem',
        '1.5' => '0.375rem', '2' => '0.5rem', '2.5' => '0.625rem', '3' => '0.75rem',
        '3.5' => '0.875rem', '4' => '1rem', '5' => '1.25rem', '6' => '1.5rem',
        '7' => '1.75rem', '8' => '2rem', '9' => '2.25rem', '10' => '2.5rem',
        '11' => '2.75rem', '12' => '3rem', '14' => '3.5rem', '16' => '4rem',
        '20' => '5rem', '24' => '6rem', '28' => '7rem', '32' => '8rem',
        '36' => '9rem', '40' => '10rem', '44' => '11rem', '48' => '12rem',
        '52' => '13rem', '56' => '14rem', '60' => '15rem', '64' => '16rem',
        '72' => '18rem', '80' => '20rem', '96' => '24rem',
        'auto' => 'auto', 'full' => '100%', 'screen' => '100vw',
    ];

    private array $cache = [];

    public function generate(string $className): ?string
    {
        if (isset($this->cache[$className])) {
            return $this->cache[$className];
        }

        $css = $this->doGenerate($className);
        if ($css !== null) {
            $this->cache[$className] = $css;
        }
        return $css;
    }

    private function doGenerate(string $raw): ?string
    {
        // Strip variant prefixes for generation (hover:, sm:, dark:, etc.)
        $class = $this->stripVariants($raw);
        $isNegative = str_starts_with($class, '-');
        $baseClass = $isNegative ? substr($class, 1) : $class;

        return $this->trySpacing($baseClass, $isNegative)
            ?? $this->tryTypography($baseClass)
            ?? $this->tryColor($baseClass)
            ?? $this->trySizing($baseClass, $isNegative)
            ?? $this->tryLayout($baseClass)
            ?? $this->tryBorder($baseClass)
            ?? $this->tryEffect($baseClass)
            ?? $this->tryTransition($baseClass)
            ?? $this->tryTransform($baseClass, $isNegative)
            ?? $this->tryPosition($baseClass, $isNegative)
            ?? $this->tryMisc($baseClass)
            ?? $this->tryArbitrary($baseClass);
    }

    private function stripVariants(string $class): string
    {
        $variants = ['hover:', 'focus:', 'active:', 'focus-within:', 'focus-visible:',
            'disabled:', 'first:', 'last:', 'odd:', 'even:', 'group-hover:',
            'dark:', 'sm:', 'md:', 'lg:', 'xl:', '2xl:'];
        foreach ($variants as $v) {
            if (str_starts_with($class, $v)) {
                return substr($class, strlen($v));
            }
        }
        return $class;
    }

    // ─── Spacing ──────────────────────────────────────────
    private function trySpacing(string $c, bool $neg): ?string
    {
        $map = [
            'p'  => ['padding'], 'px' => ['padding-left', 'padding-right'],
            'py' => ['padding-top', 'padding-bottom'], 'pt' => ['padding-top'],
            'pr' => ['padding-right'], 'pb' => ['padding-bottom'], 'pl' => ['padding-left'],
            'm'  => ['margin'], 'mx' => ['margin-left', 'margin-right'],
            'my' => ['margin-top', 'margin-bottom'], 'mt' => ['margin-top'],
            'mr' => ['margin-right'], 'mb' => ['margin-bottom'], 'ml' => ['margin-left'],
            'gap' => ['gap'], 'gap-x' => ['column-gap'], 'gap-y' => ['row-gap'],
        ];

        // space-x-N, space-y-N
        if (preg_match('/^space-(x|y)-(.+)$/', $c, $m)) {
            $val = self::$spacingScale[$m[2]] ?? null;
            if (!$val) return null;
            $prop = $m[1] === 'x' ? 'margin-left' : 'margin-top';
            return "$prop: $val;";
        }

        foreach ($map as $prefix => $props) {
            if (preg_match('/^' . preg_quote($prefix, '/') . '-(.+)$/', $c, $m)) {
                $val = self::$spacingScale[$m[1]] ?? null;
                if (!$val) return null;
                if ($neg && $val !== 'auto') $val = '-' . $val;
                return implode('; ', array_map(fn($p) => "$p: $val", $props)) . ';';
            }
        }
        return null;
    }

    // ─── Typography ───────────────────────────────────────
    private function tryTypography(string $c): ?string
    {
        $sizes = [
            'text-xs' => 'font-size: 0.75rem; line-height: 1rem;',
            'text-sm' => 'font-size: 0.875rem; line-height: 1.25rem;',
            'text-base' => 'font-size: 1rem; line-height: 1.5rem;',
            'text-lg' => 'font-size: 1.125rem; line-height: 1.75rem;',
            'text-xl' => 'font-size: 1.25rem; line-height: 1.75rem;',
            'text-2xl' => 'font-size: 1.5rem; line-height: 2rem;',
            'text-3xl' => 'font-size: 1.875rem; line-height: 2.25rem;',
            'text-4xl' => 'font-size: 2.25rem; line-height: 2.5rem;',
            'text-5xl' => 'font-size: 3rem; line-height: 1;',
            'text-6xl' => 'font-size: 3.75rem; line-height: 1;',
            'text-7xl' => 'font-size: 4.5rem; line-height: 1;',
            'text-8xl' => 'font-size: 6rem; line-height: 1;',
            'text-9xl' => 'font-size: 8rem; line-height: 1;',
        ];
        if (isset($sizes[$c])) return $sizes[$c];

        $weights = [
            'font-thin' => '100', 'font-extralight' => '200', 'font-light' => '300',
            'font-normal' => '400', 'font-medium' => '500', 'font-semibold' => '600',
            'font-bold' => '700', 'font-extrabold' => '800', 'font-black' => '900',
        ];
        if (isset($weights[$c])) return "font-weight: {$weights[$c]};";

        $textUtils = [
            'italic' => 'font-style: italic;', 'not-italic' => 'font-style: normal;',
            'underline' => 'text-decoration-line: underline;',
            'overline' => 'text-decoration-line: overline;',
            'line-through' => 'text-decoration-line: line-through;',
            'no-underline' => 'text-decoration-line: none;',
            'uppercase' => 'text-transform: uppercase;',
            'lowercase' => 'text-transform: lowercase;',
            'capitalize' => 'text-transform: capitalize;',
            'normal-case' => 'text-transform: none;',
            'text-left' => 'text-align: left;', 'text-center' => 'text-align: center;',
            'text-right' => 'text-align: right;', 'text-justify' => 'text-align: justify;',
            'truncate' => 'overflow: hidden; text-overflow: ellipsis; white-space: nowrap;',
            'whitespace-nowrap' => 'white-space: nowrap;',
            'whitespace-normal' => 'white-space: normal;',
            'whitespace-pre' => 'white-space: pre;',
            'break-words' => 'overflow-wrap: break-word;',
            'break-all' => 'word-break: break-all;',
            'break-normal' => 'overflow-wrap: normal; word-break: normal;',
            'antialiased' => '-webkit-font-smoothing: antialiased; -moz-osx-font-smoothing: grayscale;',
            'subpixel-antialiased' => '-webkit-font-smoothing: auto; -moz-osx-font-smoothing: auto;',
            'font-sans' => "font-family: ui-sans-serif, system-ui, sans-serif, 'Apple Color Emoji', 'Segoe UI Emoji';",
            'font-serif' => "font-family: ui-serif, Georgia, Cambria, 'Times New Roman', Times, serif;",
            'font-mono' => "font-family: ui-monospace, SFMono-Regular, Menlo, Monaco, Consolas, 'Liberation Mono', monospace;",
        ];
        if (isset($textUtils[$c])) return $textUtils[$c];

        $tracking = [
            'tracking-tighter' => '-0.05em', 'tracking-tight' => '-0.025em',
            'tracking-normal' => '0em', 'tracking-wide' => '0.025em',
            'tracking-wider' => '0.05em', 'tracking-widest' => '0.1em',
        ];
        if (isset($tracking[$c])) return "letter-spacing: {$tracking[$c]};";

        if (preg_match('/^leading-(\d+)$/', $c, $m)) {
            $val = (float) $m[1] * 0.25;
            return "line-height: {$val}rem;";
        }
        $leadingNamed = [
            'leading-none' => '1', 'leading-tight' => '1.25', 'leading-snug' => '1.375',
            'leading-normal' => '1.5', 'leading-relaxed' => '1.625', 'leading-loose' => '2',
        ];
        if (isset($leadingNamed[$c])) return "line-height: {$leadingNamed[$c]};";

        return null;
    }

    // ─── Colors ───────────────────────────────────────────
    private function tryColor(string $c): ?string
    {
        $prefixes = [
            'text' => 'color', 'bg' => 'background-color',
            'border' => 'border-color', 'ring' => '--tw-ring-color',
            'outline' => 'outline-color', 'accent' => 'accent-color',
            'decoration' => 'text-decoration-color', 'divide' => 'border-color',
            'placeholder' => 'color',
        ];

        foreach ($prefixes as $prefix => $prop) {
            if (!str_starts_with($c, "$prefix-")) continue;
            $rest = substr($c, strlen($prefix) + 1);

            // text-white, bg-black, bg-transparent, etc.
            $kw = ColorPalette::resolve($rest);
            if ($kw !== null) {
                return "$prop: $kw;";
            }

            // text-red-500, bg-blue-200, etc.
            if (preg_match('/^([a-z]+)-(\d+)$/', $rest, $m)) {
                $color = ColorPalette::resolve($m[1], (int) $m[2]);
                if ($color !== null) {
                    $hex = $this->oklchToHexFallback($m[1], (int) $m[2]);
                    if ($hex) {
                        return "$prop: $hex; $prop: $color;";
                    }
                    return "$prop: $color;";
                }
            }

            // Opacity modifier: text-red-500/50
            if (preg_match('/^([a-z]+)-(\d+)\/(\d+)$/', $rest, $m)) {
                $color = ColorPalette::resolve($m[1], (int) $m[2]);
                if ($color !== null) {
                    $opacity = (int) $m[3] / 100;
                    $colorWithAlpha = str_replace(')', " / $opacity)", $color);
                    return "$prop: $colorWithAlpha;";
                }
            }
        }

        // bg-opacity, text-opacity
        if (preg_match('/^(bg|text)-opacity-(\d+)$/', $c, $m)) {
            $opacity = (int) $m[2] / 100;
            return "opacity: $opacity;";
        }

        return null;
    }

    /**
     * Provide hex fallback for common colors.
     */
    private function oklchToHexFallback(string $name, int $shade): ?string
    {
        $fallbacks = [
            'slate' => [50=>'#f8fafc',100=>'#f1f5f9',200=>'#e2e8f0',300=>'#cbd5e1',400=>'#94a3b8',500=>'#64748b',600=>'#475569',700=>'#334155',800=>'#1e293b',900=>'#0f172a',950=>'#020617'],
            'gray' => [50=>'#f9fafb',100=>'#f3f4f6',200=>'#e5e7eb',300=>'#d1d5db',400=>'#9ca3af',500=>'#6b7280',600=>'#4b5563',700=>'#374151',800=>'#1f2937',900=>'#111827',950=>'#030712'],
            'zinc' => [50=>'#fafafa',100=>'#f4f4f5',200=>'#e4e4e7',300=>'#d4d4d8',400=>'#a1a1aa',500=>'#71717a',600=>'#52525b',700=>'#3f3f46',800=>'#27272a',900=>'#18181b',950=>'#09090b'],
            'neutral' => [50=>'#fafafa',100=>'#f5f5f5',200=>'#e5e5e5',300=>'#d4d4d4',400=>'#a3a3a3',500=>'#737373',600=>'#525252',700=>'#404040',800=>'#262626',900=>'#171717',950=>'#0a0a0a'],
            'red' => [50=>'#fef2f2',100=>'#fee2e2',200=>'#fecaca',300=>'#fca5a5',400=>'#f87171',500=>'#ef4444',600=>'#dc2626',700=>'#b91c1c',800=>'#991b1b',900=>'#7f1d1d',950=>'#450a0a'],
            'orange' => [50=>'#fff7ed',100=>'#ffedd5',200=>'#fed7aa',300=>'#fdba74',400=>'#fb923c',500=>'#f97316',600=>'#ea580c',700=>'#c2410c',800=>'#9a3412',900=>'#7c2d12',950=>'#431407'],
            'amber' => [50=>'#fffbeb',100=>'#fef3c7',200=>'#fde68a',300=>'#fcd34d',400=>'#fbbf24',500=>'#f59e0b',600=>'#d97706',700=>'#b45309',800=>'#92400e',900=>'#78350f',950=>'#451a03'],
            'yellow' => [50=>'#fefce8',100=>'#fef9c3',200=>'#fef08a',300=>'#fde047',400=>'#facc15',500=>'#eab308',600=>'#ca8a04',700=>'#a16207',800=>'#854d0e',900=>'#713f12',950=>'#422006'],
            'green' => [50=>'#f0fdf4',100=>'#dcfce7',200=>'#bbf7d0',300=>'#86efac',400=>'#4ade80',500=>'#22c55e',600=>'#16a34a',700=>'#15803d',800=>'#166534',900=>'#14532d',950=>'#052e16'],
            'blue' => [50=>'#eff6ff',100=>'#dbeafe',200=>'#bfdbfe',300=>'#93c5fd',400=>'#60a5fa',500=>'#3b82f6',600=>'#2563eb',700=>'#1d4ed8',800=>'#1e40af',900=>'#1e3a8a',950=>'#172554'],
            'indigo' => [50=>'#eef2ff',100=>'#e0e7ff',200=>'#c7d2fe',300=>'#a5b4fc',400=>'#818cf8',500=>'#6366f1',600=>'#4f46e5',700=>'#4338ca',800=>'#3730a3',900=>'#312e81',950=>'#1e1b4b'],
            'violet' => [50=>'#f5f3ff',100=>'#ede9fe',200=>'#ddd6fe',300=>'#c4b5fd',400=>'#a78bfa',500=>'#8b5cf6',600=>'#7c3aed',700=>'#6d28d9',800=>'#5b21b6',900=>'#4c1d95',950=>'#2e1065'],
            'purple' => [50=>'#faf5ff',100=>'#f3e8ff',200=>'#e9d5ff',300=>'#d8b4fe',400=>'#c084fc',500=>'#a855f7',600=>'#9333ea',700=>'#7e22ce',800=>'#6b21a8',900=>'#581c87',950=>'#3b0764'],
            'pink' => [50=>'#fdf2f8',100=>'#fce7f3',200=>'#fbcfe8',300=>'#f9a8d4',400=>'#f472b6',500=>'#ec4899',600=>'#db2777',700=>'#be185d',800=>'#9d174d',900=>'#831843',950=>'#500724'],
            'rose' => [50=>'#fff1f2',100=>'#ffe4e6',200=>'#fecdd3',300=>'#fda4af',400=>'#fb7185',500=>'#f43f5e',600=>'#e11d48',700=>'#be123c',800=>'#9f1239',900=>'#881337',950=>'#4c0519'],
            'emerald' => [50=>'#ecfdf5',100=>'#d1fae5',200=>'#a7f3d0',300=>'#6ee7b7',400=>'#34d399',500=>'#10b981',600=>'#059669',700=>'#047857',800=>'#065f46',900=>'#064e3b',950=>'#022c22'],
            'teal' => [50=>'#f0fdfa',100=>'#ccfbf1',200=>'#99f6e4',300=>'#5eead4',400=>'#2dd4bf',500=>'#14b8a6',600=>'#0d9488',700=>'#0f766e',800=>'#115e59',900=>'#134e4a',950=>'#042f2e'],
            'cyan' => [50=>'#ecfeff',100=>'#cffafe',200=>'#a5f3fc',300=>'#67e8f9',400=>'#22d3ee',500=>'#06b6d4',600=>'#0891b2',700=>'#0e7490',800=>'#155e75',900=>'#164e63',950=>'#083344'],
            'sky' => [50=>'#f0f9ff',100=>'#e0f2fe',200=>'#bae6fd',300=>'#7dd3fc',400=>'#38bdf8',500=>'#0ea5e9',600=>'#0284c7',700=>'#0369a1',800=>'#075985',900=>'#0c4a6e',950=>'#082f49'],
            'lime' => [50=>'#f7fee7',100=>'#ecfccb',200=>'#d9f99d',300=>'#bef264',400=>'#a3e635',500=>'#84cc16',600=>'#65a30d',700=>'#4d7c0f',800=>'#3f6212',900=>'#365314',950=>'#1a2e05'],
            'fuchsia' => [50=>'#fdf4ff',100=>'#fae8ff',200=>'#f5d0fe',300=>'#f0abfc',400=>'#e879f9',500=>'#d946ef',600=>'#c026d3',700=>'#a21caf',800=>'#86198f',900=>'#701a75',950=>'#4a044e'],
            'stone' => [50=>'#fafaf9',100=>'#f5f5f4',200=>'#e7e5e4',300=>'#d6d3d1',400=>'#a8a29e',500=>'#78716c',600=>'#57534e',700=>'#44403c',800=>'#292524',900=>'#1c1917',950=>'#0c0a09'],
        ];
        return $fallbacks[$name][$shade] ?? null;
    }

    // ─── Sizing ───────────────────────────────────────────
    private function trySizing(string $c, bool $neg): ?string
    {
        $fractions = ['1/2'=>'50%','1/3'=>'33.333333%','2/3'=>'66.666667%','1/4'=>'25%','2/4'=>'50%','3/4'=>'75%','1/5'=>'20%','2/5'=>'40%','3/5'=>'60%','4/5'=>'80%','1/6'=>'16.666667%','5/6'=>'83.333333%','1/12'=>'8.333333%','5/12'=>'41.666667%','7/12'=>'58.333333%','11/12'=>'91.666667%'];

        foreach (['w' => 'width', 'h' => 'height'] as $pre => $prop) {
            if (preg_match('/^' . $pre . '-(.+)$/', $c, $m)) {
                $v = $m[1];
                if ($v === 'full') return "$prop: 100%;";
                if ($v === 'screen') return "$prop: 100" . ($pre === 'w' ? 'vw' : 'vh') . ";";
                if ($v === 'auto') return "$prop: auto;";
                if ($v === 'min') return "$prop: min-content;";
                if ($v === 'max') return "$prop: max-content;";
                if ($v === 'fit') return "$prop: fit-content;";
                if (isset($fractions[$v])) return "$prop: {$fractions[$v]};";
                if (isset(self::$spacingScale[$v])) {
                    $val = self::$spacingScale[$v];
                    if ($neg) $val = '-' . $val;
                    return "$prop: $val;";
                }
            }
        }

        $maxW = ['max-w-none'=>'none','max-w-xs'=>'20rem','max-w-sm'=>'24rem','max-w-md'=>'28rem','max-w-lg'=>'32rem','max-w-xl'=>'36rem','max-w-2xl'=>'42rem','max-w-3xl'=>'48rem','max-w-4xl'=>'56rem','max-w-5xl'=>'64rem','max-w-6xl'=>'72rem','max-w-7xl'=>'80rem','max-w-full'=>'100%','max-w-prose'=>'65ch','max-w-screen-sm'=>'640px','max-w-screen-md'=>'768px','max-w-screen-lg'=>'1024px','max-w-screen-xl'=>'1280px','max-w-screen-2xl'=>'1536px'];
        if (isset($maxW[$c])) return "max-width: {$maxW[$c]};";

        $minMax = ['min-w-0'=>'min-width: 0px;','min-w-full'=>'min-width: 100%;','min-w-min'=>'min-width: min-content;','min-w-max'=>'min-width: max-content;','min-w-fit'=>'min-width: fit-content;','min-h-0'=>'min-height: 0px;','min-h-full'=>'min-height: 100%;','min-h-screen'=>'min-height: 100vh;','min-h-min'=>'min-height: min-content;','min-h-max'=>'min-height: max-content;','min-h-fit'=>'min-height: fit-content;','max-h-full'=>'max-height: 100%;','max-h-screen'=>'max-height: 100vh;','max-h-min'=>'max-height: min-content;','max-h-max'=>'max-height: max-content;','max-h-fit'=>'max-height: fit-content;'];
        if (isset($minMax[$c])) return $minMax[$c];

        if (preg_match('/^(min-h|max-h|min-w|max-w)-(\d+(?:\.\d+)?)$/', $c, $m)) {
            $prop = str_replace(['min-w','max-w','min-h','max-h'], ['min-width','max-width','min-height','max-height'], $m[1]);
            $val = self::$spacingScale[$m[2]] ?? null;
            if ($val) return "$prop: $val;";
        }

        if (preg_match('/^size-(.+)$/', $c, $m)) {
            $v = $m[1];
            $val = self::$spacingScale[$v] ?? ($v === 'full' ? '100%' : ($v === 'auto' ? 'auto' : null));
            if ($val) return "width: $val; height: $val;";
        }

        return null;
    }

    // ─── Layout ───────────────────────────────────────────
    private function tryLayout(string $c): ?string
    {
        $display = ['block'=>'block','inline-block'=>'inline-block','inline'=>'inline','flex'=>'flex','inline-flex'=>'inline-flex','table'=>'table','inline-table'=>'inline-table','table-caption'=>'table-caption','table-cell'=>'table-cell','table-column'=>'table-column','table-column-group'=>'table-column-group','table-footer-group'=>'table-footer-group','table-header-group'=>'table-header-group','table-row-group'=>'table-row-group','table-row'=>'table-row','flow-root'=>'flow-root','grid'=>'grid','inline-grid'=>'inline-grid','contents'=>'contents','list-item'=>'list-item','hidden'=>'none'];
        if (isset($display[$c])) return "display: {$display[$c]};";

        $flex = [
            'items-start'=>'align-items: flex-start;','items-end'=>'align-items: flex-end;','items-center'=>'align-items: center;','items-baseline'=>'align-items: baseline;','items-stretch'=>'align-items: stretch;',
            'justify-start'=>'justify-content: flex-start;','justify-end'=>'justify-content: flex-end;','justify-center'=>'justify-content: center;','justify-between'=>'justify-content: space-between;','justify-around'=>'justify-content: space-around;','justify-evenly'=>'justify-content: space-evenly;',
            'justify-items-start'=>'justify-items: start;','justify-items-end'=>'justify-items: end;','justify-items-center'=>'justify-items: center;','justify-items-stretch'=>'justify-items: stretch;',
            'self-auto'=>'align-self: auto;','self-start'=>'align-self: flex-start;','self-end'=>'align-self: flex-end;','self-center'=>'align-self: center;','self-stretch'=>'align-self: stretch;','self-baseline'=>'align-self: baseline;',
            'justify-self-auto'=>'justify-self: auto;','justify-self-start'=>'justify-self: start;','justify-self-end'=>'justify-self: end;','justify-self-center'=>'justify-self: center;','justify-self-stretch'=>'justify-self: stretch;',
            'content-start'=>'align-content: flex-start;','content-end'=>'align-content: flex-end;','content-center'=>'align-content: center;','content-between'=>'align-content: space-between;','content-around'=>'align-content: space-around;','content-evenly'=>'align-content: space-evenly;',
            'flex-row'=>'flex-direction: row;','flex-row-reverse'=>'flex-direction: row-reverse;','flex-col'=>'flex-direction: column;','flex-col-reverse'=>'flex-direction: column-reverse;',
            'flex-wrap'=>'flex-wrap: wrap;','flex-wrap-reverse'=>'flex-wrap: wrap-reverse;','flex-nowrap'=>'flex-wrap: nowrap;',
            'flex-1'=>'flex: 1 1 0%;','flex-auto'=>'flex: 1 1 auto;','flex-initial'=>'flex: 0 1 auto;','flex-none'=>'flex: none;',
            'grow'=>'flex-grow: 1;','grow-0'=>'flex-grow: 0;','shrink'=>'flex-shrink: 1;','shrink-0'=>'flex-shrink: 0;',
            'place-content-center'=>'place-content: center;','place-content-start'=>'place-content: start;','place-content-end'=>'place-content: end;','place-content-between'=>'place-content: space-between;','place-content-around'=>'place-content: space-around;','place-content-evenly'=>'place-content: space-evenly;','place-content-stretch'=>'place-content: stretch;',
            'place-items-start'=>'place-items: start;','place-items-end'=>'place-items: end;','place-items-center'=>'place-items: center;','place-items-stretch'=>'place-items: stretch;',
        ];
        if (isset($flex[$c])) return $flex[$c];

        if (preg_match('/^order-(\d+)$/', $c, $m)) return "order: {$m[1]};";
        if ($c === 'order-first') return 'order: -9999;';
        if ($c === 'order-last') return 'order: 9999;';
        if ($c === 'order-none') return 'order: 0;';

        if (preg_match('/^grid-cols-(\d+)$/', $c, $m)) return "grid-template-columns: repeat({$m[1]}, minmax(0, 1fr));";
        if ($c === 'grid-cols-none') return 'grid-template-columns: none;';
        if (preg_match('/^grid-rows-(\d+)$/', $c, $m)) return "grid-template-rows: repeat({$m[1]}, minmax(0, 1fr));";
        if (preg_match('/^col-span-(\d+)$/', $c, $m)) return "grid-column: span {$m[1]} / span {$m[1]};";
        if ($c === 'col-span-full') return 'grid-column: 1 / -1;';
        if (preg_match('/^col-start-(\d+)$/', $c, $m)) return "grid-column-start: {$m[1]};";
        if (preg_match('/^col-end-(\d+)$/', $c, $m)) return "grid-column-end: {$m[1]};";
        if (preg_match('/^row-span-(\d+)$/', $c, $m)) return "grid-row: span {$m[1]} / span {$m[1]};";
        if (preg_match('/^row-start-(\d+)$/', $c, $m)) return "grid-row-start: {$m[1]};";
        if (preg_match('/^row-end-(\d+)$/', $c, $m)) return "grid-row-end: {$m[1]};";
        if ($c === 'grid-flow-row') return 'grid-auto-flow: row;';
        if ($c === 'grid-flow-col') return 'grid-auto-flow: column;';
        if ($c === 'grid-flow-dense') return 'grid-auto-flow: dense;';
        if ($c === 'grid-flow-row-dense') return 'grid-auto-flow: row dense;';
        if ($c === 'grid-flow-col-dense') return 'grid-auto-flow: column dense;';

        $aspect = ['aspect-auto'=>'aspect-ratio: auto;','aspect-square'=>'aspect-ratio: 1 / 1;','aspect-video'=>'aspect-ratio: 16 / 9;'];
        if (isset($aspect[$c])) return $aspect[$c];

        if (preg_match('/^columns-(\d+)$/', $c, $m)) return "columns: {$m[1]};";

        return null;
    }

    // ─── Borders ──────────────────────────────────────────
    private function tryBorder(string $c): ?string
    {
        if ($c === 'border') return 'border-width: 1px;';
        if (preg_match('/^border-(\d+)$/', $c, $m)) return "border-width: {$m[1]}px;";
        $sides = ['t'=>'top','r'=>'right','b'=>'bottom','l'=>'left'];
        foreach ($sides as $s => $full) {
            if ($c === "border-$s") return "border-{$full}-width: 1px;";
            if (preg_match("/^border-$s-(\d+)$/", $c, $m)) return "border-{$full}-width: {$m[1]}px;";
        }
        if ($c === 'border-none') return 'border-style: none;';
        if ($c === 'border-solid') return 'border-style: solid;';
        if ($c === 'border-dashed') return 'border-style: dashed;';
        if ($c === 'border-dotted') return 'border-style: dotted;';
        if ($c === 'border-double') return 'border-style: double;';
        if ($c === 'border-hidden') return 'border-style: hidden;';

        $rounded = ['rounded-none'=>'0px','rounded-sm'=>'0.125rem','rounded'=>'0.25rem','rounded-md'=>'0.375rem','rounded-lg'=>'0.5rem','rounded-xl'=>'0.75rem','rounded-2xl'=>'1rem','rounded-3xl'=>'1.5rem','rounded-full'=>'9999px'];
        if (isset($rounded[$c])) return "border-radius: {$rounded[$c]};";
        foreach (['t'=>['top-left','top-right'],'r'=>['top-right','bottom-right'],'b'=>['bottom-right','bottom-left'],'l'=>['top-left','bottom-left']] as $s => $corners) {
            foreach ($rounded as $name => $val) {
                $suf = str_replace('rounded', '', $name);
                $sideClass = "rounded-$s" . $suf;
                if ($c === $sideClass) return implode('; ', array_map(fn($corner) => "border-$corner-radius: $val", $corners)) . ';';
            }
        }

        if ($c === 'divide-x') return 'border-right-width: 0px; border-left-width: 1px;';
        if ($c === 'divide-y') return 'border-bottom-width: 0px; border-top-width: 1px;';
        if ($c === 'divide-x-reverse') return 'border-right-width: 1px; border-left-width: 0px;';
        if ($c === 'divide-y-reverse') return 'border-bottom-width: 1px; border-top-width: 0px;';
        if (preg_match('/^divide-x-(\d+)$/', $c, $m)) return "border-left-width: {$m[1]}px; border-right-width: 0px;";
        if (preg_match('/^divide-y-(\d+)$/', $c, $m)) return "border-top-width: {$m[1]}px; border-bottom-width: 0px;";

        if ($c === 'ring') return 'box-shadow: 0 0 0 3px rgba(59, 130, 246, 0.5);';
        if (preg_match('/^ring-(\d+)$/', $c, $m)) return "box-shadow: 0 0 0 {$m[1]}px rgba(59, 130, 246, 0.5);";
        if ($c === 'ring-inset') return 'box-shadow: inset 0 0 0 3px rgba(59, 130, 246, 0.5);';
        if (preg_match('/^ring-offset-(\d+)$/', $c, $m)) return "--tw-ring-offset-width: {$m[1]}px;";

        return null;
    }

    // ─── Effects ──────────────────────────────────────────
    private function tryEffect(string $c): ?string
    {
        $shadows = [
            'shadow-sm'=>'0 1px 2px 0 rgb(0 0 0 / 0.05)','shadow'=>'0 1px 3px 0 rgb(0 0 0 / 0.1), 0 1px 2px -1px rgb(0 0 0 / 0.1)','shadow-md'=>'0 4px 6px -1px rgb(0 0 0 / 0.1), 0 2px 4px -2px rgb(0 0 0 / 0.1)','shadow-lg'=>'0 10px 15px -3px rgb(0 0 0 / 0.1), 0 4px 6px -4px rgb(0 0 0 / 0.1)','shadow-xl'=>'0 20px 25px -5px rgb(0 0 0 / 0.1), 0 8px 10px -6px rgb(0 0 0 / 0.1)','shadow-2xl'=>'0 25px 50px -12px rgb(0 0 0 / 0.25)','shadow-inner'=>'inset 0 2px 4px 0 rgb(0 0 0 / 0.05)','shadow-none'=>'0 0 #0000',
        ];
        if (isset($shadows[$c])) return "box-shadow: {$shadows[$c]};";

        if (preg_match('/^opacity-(\d+)$/', $c, $m)) { $v = (int) $m[1] / 100; return "opacity: $v;"; }

        $blend = ['mix-blend-normal'=>'normal','mix-blend-multiply'=>'multiply','mix-blend-screen'=>'screen','mix-blend-overlay'=>'overlay','mix-blend-darken'=>'darken','mix-blend-lighten'=>'lighten','mix-blend-color-dodge'=>'color-dodge','mix-blend-color-burn'=>'color-burn','mix-blend-hard-light'=>'hard-light','mix-blend-soft-light'=>'soft-light','mix-blend-difference'=>'difference','mix-blend-exclusion'=>'exclusion'];
        if (isset($blend[$c])) return "mix-blend-mode: {$blend[$c]};";

        if (preg_match('/^backdrop-blur(?:-(.+))?$/', $c, $m)) {
            $blurs = ['sm'=>'4px',''=>'8px','md'=>'12px','lg'=>'16px','xl'=>'24px','2xl'=>'40px','3xl'=>'64px','none'=>'0'];
            $k = $m[1] ?? '';
            if (isset($blurs[$k])) return "backdrop-filter: blur({$blurs[$k]});";
        }

        if (preg_match('/^blur(?:-(.+))?$/', $c, $m)) {
            $blurs = ['none'=>'0','sm'=>'4px',''=>'8px','md'=>'12px','lg'=>'16px','xl'=>'24px','2xl'=>'40px','3xl'=>'64px'];
            $k = $m[1] ?? '';
            if (isset($blurs[$k])) return "filter: blur({$blurs[$k]});";
        }

        return null;
    }

    // ─── Transitions ─────────────────────────────────────
    private function tryTransition(string $c): ?string
    {
        $transitions = [
            'transition-none' => 'transition-property: none;',
            'transition-all' => 'transition-property: all; transition-timing-function: cubic-bezier(0.4, 0, 0.2, 1); transition-duration: 150ms;',
            'transition' => 'transition-property: color, background-color, border-color, text-decoration-color, fill, stroke, opacity, box-shadow, transform, filter, backdrop-filter; transition-timing-function: cubic-bezier(0.4, 0, 0.2, 1); transition-duration: 150ms;',
            'transition-colors' => 'transition-property: color, background-color, border-color, text-decoration-color, fill, stroke; transition-timing-function: cubic-bezier(0.4, 0, 0.2, 1); transition-duration: 150ms;',
            'transition-opacity' => 'transition-property: opacity; transition-timing-function: cubic-bezier(0.4, 0, 0.2, 1); transition-duration: 150ms;',
            'transition-shadow' => 'transition-property: box-shadow; transition-timing-function: cubic-bezier(0.4, 0, 0.2, 1); transition-duration: 150ms;',
            'transition-transform' => 'transition-property: transform; transition-timing-function: cubic-bezier(0.4, 0, 0.2, 1); transition-duration: 150ms;',
        ];
        if (isset($transitions[$c])) return $transitions[$c];

        if (preg_match('/^duration-(\d+)$/', $c, $m)) return "transition-duration: {$m[1]}ms;";
        if (preg_match('/^delay-(\d+)$/', $c, $m)) return "transition-delay: {$m[1]}ms;";

        $easing = ['ease-linear'=>'linear','ease-in'=>'cubic-bezier(0.4, 0, 1, 1)','ease-out'=>'cubic-bezier(0, 0, 0.2, 1)','ease-in-out'=>'cubic-bezier(0.4, 0, 0.2, 1)'];
        if (isset($easing[$c])) return "transition-timing-function: {$easing[$c]};";

        return null;
    }

    // ─── Transforms ──────────────────────────────────────
    private function tryTransform(string $c, bool $neg): ?string
    {
        if ($c === 'transform') return 'transform: translateX(var(--tw-translate-x, 0)) translateY(var(--tw-translate-y, 0)) rotate(var(--tw-rotate, 0)) skewX(var(--tw-skew-x, 0)) skewY(var(--tw-skew-y, 0)) scaleX(var(--tw-scale-x, 1)) scaleY(var(--tw-scale-y, 1));';
        if ($c === 'transform-none') return 'transform: none;';
        if ($c === 'transform-gpu') return 'transform: translate3d(var(--tw-translate-x, 0), var(--tw-translate-y, 0), 0) rotate(var(--tw-rotate, 0)) skewX(var(--tw-skew-x, 0)) skewY(var(--tw-skew-y, 0)) scaleX(var(--tw-scale-x, 1)) scaleY(var(--tw-scale-y, 1));';

        if (preg_match('/^scale-(\d+)$/', $c, $m)) { $v = (int) $m[1] / 100; return "transform: scale($v);"; }
        if (preg_match('/^scale-x-(\d+)$/', $c, $m)) { $v = (int) $m[1] / 100; return "transform: scaleX($v);"; }
        if (preg_match('/^scale-y-(\d+)$/', $c, $m)) { $v = (int) $m[1] / 100; return "transform: scaleY($v);"; }
        if (preg_match('/^rotate-(\d+)$/', $c, $m)) { $v = $neg ? "-{$m[1]}" : $m[1]; return "transform: rotate({$v}deg);"; }
        if (preg_match('/^translate-x-(.+)$/', $c, $m)) {
            $val = self::$spacingScale[$m[1]] ?? null;
            if ($val) { if ($neg) $val = "-$val"; return "transform: translateX($val);"; }
        }
        if (preg_match('/^translate-y-(.+)$/', $c, $m)) {
            $val = self::$spacingScale[$m[1]] ?? null;
            if ($val) { if ($neg) $val = "-$val"; return "transform: translateY($val);"; }
        }
        if (preg_match('/^skew-x-(\d+)$/', $c, $m)) { $v = $neg ? "-{$m[1]}" : $m[1]; return "transform: skewX({$v}deg);"; }
        if (preg_match('/^skew-y-(\d+)$/', $c, $m)) { $v = $neg ? "-{$m[1]}" : $m[1]; return "transform: skewY({$v}deg);"; }

        $origins = ['origin-center'=>'center','origin-top'=>'top','origin-top-right'=>'top right','origin-right'=>'right','origin-bottom-right'=>'bottom right','origin-bottom'=>'bottom','origin-bottom-left'=>'bottom left','origin-left'=>'left','origin-top-left'=>'top left'];
        if (isset($origins[$c])) return "transform-origin: {$origins[$c]};";

        return null;
    }

    // ─── Position ─────────────────────────────────────────
    private function tryPosition(string $c, bool $neg): ?string
    {
        $positions = ['static'=>'static','fixed'=>'fixed','absolute'=>'absolute','relative'=>'relative','sticky'=>'sticky'];
        if (isset($positions[$c])) return "position: {$positions[$c]};";

        foreach (['top','right','bottom','left'] as $dir) {
            if (preg_match('/^' . $dir . '-(.+)$/', $c, $m)) {
                $v = $m[1];
                if ($v === 'auto') return "$dir: auto;";
                if ($v === 'full') return "$dir: 100%;";
                if (isset(self::$spacingScale[$v])) {
                    $val = self::$spacingScale[$v];
                    if ($neg) $val = "-$val";
                    return "$dir: $val;";
                }
                $fracs = ['1/2'=>'50%','1/3'=>'33.333333%','2/3'=>'66.666667%','1/4'=>'25%','3/4'=>'75%'];
                if (isset($fracs[$v])) return "$dir: {$fracs[$v]};";
            }
        }

        if (preg_match('/^inset-(.+)$/', $c, $m)) {
            $v = $m[1];
            if ($v === 'auto') return 'inset: auto;';
            if (isset(self::$spacingScale[$v])) {
                $val = self::$spacingScale[$v]; if ($neg) $val = "-$val";
                return "inset: $val;";
            }
        }
        if (preg_match('/^inset-x-(.+)$/', $c, $m)) {
            $val = self::$spacingScale[$m[1]] ?? ($m[1] === 'auto' ? 'auto' : null);
            if ($val) { if ($neg && $val !== 'auto') $val = "-$val"; return "left: $val; right: $val;"; }
        }
        if (preg_match('/^inset-y-(.+)$/', $c, $m)) {
            $val = self::$spacingScale[$m[1]] ?? ($m[1] === 'auto' ? 'auto' : null);
            if ($val) { if ($neg && $val !== 'auto') $val = "-$val"; return "top: $val; bottom: $val;"; }
        }

        if (preg_match('/^z-(\d+)$/', $c, $m)) return "z-index: {$m[1]};";
        if ($c === 'z-auto') return 'z-index: auto;';

        return null;
    }

    // ─── Misc ─────────────────────────────────────────────
    private function tryMisc(string $c): ?string
    {
        $map = [
            'overflow-auto'=>'overflow: auto;','overflow-hidden'=>'overflow: hidden;','overflow-clip'=>'overflow: clip;','overflow-visible'=>'overflow: visible;','overflow-scroll'=>'overflow: scroll;',
            'overflow-x-auto'=>'overflow-x: auto;','overflow-x-hidden'=>'overflow-x: hidden;','overflow-x-scroll'=>'overflow-x: scroll;','overflow-x-visible'=>'overflow-x: visible;',
            'overflow-y-auto'=>'overflow-y: auto;','overflow-y-hidden'=>'overflow-y: hidden;','overflow-y-scroll'=>'overflow-y: scroll;','overflow-y-visible'=>'overflow-y: visible;',
            'cursor-auto'=>'cursor: auto;','cursor-default'=>'cursor: default;','cursor-pointer'=>'cursor: pointer;','cursor-wait'=>'cursor: wait;','cursor-text'=>'cursor: text;','cursor-move'=>'cursor: move;','cursor-help'=>'cursor: help;','cursor-not-allowed'=>'cursor: not-allowed;','cursor-none'=>'cursor: none;','cursor-context-menu'=>'cursor: context-menu;','cursor-progress'=>'cursor: progress;','cursor-cell'=>'cursor: cell;','cursor-crosshair'=>'cursor: crosshair;','cursor-vertical-text'=>'cursor: vertical-text;','cursor-alias'=>'cursor: alias;','cursor-copy'=>'cursor: copy;','cursor-no-drop'=>'cursor: no-drop;','cursor-grab'=>'cursor: grab;','cursor-grabbing'=>'cursor: grabbing;','cursor-zoom-in'=>'cursor: zoom-in;','cursor-zoom-out'=>'cursor: zoom-out;','cursor-col-resize'=>'cursor: col-resize;','cursor-row-resize'=>'cursor: row-resize;',
            'select-none'=>'user-select: none;','select-text'=>'user-select: text;','select-all'=>'user-select: all;','select-auto'=>'user-select: auto;',
            'pointer-events-none'=>'pointer-events: none;','pointer-events-auto'=>'pointer-events: auto;',
            'resize-none'=>'resize: none;','resize-y'=>'resize: vertical;','resize-x'=>'resize: horizontal;','resize'=>'resize: both;',
            'appearance-none'=>'appearance: none;','appearance-auto'=>'appearance: auto;',
            'outline-none'=>'outline: 2px solid transparent; outline-offset: 2px;','outline'=>'outline-style: solid;',
            'fill-current'=>'fill: currentColor;','fill-none'=>'fill: none;','stroke-current'=>'stroke: currentColor;','stroke-none'=>'stroke: none;',
            'object-contain'=>'object-fit: contain;','object-cover'=>'object-fit: cover;','object-fill'=>'object-fit: fill;','object-none'=>'object-fit: none;','object-scale-down'=>'object-fit: scale-down;',
            'object-center'=>'object-position: center;','object-top'=>'object-position: top;','object-bottom'=>'object-position: bottom;','object-left'=>'object-position: left;','object-right'=>'object-position: right;',
            'sr-only'=>'position: absolute; width: 1px; height: 1px; padding: 0; margin: -1px; overflow: hidden; clip: rect(0, 0, 0, 0); white-space: nowrap; border-width: 0;',
            'not-sr-only'=>'position: static; width: auto; height: auto; padding: 0; margin: 0; overflow: visible; clip: auto; white-space: normal;',
            'isolate'=>'isolation: isolate;','isolation-auto'=>'isolation: auto;',
            'visible'=>'visibility: visible;','invisible'=>'visibility: hidden;','collapse'=>'visibility: collapse;',
            'list-none'=>'list-style-type: none;','list-disc'=>'list-style-type: disc;','list-decimal'=>'list-style-type: decimal;',
            'list-inside'=>'list-style-position: inside;','list-outside'=>'list-style-position: outside;',
            'float-left'=>'float: left;','float-right'=>'float: right;','float-none'=>'float: none;',
            'clear-left'=>'clear: left;','clear-right'=>'clear: right;','clear-both'=>'clear: both;','clear-none'=>'clear: none;',
            'box-border'=>'box-sizing: border-box;','box-content'=>'box-sizing: content-box;',
            'overscroll-auto'=>'overscroll-behavior: auto;','overscroll-contain'=>'overscroll-behavior: contain;','overscroll-none'=>'overscroll-behavior: none;',
            'scroll-auto'=>'scroll-behavior: auto;','scroll-smooth'=>'scroll-behavior: smooth;',
            'touch-auto'=>'touch-action: auto;','touch-none'=>'touch-action: none;','touch-pan-x'=>'touch-action: pan-x;','touch-pan-y'=>'touch-action: pan-y;','touch-manipulation'=>'touch-action: manipulation;',
            'will-change-auto'=>'will-change: auto;','will-change-scroll'=>'will-change: scroll-position;','will-change-contents'=>'will-change: contents;','will-change-transform'=>'will-change: transform;',
        ];
        if (isset($map[$c])) return $map[$c];

        if (preg_match('/^stroke-(\d+)$/', $c, $m)) return "stroke-width: {$m[1]};";

        return null;
    }

    // ─── Arbitrary Values ─────────────────────────────────
    private function tryArbitrary(string $c): ?string
    {
        // Support w-[200px], bg-[#ff0000], text-[1.25rem], p-[10px], etc.
        if (!preg_match('/^([a-z-]+)-\[(.+)\]$/', $c, $m)) return null;

        $prop = $m[1];
        $val = $m[2];

        $propMap = [
            'w'=>'width','h'=>'height','p'=>'padding','m'=>'margin',
            'pt'=>'padding-top','pr'=>'padding-right','pb'=>'padding-bottom','pl'=>'padding-left',
            'mt'=>'margin-top','mr'=>'margin-right','mb'=>'margin-bottom','ml'=>'margin-left',
            'px'=>null,'py'=>null,'mx'=>null,'my'=>null,
            'top'=>'top','right'=>'right','bottom'=>'bottom','left'=>'left',
            'inset'=>'inset','gap'=>'gap','text'=>'font-size',
            'bg'=>'background-color','border'=>'border-width',
            'rounded'=>'border-radius','max-w'=>'max-width','max-h'=>'max-height',
            'min-w'=>'min-width','min-h'=>'min-height','basis'=>'flex-basis',
            'z'=>'z-index','opacity'=>'opacity','tracking'=>'letter-spacing',
            'leading'=>'line-height','indent'=>'text-indent',
        ];

        if ($prop === 'px') return "padding-left: $val; padding-right: $val;";
        if ($prop === 'py') return "padding-top: $val; padding-bottom: $val;";
        if ($prop === 'mx') return "margin-left: $val; margin-right: $val;";
        if ($prop === 'my') return "margin-top: $val; margin-bottom: $val;";

        $cssProp = $propMap[$prop] ?? null;
        if ($cssProp) return "$cssProp: $val;";

        return null;
    }

    /**
     * Generate CSS for multiple classes at once.
     *
     * @param string[] $classes
     * @return array<string, string> className => cssRules
     */
    public function generateAll(array $classes): array
    {
        $results = [];
        foreach ($classes as $class) {
            $css = $this->generate($class);
            if ($css !== null) {
                $results[$class] = $css;
            }
        }
        return $results;
    }

    /**
     * Generate the dark mode counterpart for a color utility.
     */
    public function getAutoDarkCounterpart(string $c): ?string
    {
        $prefixes = [
            'text' => 'color', 'bg' => 'background-color',
            'border' => 'border-color', 'ring' => '--tw-ring-color',
            'outline' => 'outline-color', 'accent' => 'accent-color',
            'decoration' => 'text-decoration-color', 'divide' => 'border-color',
            'placeholder' => 'color',
        ];

        foreach ($prefixes as $prefix => $prop) {
            if (!str_starts_with($c, "$prefix-")) continue;
            $rest = substr($c, strlen($prefix) + 1);

            // Handle keywords: white <-> black
            if ($rest === 'white') {
                $black = ColorPalette::resolve('black');
                return "$prop: $black;";
            }
            if ($rest === 'black') {
                $white = ColorPalette::resolve('white');
                return "$prop: $white;";
            }

            // Handle shades: invert 100 <-> 900, etc.
            if (preg_match('/^([a-z]+)-(\d+)$/', $rest, $m)) {
                $name = $m[1];
                $shade = (int) $m[2];
                $invertedShade = $this->invertShade($shade);

                $color = ColorPalette::resolve($name, $invertedShade);
                if ($color !== null) {
                    $hex = $this->oklchToHexFallback($name, $invertedShade);
                    if ($hex) {
                        return "$prop: $hex; $prop: $color;";
                    }
                    return "$prop: $color;";
                }
            }
        }

        return null;
    }

    /**
     * Invert a color shade for dark mode.
     */
    private function invertShade(int $shade): int
    {
        $map = [
            50 => 950, 100 => 900, 200 => 800, 300 => 700, 400 => 600,
            500 => 500,
            600 => 400, 700 => 300, 800 => 200, 900 => 100, 950 => 50
        ];
        return $map[$shade] ?? $shade;
    }
}
