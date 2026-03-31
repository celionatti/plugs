<?php

declare(strict_types=1);

namespace Plugs\Console\Support;

/*
|--------------------------------------------------------------------------
| Output Class — Premium CLI Rendering Engine
|--------------------------------------------------------------------------
| Provides a rich, distinctive design language for the Plugs Framework CLI.
| Features 256-color support, vertical rhythm, and premium components.
*/

class Output
{
    // ── Reset ──
    public const RESET = "\033[0m";

    // ── Standard Colors (16-color fallback) ──
    public const BLACK = "\033[30m";
    public const RED = "\033[31m";
    public const GREEN = "\033[32m";
    public const YELLOW = "\033[33m";
    public const BLUE = "\033[34m";
    public const MAGENTA = "\033[35m";
    public const CYAN = "\033[36m";
    public const WHITE = "\033[37m";

    // ── Bright Colors ──
    public const BRIGHT_BLACK = "\033[90m";
    public const BRIGHT_RED = "\033[91m";
    public const BRIGHT_GREEN = "\033[92m";
    public const BRIGHT_YELLOW = "\033[93m";
    public const BRIGHT_BLUE = "\033[94m";
    public const BRIGHT_MAGENTA = "\033[95m";
    public const BRIGHT_CYAN = "\033[96m";
    public const BRIGHT_WHITE = "\033[97m";

    // ── Background Colors ──
    public const BG_RED = "\033[41m";
    public const BG_GREEN = "\033[42m";
    public const BG_YELLOW = "\033[43m";
    public const BG_BLUE = "\033[44m";
    public const BG_MAGENTA = "\033[45m";
    public const BG_CYAN = "\033[46m";

    // ── Text Styles ──
    public const BOLD = "\033[1m";
    public const DIM = "\033[2m";
    public const ITALIC = "\033[3m";
    public const UNDERLINE = "\033[4m";
    public const BLINK = "\033[5m";
    public const REVERSE = "\033[7m";
    public const STRIKETHROUGH = "\033[9m";

    // ── 256-Color Accent Palette ──
    public const ACCENT = "\033[38;5;99m";       // Purple-blue
    public const ACCENT2 = "\033[38;5;73m";      // Teal
    public const ACCENT3 = "\033[38;5;214m";     // Warm amber
    public const MUTED = "\033[38;5;243m";       // Gray-silver
    public const SURFACE = "\033[38;5;238m";     // Dark gray
    public const SUBTLE = "\033[38;5;245m";      // Light gray
    public const EMBER = "\033[38;5;203m";       // Soft red
    public const MINT = "\033[38;5;114m";        // Soft green
    public const SKY = "\033[38;5;111m";         // Soft blue
    public const GOLD = "\033[38;5;220m";        // Gold

    // ── Legacy Gradient (kept for backward compat) ──
    public const GRADIENT_PURPLE = "\033[38;5;135m";
    public const GRADIENT_PINK = "\033[38;5;211m";
    public const GRADIENT_ORANGE = "\033[38;5;208m";
    public const GRADIENT_TEAL = "\033[38;5;80m";

    // ── Glyph Constants ──
    private const G_BRAND   = '◈';
    private const G_BULLET  = '●';
    private const G_SUCCESS = '✦';
    private const G_ERROR   = '✖';
    private const G_WARN    = '▲';
    private const G_NOTE    = '◦';
    private const G_INFO    = '●';
    private const G_DEBUG   = '◦';
    private const G_ARROW   = '▸';
    private const G_DOT     = '·';
    private const G_PIPE    = '│';
    private const G_CORNER  = '╰';
    private const G_TOP     = '╭';
    private const G_BOTTOM  = '╰';
    private const G_LINK    = '⟐';

    private int $consoleWidth;

    public function __construct()
    {
        $this->consoleWidth = $this->getConsoleWidth();
    }

    // ========================================================================
    // CORE UTILITIES
    // ========================================================================

    private function getConsoleWidth(): int
    {
        if (getenv('COLUMNS')) {
            return (int) getenv('COLUMNS');
        }

        if (strtoupper(substr(PHP_OS, 0, 3)) === 'WIN') {
            $output = [];
            @exec('mode con', $output);
            foreach ($output as $line) {
                if (preg_match('/Columns:\s*(\d+)/i', $line, $matches)) {
                    return max(80, min((int) $matches[1], 200));
                }
            }
        } else {
            $width = @exec('tput cols 2>/dev/null');
            if ($width && is_numeric($width)) {
                return max(80, min((int) $width, 200));
            }
        }

        return 120;
    }

    public function stripAnsiCodes(string $text): string
    {
        return preg_replace('/\033\[[0-9;]*m/', '', $text);
    }

    private function visibleLen(string $text): int
    {
        return mb_strwidth($this->stripAnsiCodes($text));
    }

    private function pad(string $text, int $width, string $pad = ' ', int $type = STR_PAD_RIGHT): string
    {
        $visible = $this->visibleLen($text);
        $diff = max(0, $width - $visible);
        return match ($type) {
            STR_PAD_LEFT => str_repeat($pad, $diff) . $text,
            STR_PAD_BOTH => str_repeat($pad, (int)($diff / 2)) . $text . str_repeat($pad, $diff - (int)($diff / 2)),
            default => $text . str_repeat($pad, $diff),
        };
    }

    private function parseTags(string $text): string
    {
        $tags = [
            'fg=black' => self::BLACK, 'fg=red' => self::RED, 'fg=green' => self::GREEN,
            'fg=yellow' => self::YELLOW, 'fg=blue' => self::BLUE, 'fg=magenta' => self::MAGENTA,
            'fg=cyan' => self::CYAN, 'fg=white' => self::WHITE, 'fg=gray' => self::BRIGHT_BLACK,
            'options=bold' => self::BOLD, 'options=dim' => self::DIM,
            'options=italic' => self::ITALIC, 'options=underline' => self::UNDERLINE,
        ];

        return preg_replace_callback('/<([^>]+)>(.*?)<\/?>/s', function ($matches) use ($tags) {
            $parts = explode(';', $matches[1]);
            $prefix = '';
            foreach ($parts as $part) {
                if (isset($tags[trim($part)])) {
                    $prefix .= $tags[trim($part)];
                }
            }
            return $prefix . $matches[2] . self::RESET;
        }, $text);
    }

    private function wrapText(string $text, int $maxWidth): array
    {
        $words = explode(' ', $text);
        $lines = [];
        $currentLine = '';

        foreach ($words as $word) {
            $wordLen = mb_strwidth($word);
            $currentLen = $this->visibleLen($currentLine);

            if ($currentLen + $wordLen + 1 <= $maxWidth) {
                $currentLine .= ($currentLine ? ' ' : '') . $word;
            } else {
                if ($currentLine) $lines[] = $currentLine;
                $currentLine = $word;
            }
        }
        if ($currentLine) $lines[] = $currentLine;

        return $lines;
    }

    // ========================================================================
    // BASE OUTPUT
    // ========================================================================

    public function line(string $text = ''): void
    {
        $margin = "  ";
        $contentWidth = $this->consoleWidth - 4;
        $rawLines = explode(PHP_EOL, $text);

        foreach ($rawLines as $rawLine) {
            $parsedLine = $this->parseTags($rawLine);
            $wrappedLines = $this->wrapText($parsedLine, $contentWidth);
            if (empty($wrappedLines)) $wrappedLines = [''];

            foreach ($wrappedLines as $lineContent) {
                echo $margin . $lineContent . PHP_EOL;
            }
        }
    }

    public function raw(string $text): void
    {
        echo $text;
    }

    public function newLine(int $count = 1): void
    {
        for ($i = 0; $i < $count; $i++) {
            $this->line();
        }
    }

    public function clear(): void
    {
        if (strtoupper(substr(PHP_OS, 0, 3)) === 'WIN') {
            system('cls');
        } else {
            system('clear');
        }
    }

    // ========================================================================
    // MESSAGE METHODS — Distinctive Glyph Design
    // ========================================================================

    public function info(string $text): void
    {
        $this->line(self::SKY . self::G_INFO . self::RESET . " " . $text);
    }

    public function success(string $text): void
    {
        $this->line(self::MINT . self::G_SUCCESS . self::RESET . " " . $text);
    }

    public function warning(string $text): void
    {
        $this->line(self::GOLD . self::G_WARN . self::RESET . " " . $text);
    }

    public function error(string $text): void
    {
        $this->line(self::EMBER . self::G_ERROR . self::RESET . " " . $text);
    }

    public function note(string $text): void
    {
        $this->line(self::MUTED . self::G_NOTE . " " . $text . self::RESET);
    }

    public function critical(string $text): void
    {
        $this->line(self::BG_RED . self::BRIGHT_WHITE . self::BOLD . " ERROR " . self::RESET . " " . self::EMBER . $text . self::RESET);
    }

    public function debug(string $text): void
    {
        $this->line(self::MUTED . self::G_DEBUG . " " . $text . self::RESET);
    }

    // ========================================================================
    // BRANDING & HEADERS
    // ========================================================================

    public function brandingLogo(): void
    {
        $logo = [
            ' ██████╗ ██╗     ██╗   ██╗ ██████╗ ███████╗',
            ' ██╔══██╗██║     ██║   ██║██╔════╝ ██╔════╝',
            ' ██████╔╝██║     ██║   ██║██║  ███╗███████╗',
            ' ██╔═══╝ ██║     ██║   ██║██║   ██║╚════██║',
            ' ██║     ███████╗╚██████╔╝╚██████╔╝███████║',
            ' ╚═╝     ╚══════╝ ╚═════╝  ╚═════╝ ╚══════╝',
        ];

        $this->newLine();
        $colors = [self::ACCENT, "\033[38;5;105m", "\033[38;5;111m", self::SKY, self::ACCENT2, "\033[38;5;79m"];
        foreach ($logo as $i => $line) {
            $color = $colors[$i % count($colors)];
            $this->line($color . $line . self::RESET);
        }
    }

    public function branding(string $version = '1.0.0'): void
    {
        $this->brandingLogo();
        $this->line(self::MUTED . "  Framework " . self::SUBTLE . "v" . $version . self::RESET);
        $this->newLine();
    }

    public function commandHeader(string $command): void
    {
        $time = date('H:i:s');
        $lineWidth = $this->consoleWidth - 4;

        $prefix = self::ACCENT . self::G_LINK . " plugs" . self::RESET;
        $cmdPart = self::MUTED . " ─── " . self::RESET . self::BOLD . self::BRIGHT_WHITE . $command . self::RESET;
        $timePart = self::MUTED . $time . self::RESET;

        $usedLen = $this->visibleLen($this->stripAnsiCodes($prefix . $cmdPart . $timePart)) + 1;
        $dashCount = max(2, $lineWidth - $usedLen);
        $dashes = self::SURFACE . " " . str_repeat("─", $dashCount) . " " . self::RESET;

        $this->newLine();
        $this->line($prefix . $cmdPart . $dashes . $timePart);
        $this->line(self::SURFACE . self::G_PIPE . self::RESET);
    }

    public function commandFooter(float $time, int $memory, int $filesCreated = 0): void
    {
        $timeFormatted = $this->formatTime($time);
        $memoryFormatted = $this->formatBytes($memory);

        $parts = [self::MINT . self::G_SUCCESS . " Done" . self::RESET];
        $parts[] = self::MUTED . "in " . self::BRIGHT_WHITE . $timeFormatted . self::RESET;
        if ($filesCreated > 0) {
            $parts[] = self::MUTED . self::G_DOT . " " . self::BRIGHT_WHITE . $filesCreated . self::MUTED . " files" . self::RESET;
        }
        $parts[] = self::MUTED . self::G_DOT . " " . $memoryFormatted . self::RESET;

        $this->line(self::SURFACE . self::G_CORNER . "─ " . self::RESET . implode(" ", $parts));
        $this->newLine();
    }

    public function header(string $text): void
    {
        $width = $this->consoleWidth - 4;
        $textPadded = "  " . $text . "  ";
        $padding = str_repeat(" ", (int)(($width - mb_strwidth($textPadded)) / 2));

        $this->line();
        $this->line(self::ACCENT . str_repeat(" ", $width) . self::RESET);
        $this->line(self::ACCENT . $padding . self::BRIGHT_WHITE . self::BOLD . $textPadded . self::ACCENT . $padding . self::RESET);
        $this->line(self::ACCENT . str_repeat(" ", $width) . self::RESET);
        $this->line();
    }

    public function section(string $title): void
    {
        $this->newLine();
        $this->line(self::SURFACE . self::G_PIPE . self::RESET);
        $this->line(self::ACCENT . self::G_BRAND . self::RESET . " " . self::BOLD . self::BRIGHT_WHITE . $title . self::RESET);
        $this->line(self::SURFACE . self::G_PIPE . self::RESET);
    }

    public function sectionTitle(string $text): void
    {
        $this->line();
        $this->line(self::BRIGHT_WHITE . self::BOLD . $text . self::RESET);
        $this->fullWidthLine();
        $this->line();
    }

    public function title(string $text): void
    {
        $maxWidth = $this->consoleWidth - 4;
        $textLen = mb_strwidth($text);
        $leftPad = (int)(($maxWidth - $textLen - 2) / 2);
        $rightPad = $maxWidth - $textLen - $leftPad - 2;

        $this->line();
        $this->line(self::ACCENT . self::G_TOP . str_repeat("─", $maxWidth) . "╮" . self::RESET);
        $this->line(
            self::ACCENT . self::G_PIPE . self::RESET .
            str_repeat(" ", $leftPad) .
            self::BRIGHT_WHITE . self::BOLD . $text . self::RESET .
            str_repeat(" ", $rightPad) .
            self::ACCENT . self::G_PIPE . self::RESET
        );
        $this->line(self::ACCENT . self::G_BOTTOM . str_repeat("─", $maxWidth) . "╯" . self::RESET);
        $this->line();
    }

    public function advancedHeader(string $title, string $subtitle = ''): void
    {
        $this->brandingLogo();
        $maxWidth = $this->consoleWidth - 4;

        $this->line(self::ACCENT . self::G_TOP . str_repeat("─", $maxWidth) . "╮" . self::RESET);

        $paddedTitle = " " . strtoupper($title) . " ";
        $titleLen = mb_strwidth($paddedTitle);
        $leftPad = (int)(($maxWidth - $titleLen) / 2);
        $rightPad = $maxWidth - $titleLen - $leftPad;

        echo "  " . self::ACCENT . self::G_PIPE . self::RESET . str_repeat(" ", $leftPad);
        $this->gradientRaw($paddedTitle);
        echo str_repeat(" ", $rightPad) . self::ACCENT . self::G_PIPE . self::RESET . "\n";

        if ($subtitle) {
            $subLen = mb_strwidth($subtitle);
            $leftSub = (int)(($maxWidth - $subLen) / 2);
            $rightSub = $maxWidth - $subLen - $leftSub;
            $this->line(
                self::ACCENT . self::G_PIPE . self::RESET .
                str_repeat(" ", $leftSub) .
                self::MUTED . $subtitle . self::RESET .
                str_repeat(" ", $rightSub) .
                self::ACCENT . self::G_PIPE . self::RESET
            );
        }

        $this->line(self::ACCENT . self::G_BOTTOM . str_repeat("─", $maxWidth) . "╯" . self::RESET);
        $this->newLine();
    }

    public function banner(string $text): void
    {
        $maxWidth = $this->consoleWidth - 4;
        $totalPadding = max(0, $maxWidth - mb_strwidth($text));
        $leftPad = (int)($totalPadding / 2);
        $rightPad = $totalPadding - $leftPad;

        $this->line();
        $this->gradient(str_repeat("▀", $maxWidth));
        $centered = str_repeat(" ", $leftPad) . $text . str_repeat(" ", $rightPad);
        $this->line(self::BOLD . self::BRIGHT_WHITE . $centered . self::RESET);
        $this->gradient(str_repeat("▄", $maxWidth));
        $this->line();
    }

    public function gradient(string $text): void
    {
        $colors = [self::ACCENT, "\033[38;5;105m", "\033[38;5;111m", self::SKY, self::ACCENT2, "\033[38;5;79m", self::MINT];
        $chars = mb_str_split($text);
        $len = count($chars);
        $colorCount = count($colors);

        for ($i = 0; $i < $len; $i++) {
            $colorIndex = (int)(($i / max(1, $len - 1)) * ($colorCount - 1));
            echo $colors[$colorIndex] . $chars[$i];
        }
        echo self::RESET . "\n";
    }

    private function gradientRaw(string $text): void
    {
        $colors = [self::ACCENT, "\033[38;5;105m", "\033[38;5;111m", self::SKY, self::ACCENT2, "\033[38;5;79m", self::MINT];
        $chars = mb_str_split($text);
        $len = count($chars);
        $colorCount = count($colors);

        for ($i = 0; $i < $len; $i++) {
            $colorIndex = (int)(($i / max(1, $len - 1)) * ($colorCount - 1));
            echo $colors[$colorIndex] . $chars[$i];
        }
        echo self::RESET;
    }

    private function getGradientString(string $text): string
    {
        $colors = [self::ACCENT, "\033[38;5;105m", "\033[38;5;111m", self::SKY, self::ACCENT2, "\033[38;5;79m", self::MINT];
        $chars = mb_str_split($text);
        $len = count($chars);
        $colorCount = count($colors);
        $result = '';

        for ($i = 0; $i < $len; $i++) {
            $colorIndex = (int)(($i / max(1, $len - 1)) * ($colorCount - 1));
            $result .= $colors[$colorIndex] . $chars[$i];
        }

        return $result . self::RESET;
    }

    public function fullWidthLine(string $char = '─', string $color = ''): void
    {
        $c = $color ?: self::SURFACE;
        $contentWidth = $this->consoleWidth - 4;
        $this->line($c . str_repeat($char, $contentWidth) . self::RESET);
    }

    public function divider(string $char = '─', string $color = ''): void
    {
        $this->fullWidthLine($char, $color ?: self::SURFACE);
    }

    // ========================================================================
    // BADGE & INLINE COMPONENTS
    // ========================================================================

    public function badge(string $text, string $type = 'info'): string
    {
        $colors = [
            'info' => self::SKY,
            'success' => self::MINT,
            'warning' => self::GOLD,
            'error' => self::EMBER,
            'muted' => self::MUTED,
            'accent' => self::ACCENT,
        ];
        $color = $colors[$type] ?? $colors['info'];
        return $color . self::BOLD . " " . strtoupper($text) . " " . self::RESET;
    }

    public function inlineBadge(string $text, string $type = 'info'): void
    {
        $this->line($this->badge($text, $type));
    }

    // ========================================================================
    // FILE OPERATION RESULTS
    // ========================================================================

    public function fileResult(string $action, string $path): void
    {
        $icons = [
            'created' => [self::MINT, '+'],
            'modified' => [self::GOLD, '~'],
            'deleted' => [self::EMBER, '-'],
            'skipped' => [self::MUTED, '○'],
            'exists' => [self::MUTED, '='],
        ];

        [$color, $icon] = $icons[$action] ?? $icons['created'];
        $relativePath = str_replace([getcwd() . '/', getcwd() . '\\'], '', $path);
        $this->line($color . "  {$icon} " . self::RESET . self::SUBTLE . $relativePath . self::RESET);
    }

    // ========================================================================
    // RESULT SUMMARY
    // ========================================================================

    public function resultSummary(array $stats, float $time = 0, int $memory = 0): void
    {
        $parts = [];
        foreach ($stats as $label => $value) {
            $parts[] = self::BRIGHT_WHITE . $value . self::MUTED . " " . $label;
        }

        if ($time > 0) {
            $parts[] = self::MUTED . "in " . self::BRIGHT_WHITE . $this->formatTime($time);
        }
        if ($memory > 0) {
            $parts[] = self::MUTED . $this->formatBytes($memory);
        }

        $joined = implode(self::MUTED . " " . self::G_DOT . " " . self::RESET, $parts);
        $this->line(self::SURFACE . self::G_CORNER . "─ " . self::MINT . self::G_SUCCESS . self::RESET . " " . $joined . self::RESET);
        $this->newLine();
    }

    // ========================================================================
    // TIMELINE / STEP PROGRESSION
    // ========================================================================

    public function timeline(int $current, int $total, string $message): void
    {
        $isDone = $current > $total;
        $isCurrent = !$isDone;
        $color = $isCurrent ? self::ACCENT : self::MINT;
        $glyph = $isCurrent ? self::G_BRAND : self::G_SUCCESS;
        $counter = self::MUTED . "[{$current}/{$total}]" . self::RESET;

        $this->line("{$color}{$glyph}" . self::RESET . " {$counter} " . self::BRIGHT_WHITE . $message . self::RESET);
    }

    public function step(int $current, int $total, string $message): void
    {
        $this->timeline($current, $total, $message);
    }

    // ========================================================================
    // TABLES — Modern Bordered Design
    // ========================================================================

    public function table(array $headers, array $rows): void
    {
        if (empty($headers) || empty($rows)) {
            $this->warning("No data to display in table");
            return;
        }

        $cols = count($headers);
        $widths = [];
        foreach ($headers as $i => $header) {
            $widths[$i] = $this->visibleLen((string) $header) + 2;
        }
        foreach ($rows as $row) {
            for ($i = 0; $i < $cols; $i++) {
                $cell = (string)($row[$i] ?? '');
                $widths[$i] = max($widths[$i], $this->visibleLen($cell) + 2);
            }
        }

        // Cap column widths
        $maxCol = (int)(($this->consoleWidth - 4 - ($cols + 1)) / $cols);
        foreach ($widths as $i => $w) {
            $widths[$i] = min($w, $maxCol);
        }

        $bc = self::SURFACE;

        // Top border
        $top = $bc . self::G_TOP;
        foreach ($widths as $i => $w) {
            $top .= str_repeat("─", $w);
            $top .= ($i < $cols - 1) ? "┬" : "";
        }
        $top .= "╮" . self::RESET;
        $this->line($top);

        // Header row
        $headerLine = $bc . self::G_PIPE . self::RESET;
        foreach ($headers as $i => $header) {
            $headerLine .= " " . self::BOLD . self::BRIGHT_WHITE . $this->pad((string) $header, $widths[$i] - 1) . self::RESET . $bc . self::G_PIPE . self::RESET;
        }
        $this->line($headerLine);

        // Header separator
        $sep = $bc . "├";
        foreach ($widths as $i => $w) {
            $sep .= str_repeat("─", $w);
            $sep .= ($i < $cols - 1) ? "┼" : "";
        }
        $sep .= "┤" . self::RESET;
        $this->line($sep);

        // Data rows
        foreach ($rows as $ri => $row) {
            $dim = ($ri % 2 === 1) ? self::DIM : '';
            $rowLine = $bc . self::G_PIPE . self::RESET;
            for ($i = 0; $i < $cols; $i++) {
                $cell = (string)($row[$i] ?? '');
                $cellClean = $this->stripAnsiCodes($cell);
                $cellWidth = $widths[$i] - 1;

                if (mb_strwidth($cellClean) > $cellWidth) {
                    $cell = mb_substr($cellClean, 0, $cellWidth - 1) . "…";
                }

                $rowLine .= " " . $dim . $this->pad($cell, $cellWidth) . self::RESET . $bc . self::G_PIPE . self::RESET;
            }
            $this->line($rowLine);
        }

        // Bottom border
        $bottom = $bc . self::G_BOTTOM;
        foreach ($widths as $i => $w) {
            $bottom .= str_repeat("─", $w);
            $bottom .= ($i < $cols - 1) ? "┴" : "";
        }
        $bottom .= "╯" . self::RESET;
        $this->line($bottom);
        $this->line();
    }

    // ========================================================================
    // BOX / PANEL / STATUS CARD
    // ========================================================================

    public function box(string $content, string $title = '', string $type = 'info'): void
    {
        $colors = [
            'info' => self::SKY, 'success' => self::MINT,
            'warning' => self::GOLD, 'error' => self::EMBER,
            'default' => self::MUTED,
        ];
        $color = $colors[$type] ?? $colors['default'];

        $this->line();
        if ($title) {
            $this->line("  " . $color . self::BOLD . $title . self::RESET);
        }

        $lines = explode("\n", $content);
        foreach ($lines as $l) {
            $this->line("  " . $color . "▐" . self::RESET . " " . $l);
        }
        $this->line();
    }

    public function panel(string $content, string $title = ''): void
    {
        $this->newLine();
        if ($title) {
            $this->line("  " . self::ACCENT . self::G_PIPE . " " . self::BOLD . $title . self::RESET);
        }
        $lines = explode("\n", $content);
        foreach ($lines as $l) {
            $this->line("  " . self::ACCENT . self::G_PIPE . " " . self::RESET . $l);
        }
        $this->newLine();
    }

    public function statusCard(string $title, array $stats, string $type = 'info'): void
    {
        $colors = [
            'info' => self::SKY, 'success' => self::MINT,
            'warning' => self::GOLD, 'error' => self::EMBER,
        ];
        $color = $colors[$type] ?? self::MUTED;
        $maxWidth = $this->consoleWidth - 4;

        $this->line(self::SURFACE . self::G_TOP . str_repeat("─", $maxWidth) . "╮" . self::RESET);

        $titleLine = $color . self::BOLD . " " . strtoupper($title) . self::RESET;
        $titleLen = $this->visibleLen($this->stripAnsiCodes($titleLine)) + 1;
        $this->line(
            self::SURFACE . self::G_PIPE . self::RESET . $titleLine .
            str_repeat(" ", max(0, $maxWidth - $titleLen)) .
            self::SURFACE . self::G_PIPE . self::RESET
        );

        $this->line(self::SURFACE . "├" . str_repeat("─", $maxWidth) . "┤" . self::RESET);

        foreach ($stats as $key => $value) {
            $kvLine = "  " . self::SUBTLE . $key . self::RESET . self::MUTED . ": " . self::RESET . $value;
            $kvLen = $this->visibleLen($kvLine);
            $this->line(
                self::SURFACE . self::G_PIPE . self::RESET . $kvLine .
                str_repeat(" ", max(0, $maxWidth - $kvLen)) .
                self::SURFACE . self::G_PIPE . self::RESET
            );
        }

        $this->line(self::SURFACE . self::G_BOTTOM . str_repeat("─", $maxWidth) . "╯" . self::RESET);
    }

    public function errorBox(string $message, string $title = 'Error'): void
    {
        $this->box($message, $title, 'error');
    }

    // ========================================================================
    // LISTS
    // ========================================================================

    public function bulletList(array $items, string $bullet = ''): void
    {
        $b = $bullet ?: self::G_BULLET;
        foreach ($items as $item) {
            $this->line("  " . self::ACCENT . $b . self::RESET . " {$item}");
        }
    }

    public function numberedList(array $items): void
    {
        foreach ($items as $index => $item) {
            $n = $index + 1;
            $this->line("  " . self::ACCENT . "{$n}." . self::RESET . " {$item}");
        }
    }

    public function tree(array $items, int $level = 0, bool $isLast = false): void
    {
        $keys = array_keys($items);
        $total = count($keys);

        foreach ($keys as $idx => $key) {
            $value = $items[$key];
            $last = ($idx === $total - 1);
            $indent = str_repeat("  ", $level);
            $branch = $level > 0 ? ($last ? "└── " : "├── ") : "";
            $branchColor = self::SURFACE;

            if (is_array($value)) {
                $this->line($indent . $branchColor . $branch . self::RESET . self::ACCENT . $key . self::RESET);
                $this->tree($value, $level + 1, $last);
            } else {
                $this->line($indent . $branchColor . $branch . self::RESET . $value);
            }
        }
    }

    public function columns(array $items, int $cols = 3): void
    {
        $colWidth = (int)(($this->consoleWidth - 8) / $cols);
        $chunks = array_chunk($items, (int)ceil(count($items) / $cols));

        $maxRows = max(array_map('count', $chunks));

        for ($row = 0; $row < $maxRows; $row++) {
            $line = "";
            for ($col = 0; $col < $cols; $col++) {
                $item = $chunks[$col][$row] ?? '';
                $line .= $this->pad($item, $colWidth);
            }
            $this->line($line);
        }
    }

    public function keyValue(string $key, string $value, int $padding = 20): void
    {
        $key = $this->pad($key, $padding);
        $this->line("  " . self::SUBTLE . $key . self::RESET . self::MUTED . ": " . self::RESET . $value);
    }

    public function twoColumnList(array $items, int $leftWidth = 30): void
    {
        foreach ($items as $left => $right) {
            $visibleLeft = $this->visibleLen($left);
            $padding = max(0, $leftWidth - $visibleLeft);
            $this->line("  " . self::MINT . $left . self::RESET . str_repeat(' ', $padding) . $right);
        }
    }

    public function diff(string $old, string $new): void
    {
        $this->line();
        $this->line(self::EMBER . "  - " . $old . self::RESET);
        $this->line(self::MINT . "  + " . $new . self::RESET);
        $this->line();
    }

    // ========================================================================
    // QUOTES & HIGHLIGHTS
    // ========================================================================

    public function quote(string $text, string $author = ''): void
    {
        $this->line();
        $this->line(self::ITALIC . self::ACCENT2 . "  \"" . $text . "\"" . self::RESET);
        if ($author) {
            $this->line(self::MUTED . "    — " . $author . self::RESET);
        }
        $this->line();
    }

    public function highlight(string $text, array $words, string $color = ''): void
    {
        $c = $color ?: self::GOLD;
        $highlighted = $text;
        foreach ($words as $word) {
            $highlighted = str_replace($word, $c . $word . self::RESET, $highlighted);
        }
        $this->line($highlighted);
    }

    // ========================================================================
    // PROGRESS INDICATORS
    // ========================================================================

    public function loading(string $message, callable $callback): mixed
    {
        $frames = ['⠋', '⠙', '⠹', '⠸', '⠼', '⠴', '⠦', '⠧', '⠇', '⠏'];
        $maxWidth = $this->consoleWidth - 4;
        $startTime = microtime(true);
        $result = null;
        $error = null;
        $i = 0;

        while (true) {
            $elapsed = microtime(true) - $startTime;
            $spinnerText = $frames[$i++ % count($frames)] . " " . $message;
            if ($elapsed > 0.1) {
                $spinnerText .= self::MUTED . sprintf(" (%.1fs)", $elapsed) . self::RESET;
            }

            $textLen = $this->visibleLen($spinnerText);
            $padding = max(0, $maxWidth - $textLen - 4);

            echo "\r  " . self::ACCENT . self::G_PIPE . self::RESET .
                " " . self::SKY . $spinnerText . self::RESET .
                str_repeat(" ", $padding);
            flush();

            try {
                $result = $callback();
                break;
            } catch (\Throwable $e) {
                $error = $e;
                break;
            }

            usleep(100000);
            if ($elapsed > 30) break;
        }

        $totalTime = microtime(true) - $startTime;

        if ($error) {
            $errText = self::G_ERROR . " " . $message . " – Failed!";
            echo "\r  " . self::ACCENT . self::G_PIPE . self::RESET .
                " " . self::EMBER . $errText . self::RESET .
                str_repeat(" ", max(0, $maxWidth - $this->visibleLen($errText) - 4)) . "\n";
            throw $error;
        }

        $successText = self::G_SUCCESS . " " . $message . sprintf(" (%.2fs)", $totalTime);
        echo "\r  " . self::ACCENT . self::G_PIPE . self::RESET .
            " " . self::MINT . $successText . self::RESET .
            str_repeat(" ", max(0, $maxWidth - $this->visibleLen($successText) - 4)) . "\n";

        return $result;
    }

    public function spinner(string $message, int|callable $secondsOrCallback = 2): void
    {
        $frames = ['⠋', '⠙', '⠹', '⠸', '⠼', '⠴', '⠦', '⠧', '⠇', '⠏'];
        $maxWidth = $this->consoleWidth - 4;
        $i = 0;

        if (is_callable($secondsOrCallback)) {
            while (!$secondsOrCallback()) {
                $text = $frames[$i++ % count($frames)] . " " . $message;
                $textLen = $this->visibleLen($text);
                echo "\r  " . self::ACCENT . self::G_PIPE . self::RESET .
                    " " . self::SKY . $text . self::RESET .
                    str_repeat(" ", max(0, $maxWidth - $textLen - 4));
                flush();
                usleep(120000);
                if ($i > 500) break;
            }
        } else {
            $end = time() + $secondsOrCallback;
            while (time() < $end) {
                $text = $frames[$i++ % count($frames)] . " " . $message;
                $textLen = $this->visibleLen($text);
                echo "\r  " . self::ACCENT . self::G_PIPE . self::RESET .
                    " " . self::SKY . $text . self::RESET .
                    str_repeat(" ", max(0, $maxWidth - $textLen - 4));
                flush();
                usleep(120000);
            }
        }

        $successText = self::G_SUCCESS . " " . $message;
        echo "\r  " . self::ACCENT . self::G_PIPE . self::RESET .
            " " . self::MINT . $successText . self::RESET .
            str_repeat(" ", max(0, $maxWidth - $this->visibleLen($successText) - 4)) . "\n";
    }

    public function progressBar(int $max, callable $step, string $label = 'Progress'): void
    {
        $maxWidth = $this->consoleWidth - 4;

        $this->line(self::SURFACE . self::G_PIPE . self::RESET);
        $this->line(self::ACCENT . self::G_BRAND . self::RESET . " " . self::BOLD . $label . self::RESET);
        $this->line(self::SURFACE . self::G_PIPE . self::RESET);

        for ($i = 1; $i <= $max; $i++) {
            $step($i);
            $percent = (int)(($i / $max) * 100);
            $barWidth = $maxWidth - 22;
            $filled = (int)(($percent / 100) * $barWidth);
            $empty = $barWidth - $filled;

            $bar = $this->getGradientString(str_repeat("█", $filled)) . self::SURFACE . str_repeat("░", $empty) . self::RESET;
            $pctText = self::BRIGHT_WHITE . str_pad($percent . "%", 4, " ", STR_PAD_LEFT) . self::RESET;
            $counterText = self::MUTED . "({$i}/{$max})" . self::RESET;

            echo "\r  " . self::ACCENT . self::G_PIPE . self::RESET .
                " " . $bar . " " . $pctText . " " . $counterText .
                str_repeat(" ", 4);
            flush();

            usleep(50000);
        }

        echo "\n";
        $this->line(self::SURFACE . self::G_CORNER . "─ " . self::MINT . self::G_SUCCESS . " Completed" . self::RESET);
        $this->line();
    }

    public function progress(int $current, int $total, string $message = ''): void
    {
        $maxWidth = $this->consoleWidth - 4;
        $percentage = (int)(($current / $total) * 100);
        $barWidth = $maxWidth - 32;
        $filled = (int)($barWidth * ($current / $total));

        $bar = $this->getGradientString(str_repeat('█', $filled)) . self::SURFACE . str_repeat('░', $barWidth - $filled) . self::RESET;
        $pctText = self::BRIGHT_WHITE . sprintf("%3d%%", $percentage) . self::RESET;
        $msgText = $message ? " " . self::MUTED . $message . self::RESET : "";

        echo "\r  " . self::ACCENT . self::G_PIPE . self::RESET .
            " " . $bar . " " . $pctText . $msgText .
            str_repeat(" ", 4);
        flush();

        if ($current === $total) echo PHP_EOL;
    }

    public function gradientProgressBar(int $current, int $total, string $message = ''): void
    {
        $this->progress($current, $total, $message);
    }

    public function taskWithBox(string $message, callable $callback): mixed
    {
        return $this->loading($message, $callback);
    }

    // ========================================================================
    // COUNTDOWN & ALERTS
    // ========================================================================

    public function countdown(int $seconds, string $message = 'Starting in'): void
    {
        $maxWidth = $this->consoleWidth - 4;

        for ($i = $seconds; $i > 0; $i--) {
            $text = $message . " " . self::BOLD . $i . self::RESET . "s...";
            $textLen = $this->visibleLen($this->stripAnsiCodes($text));
            $padding = (int)(($maxWidth - $textLen) / 2);

            echo "\r  " . str_repeat(" ", $padding) .
                self::GOLD . $text . self::RESET .
                str_repeat(" ", max(0, $maxWidth - $textLen - $padding));
            flush();

            sleep(1);
        }

        $goText = "🚀 Let's go!";
        $goLen = mb_strwidth($goText);
        $padding = (int)(($maxWidth - $goLen) / 2);

        echo "\r  " . str_repeat(" ", $padding) .
            self::MINT . $goText . self::RESET .
            str_repeat(" ", max(0, $maxWidth - $goLen - $padding)) . "\n";

        $this->line();
    }

    public function alert(string $message, string $type = 'info'): void
    {
        $icons = [
            'info' => 'ℹ️', 'success' => '✅',
            'warning' => '⚠️', 'error' => '❌', 'question' => '❓',
        ];
        $icon = $icons[$type] ?? $icons['info'];
        $this->box($message, "{$icon} Alert", $type);
    }

    // ========================================================================
    // INTERACTIVE INPUT
    // ========================================================================

    public function ask(string $question, ?string $default = null): string
    {
        $prompt = self::ACCENT . "◆" . self::RESET . " " . $question;
        if ($default !== null) {
            $prompt .= " " . self::MUTED . "({$default})" . self::RESET;
        }
        echo "  " . $prompt . self::MUTED . ": " . self::RESET;
        flush();

        $handle = fopen("php://stdin", "r");
        $input = trim(fgets($handle));
        fclose($handle);

        return $input === '' && $default !== null ? $default : $input;
    }

    public function secret(string $question): string
    {
        echo "  " . self::ACCENT . "🔒" . self::RESET . " " . $question . ": ";
        flush();

        if (strtoupper(substr(PHP_OS, 0, 3)) === 'WIN') {
            $password = '';
            while (true) {
                $char = fgetc(STDIN);
                if ($char === "\n" || $char === "\r") break;
                $password .= $char;
            }
        } else {
            system('stty -echo');
            $handle = fopen("php://stdin", "r");
            $password = trim(fgets($handle));
            fclose($handle);
            system('stty echo');
        }

        echo "\n";
        return $password;
    }

    public function confirm(string $question, bool $default = false): bool
    {
        return $this->askConfirmation($question, $default);
    }

    public function askConfirmation(string $question, bool $default = false): bool
    {
        $suffix = $default ? self::MUTED . '[Y/n]' . self::RESET : self::MUTED . '[y/N]' . self::RESET;
        echo "  " . self::GOLD . "◆" . self::RESET . " " . $question . " " . $suffix . " ";
        flush();

        $handle = fopen("php://stdin", "r");
        $input = strtolower(trim(fgets($handle)));
        fclose($handle);

        if ($input === '') return $default;
        return in_array($input, ['y', 'yes', '1', 'true']);
    }

    public function choice(string $question, array $choices, $default = null): string
    {
        $this->line();
        $this->line(self::ACCENT . "◆" . self::RESET . " " . self::BOLD . $question . self::RESET);
        $this->line();

        $indexedChoices = array_values($choices);

        foreach ($indexedChoices as $index => $choice) {
            $number = $index + 1;
            $isDefault = ($default !== null && $choice === $default);
            $marker = $isDefault ? self::ACCENT . self::G_ARROW : self::MUTED . " ";
            $highlight = $isDefault ? self::BRIGHT_WHITE : self::SUBTLE;
            echo "  {$marker}" . self::RESET . " " . $highlight . "[{$number}] {$choice}" . self::RESET . "\n";
        }

        $this->line();
        $prompt = self::MUTED . "Select" . self::RESET;
        if ($default !== null) {
            $defaultIndex = array_search($default, $indexedChoices);
            $prompt .= " " . self::MUTED . "(" . ($defaultIndex + 1) . ")" . self::RESET;
        }
        echo "  " . $prompt . ": ";
        flush();

        $handle = fopen("php://stdin", "r");
        $input = trim(fgets($handle));
        fclose($handle);

        if ($input === '' && $default !== null) {
            $this->line();
            return $default;
        }

        $selectedIndex = (int) $input - 1;

        if (!isset($indexedChoices[$selectedIndex])) {
            $this->error("Invalid choice. Please try again.");
            return $this->choice($question, $choices, $default);
        }

        $this->line();
        return $indexedChoices[$selectedIndex];
    }

    public function multiChoice(string $question, array $choices, array $defaults = []): array
    {
        $this->line();
        $this->line(self::ACCENT . "◆" . self::RESET . " " . self::BOLD . $question . self::RESET);
        $this->line(self::MUTED . "  Enter numbers separated by commas" . self::RESET);
        $this->line();

        $indexedChoices = array_values($choices);

        foreach ($indexedChoices as $index => $choice) {
            $number = $index + 1;
            $isDefault = in_array($choice, $defaults);
            $marker = $isDefault ? self::MINT . "✓" . self::RESET : " ";
            echo "  [{$marker}] " . self::SUBTLE . "[{$number}]" . self::RESET . " {$choice}\n";
        }

        $this->line();
        echo "  " . self::MUTED . "Select (comma-separated)" . self::RESET . ": ";
        flush();

        $handle = fopen("php://stdin", "r");
        $input = trim(fgets($handle));
        fclose($handle);

        if ($input === '') {
            $this->line();
            return $defaults;
        }

        $selected = [];
        $numbers = array_map('trim', explode(',', $input));
        foreach ($numbers as $number) {
            $index = (int)$number - 1;
            if (isset($indexedChoices[$index])) {
                $selected[] = $indexedChoices[$index];
            }
        }

        $this->line();
        return $selected;
    }

    public function anticipate(string $question, array $suggestions, ?string $default = null): string
    {
        $this->line();
        $this->line(self::ACCENT . "◆" . self::RESET . " " . $question);

        if (!empty($suggestions)) {
            $this->line(self::MUTED . "  Suggestions: " . implode(", ", $suggestions) . self::RESET);
        }

        $prompt = '';
        if ($default !== null) {
            $prompt .= self::MUTED . "({$default})" . self::RESET . " ";
        }
        echo "  " . $prompt . ": ";
        flush();

        $handle = fopen("php://stdin", "r");
        $input = trim(fgets($handle));
        fclose($handle);

        $this->line();
        return $input === '' && $default !== null ? $default : $input;
    }

    // ========================================================================
    // JSON DISPLAY
    // ========================================================================

    public function json(mixed $data): void
    {
        $json = json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);

        $json = preg_replace_callback(
            '/(".*?")(\s*:)|(".*?")|(\b\d+\b)|(true|false|null)/',
            function ($matches) {
                if (!empty($matches[1])) return self::ACCENT2 . $matches[1] . self::RESET . $matches[2];
                if (!empty($matches[3])) return self::MINT . $matches[3] . self::RESET;
                if (!empty($matches[4])) return self::GOLD . $matches[4] . self::RESET;
                if (!empty($matches[5])) return self::ACCENT . $matches[5] . self::RESET;
                return $matches[0];
            },
            $json
        );

        $this->panel($json, 'JSON');
    }

    // ========================================================================
    // SIDE-BY-SIDE / MIGRATION RESULT
    // ========================================================================

    public function sideBySide(string $left, string $right, string $leftTitle = '', string $rightTitle = ''): void
    {
        $halfWidth = (int)(($this->consoleWidth - 10) / 2);

        $leftLines = $this->wrapText($left, $halfWidth);
        $rightLines = $this->wrapText($right, $halfWidth);

        if ($leftTitle || $rightTitle) {
            $lt = self::BOLD . self::BRIGHT_WHITE . $this->pad($leftTitle, $halfWidth) . self::RESET;
            $rt = self::BOLD . self::BRIGHT_WHITE . $this->pad($rightTitle, $halfWidth) . self::RESET;
            $this->line("  " . self::ACCENT . self::G_PIPE . " " . self::RESET . $lt . self::ACCENT . " " . self::G_PIPE . " " . self::RESET . $rt);
            $this->line("  " . self::SURFACE . "├─" . str_repeat("─", $halfWidth) . "┼─" . str_repeat("─", $halfWidth) . "┤" . self::RESET);
        }

        $maxLines = max(count($leftLines), count($rightLines));
        for ($i = 0; $i < $maxLines; $i++) {
            $l = $this->pad($leftLines[$i] ?? '', $halfWidth);
            $r = $this->pad($rightLines[$i] ?? '', $halfWidth);
            $this->line("  " . self::ACCENT . self::G_PIPE . " " . self::RESET . $l . self::ACCENT . " " . self::G_PIPE . " " . self::RESET . $r);
        }
        $this->newLine();
    }

    public function migrationResult(string $migration, string $status, float $time): void
    {
        $statusColor = $status === 'DONE' ? self::MINT : self::EMBER;
        $timeFormatted = $this->formatTime($time);
        $migrationName = str_pad($migration, 60, '.');
        $this->line("  {$migrationName} {$statusColor}{$status}" . self::RESET . " {$timeFormatted}");
    }

    public function infoBlock(string $title, string $content): void
    {
        $this->line(self::SKY . "  {$title}: " . self::RESET . $content);
    }

    public function successBlock(string $title, string $content): void
    {
        $this->line(self::MINT . "  " . self::G_SUCCESS . " {$title}: " . self::RESET . $content);
    }

    // ========================================================================
    // COMMAND TABLE (for help listing)
    // ========================================================================

    public function commandTable(array $commands): void
    {
        $this->line();
        $this->sectionTitle('Available Commands');

        $grouped = [];
        foreach ($commands as $name => $command) {
            $category = 'General';
            if (str_contains($name, ':')) {
                $category = ucfirst(explode(':', $name)[0]);
            }
            $grouped[$category][$name] = $command;
        }

        ksort($grouped);

        foreach ($grouped as $category => $categoryCommands) {
            $this->line(self::BRIGHT_WHITE . self::BOLD . "  " . $category . self::RESET);

            foreach ($categoryCommands as $name => $commandClass) {
                try {
                    $command = new $commandClass($name);
                    $description = $command->description();
                } catch (\Throwable $e) {
                    $description = 'No description';
                }

                $name = str_pad($name, 28);
                $this->line("    " . self::MINT . $name . self::RESET . self::SUBTLE . $description . self::RESET);
            }
            $this->line();
        }
    }

    public function argumentList(array $arguments): void
    {
        if (empty($arguments)) return;
        $this->line(self::BRIGHT_WHITE . self::BOLD . "  Arguments:" . self::RESET);
        foreach ($arguments as $name => $description) {
            $this->line("    " . self::MINT . str_pad($name, 22) . self::RESET . self::SUBTLE . $description . self::RESET);
        }
        $this->line();
    }

    public function optionList(array $options): void
    {
        if (empty($options)) return;
        $this->line(self::BRIGHT_WHITE . self::BOLD . "  Options:" . self::RESET);
        foreach ($options as $name => $description) {
            $this->line("    " . self::MINT . str_pad($name, 28) . self::RESET . self::SUBTLE . $description . self::RESET);
        }
        $this->line();
    }

    // ========================================================================
    // METRICS
    // ========================================================================

    public function metrics(float $time, int $memory): void
    {
        $timeFormatted = $this->formatTime($time);
        $memoryFormatted = $this->formatBytes($memory);

        $this->line();
        $this->divider();
        $this->line("  " . self::MINT . self::G_SUCCESS . self::RESET . " Completed in " . self::BOLD . $timeFormatted . self::RESET);
        $this->line("  " . self::SKY . "◈" . self::RESET . " Memory: " . self::BOLD . $memoryFormatted . self::RESET);
        $this->divider();
        $this->line();
    }

    // ========================================================================
    // FORMATTING HELPERS
    // ========================================================================

    public function formatTime(float $seconds): string
    {
        if ($seconds < 0.001) {
            return number_format($seconds * 1000000, 0) . 'μs';
        } elseif ($seconds < 1) {
            return number_format($seconds * 1000, 0) . 'ms';
        }
        return number_format($seconds, 2) . 's';
    }

    public function formatBytes(int $bytes): string
    {
        $units = ['B', 'KB', 'MB', 'GB'];
        $bytes = max($bytes, 0);
        $pow = floor(($bytes ? log($bytes) : 0) / log(1024));
        $pow = min($pow, count($units) - 1);
        $bytes /= (1 << (10 * $pow));
        return round($bytes, 2) . ' ' . $units[$pow];
    }
}
