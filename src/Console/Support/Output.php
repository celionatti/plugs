<?php

declare(strict_types=1);

namespace Plugs\Console\Support;

/*
|--------------------------------------------------------------------------
| OutPut Class
|--------------------------------------------------------------------------
*/

class Output
{
    // Reset
    private const RESET = "\033[0m";

    // Standard colors
    private const BLACK = "\033[30m";
    private const RED = "\033[31m";
    private const GREEN = "\033[32m";
    private const YELLOW = "\033[33m";
    private const BLUE = "\033[34m";
    private const MAGENTA = "\033[35m";
    private const CYAN = "\033[36m";
    private const WHITE = "\033[37m";

    // Bright colors
    private const BRIGHT_BLACK = "\033[90m";
    private const BRIGHT_RED = "\033[91m";
    private const BRIGHT_GREEN = "\033[92m";
    private const BRIGHT_YELLOW = "\033[93m";
    private const BRIGHT_BLUE = "\033[94m";
    private const BRIGHT_MAGENTA = "\033[95m";
    private const BRIGHT_CYAN = "\033[96m";
    private const BRIGHT_WHITE = "\033[97m";

    // Background colors
    private const BG_RED = "\033[41m";
    private const BG_GREEN = "\033[42m";
    private const BG_YELLOW = "\033[43m";
    private const BG_BLUE = "\033[44m";
    private const BG_MAGENTA = "\033[45m";
    private const BG_CYAN = "\033[46m";

    // Text styles
    private const BOLD = "\033[1m";
    private const DIM = "\033[2m";
    private const ITALIC = "\033[3m";
    private const UNDERLINE = "\033[4m";
    private const BLINK = "\033[5m";
    private const REVERSE = "\033[7m";
    private const STRIKETHROUGH = "\033[9m";

    // Gradient colors
    private const GRADIENT_PURPLE = "\033[38;5;135m";
    private const GRADIENT_PINK = "\033[38;5;211m";
    private const GRADIENT_ORANGE = "\033[38;5;208m";
    private const GRADIENT_TEAL = "\033[38;5;80m";

    private int $consoleWidth;
    private array $logoLines = [];
    private int $logoLineIndex = 0;
    private int $logoWidth = 35; // Fixed width for logo column

    public function __construct()
    {
        $this->consoleWidth = $this->getConsoleWidth();
        $this->logoLines = $this->getPlugLogoLines();
    }

    private function getConsoleWidth(): int
    {
        // Try different methods to get console width

        // Method 1: Environment variable (works on most systems)
        if (getenv('COLUMNS')) {
            return (int) getenv('COLUMNS');
        }

        // Method 2: Try tput (Unix/Linux/Mac)
        if (strtoupper(substr(PHP_OS, 0, 3)) !== 'WIN') {
            $width = @exec('tput cols 2>/dev/null');
            if ($width && is_numeric($width)) {
                return max(80, min((int) $width, 200));
            }
        }

        // Method 3: Try mode con (Windows)
        if (strtoupper(substr(PHP_OS, 0, 3)) === 'WIN') {
            $output = [];
            @exec('mode con', $output);
            foreach ($output as $line) {
                if (preg_match('/Columns:\s*(\d+)/i', $line, $matches)) {
                    return max(80, min((int) $matches[1], 200));
                }
            }
        }

        // Method 4: Default fallback
        return 120; // Safe default width
    }

    public function fullWidthLine(string $char = '─', string $color = self::BRIGHT_BLACK): void
    {
        // Adjust full width line to account for logo column
        $contentWidth = $this->consoleWidth - $this->logoWidth - 2;
        $this->line($color . str_repeat($char, $contentWidth) . self::RESET);
    }

    public function sectionTitle(string $text): void
    {
        $this->line();
        $this->line(self::BRIGHT_WHITE . self::BOLD . $text . self::RESET);
        $this->fullWidthLine();
        $this->line();
    }

    public function commandHeader(string $command): void
    {
        // Minimal Modern Header
        $width = $this->consoleWidth;
        $time = date('H:i:s');

        $this->newLine();
        $this->line(self::BRIGHT_BLUE . "  ➜ " . self::BOLD . self::BRIGHT_WHITE . "Command" . self::RESET . self::DIM . " running " . self::RESET . self::BRIGHT_CYAN . $command . self::RESET);
        $this->line(self::DIM . "    Started at " . $time . self::RESET);
        $this->newLine();
    }

    public function migrationResult(string $migration, string $status, float $time): void
    {
        $statusColor = $status === 'DONE' ? self::BRIGHT_GREEN : self::BRIGHT_RED;
        $timeFormatted = $this->formatTime($time);

        $migrationName = str_pad($migration, 60, '.');
        $this->line("  {$migrationName} {$statusColor}{$status}" . self::RESET . " {$timeFormatted}");
    }

    public function infoBlock(string $title, string $content): void
    {
        $this->line(self::BRIGHT_BLUE . "  {$title}: " . self::RESET . $content);
    }

    public function successBlock(string $title, string $content): void
    {
        $this->line(self::BRIGHT_GREEN . "  ✓ {$title}: " . self::RESET . $content);
    }

    public function line(string $text = ''): void
    {
        // 4 for "    " padding
        // If console width is too small or detection failed, default to sensible content width
        $effectiveConsoleWidth = ($this->consoleWidth > 60) ? $this->consoleWidth : 120;
        $contentWidth = $effectiveConsoleWidth - $this->logoWidth - 4;

        // Handle incoming newlines first
        $rawLines = explode(PHP_EOL, $text);

        foreach ($rawLines as $rawLine) {
            // Wrap long lines using our wrapText helper (which respects ANSI codes)
            $wrappedLines = $this->wrapText($rawLine, $contentWidth);

            // If empty (e.g. just a newline), ensure we print at least one line
            if (empty($wrappedLines)) {
                $wrappedLines = [''];
            }

            foreach ($wrappedLines as $lineContent) {
                // Get next logo line or empty padding
                $logoLine = $this->logoLines[$this->logoLineIndex] ?? str_repeat(' ', $this->logoWidth);

                // Increment logo index
                if ($this->logoLineIndex < count($this->logoLines)) {
                    $this->logoLineIndex++;
                }

                echo $logoLine . "    " . $lineContent . PHP_EOL;
            }
        }
    }

    // ... 

    private function getPlugLogoLines(): array
    {
        $width = $this->logoWidth;
        $c = self::BRIGHT_CYAN;
        $w = self::BRIGHT_WHITE;
        $b = self::BRIGHT_BLUE;
        $d = self::DIM;
        $r = self::RESET;

        // Professional, Minimalist Plug Logo
        $logo = [
            "",
            $w . "      _    " . $r,
            $w . "    _| |_  " . $r,
            $w . "   |  _  | " . $r,
            $w . "   | | | | " . $r,
            $w . "   | |_| | " . $r,
            $w . "   |_____| " . $r,
            $w . "    |___|  " . $r,
            "",
            $c . "    PLUGS  " . $r,
            $d . "    FRAME  " . $r,
            $d . "    WORK   " . $r,
            "",
            $b . "   v1.0.0  " . $r,
        ];

        return array_map(function ($line) use ($width) {
            $stripped = $this->stripAnsiCodes($line);
            $padding = $width - mb_strwidth($stripped);
            return $line . str_repeat(' ', max(0, $padding));
        }, $logo);
    }

    public function info(string $text): void
    {
        $this->line("  " . self::BRIGHT_BLUE . "ℹ" . self::RESET . "  " . $text);
    }

    public function success(string $text): void
    {
        $this->line("  " . self::BRIGHT_GREEN . "✔" . self::RESET . "  " . $text);
    }

    public function warning(string $text): void
    {
        $this->line("  " . self::BRIGHT_YELLOW . "⚠" . self::RESET . "  " . $text);
    }

    public function error(string $text): void
    {
        $this->line("  " . self::BRIGHT_RED . "✖" . self::RESET . "  " . $text);
    }

    public function note(string $text): void
    {
        $this->line(self::DIM . "  NOTE: " . $text . self::RESET);
    }

    public function critical(string $text): void
    {
        $this->line(self::BG_RED . self::BRIGHT_WHITE . self::BOLD . " CRITICAL " . self::RESET . " " . self::BRIGHT_RED . $text . self::RESET);
    }

    public function debug(string $text): void
    {
        $this->line(self::DIM . "🐛 Debug: " . $text . self::RESET);
    }

    public function header(string $text): void
    {
        $width = $this->consoleWidth - 4;
        $text = "  " . $text . "  ";
        $padding = str_repeat(" ", (int) (($width - mb_strwidth($text)) / 2));

        $this->line();
        $this->line(self::BRIGHT_BLUE . str_repeat(" ", $width) . self::RESET);
        $this->line(self::BRIGHT_BLUE . $padding . self::BRIGHT_WHITE . self::BOLD . $text . self::BRIGHT_BLUE . $padding . self::RESET);
        $this->line(self::BRIGHT_BLUE . str_repeat(" ", $width) . self::RESET);
        $this->line();
    }

    public function section(string $title): void
    {
        $this->newLine();
        $this->line(self::BRIGHT_BLUE . "  :: " . self::BOLD . self::BRIGHT_WHITE . strtoupper($title) . self::RESET);
        $this->line();
    }

    public function loading(string $message, callable $callback): mixed
    {
        $frames = ['⠋', '⠙', '⠹', '⠸', '⠼', '⠴', '⠦', '⠧', '⠇', '⠏'];
        $result = null;
        $completed = false;
        $i = 0;

        $maxWidth = $this->consoleWidth - 4;

        // Draw top border
        $this->line(self::BRIGHT_BLUE . "╭" . str_repeat("─", $maxWidth) . "╮" . self::RESET);

        $startTime = microtime(true);

        while (!$completed) {
            $elapsed = microtime(true) - $startTime;
            $spinnerText = $frames[$i++ % count($frames)] . " " . $message;

            // Add elapsed time
            if ($elapsed > 0.1) {
                $timeText = sprintf("(%.1fs)", $elapsed);
                $fullText = $spinnerText . " " . $timeText;
            } else {
                $fullText = $spinnerText;
            }

            $textLen = mb_strwidth($fullText);
            $padding = $maxWidth - $textLen;

            echo "\r" . self::BRIGHT_BLUE . "│" . self::RESET .
                " " . self::BRIGHT_CYAN . $fullText . self::RESET .
                str_repeat(" ", max(0, $padding)) . " " .
                self::BRIGHT_BLUE . "│" . self::RESET;

            // Try to execute callback
            if (!$completed) {
                try {
                    $result = $callback();
                    $completed = true;
                } catch (\Throwable $e) {
                    $completed = true;

                    // Error line
                    $errorText = "✗ " . $message . " - Failed!";
                    $textLen = mb_strwidth($errorText);
                    $padding = $maxWidth - $textLen;

                    echo "\r" . self::BRIGHT_BLUE . "│" . self::RESET .
                        " " . self::BRIGHT_RED . $errorText . self::RESET .
                        str_repeat(" ", max(0, $padding)) . " " .
                        self::BRIGHT_BLUE . "│" . self::RESET . "\n";

                    $this->line(self::BRIGHT_BLUE . "╰" . str_repeat("─", $maxWidth) . "╯" . self::RESET);
                    throw $e;
                }
            }

            usleep(100000);

            // Timeout after 30 seconds
            if (microtime(true) - $startTime > 30) {
                break;
            }
        }

        // Success line with elapsed time
        $totalTime = microtime(true) - $startTime;
        $successText = "✔ " . $message . sprintf(" (%.2fs)", $totalTime);
        $textLen = mb_strwidth($successText);
        $padding = $maxWidth - $textLen;

        echo "\r" . self::BRIGHT_BLUE . "│" . self::RESET .
            " " . self::BRIGHT_GREEN . $successText . self::RESET .
            str_repeat(" ", max(0, $padding)) . " " .
            self::BRIGHT_BLUE . "│" . self::RESET . "\n";

        // Draw bottom border
        $this->line(self::BRIGHT_BLUE . "╰" . str_repeat("─", $maxWidth) . "╯" . self::RESET);

        return $result;
    }

    public function spinner(string $message, int|callable $secondsOrCallback = 2): void
    {
        $frames = ['⠋', '⠙', '⠹', '⠸', '⠼', '⠴', '⠦', '⠧', '⠇', '⠏'];
        $i = 0;

        // Create a full-width box for the spinner
        $maxWidth = $this->consoleWidth - 4;

        if (is_callable($secondsOrCallback)) {
            while (!$secondsOrCallback()) {
                $spinnerText = $frames[$i++ % count($frames)] . " " . $message;
                $textLen = mb_strwidth($spinnerText);
                $padding = $maxWidth - $textLen;

                echo "\r" . self::BRIGHT_BLUE . "│" . self::RESET .
                    " " . self::BRIGHT_CYAN . $spinnerText . self::RESET .
                    str_repeat(" ", max(0, $padding)) . " " .
                    self::BRIGHT_BLUE . "│" . self::RESET;

                usleep(120000);

                if ($i > 500) {
                    $this->warning("Spinner timeout reached");
                    break;
                }
            }
        } else {
            $end = time() + $secondsOrCallback;
            while (time() < $end) {
                $spinnerText = $frames[$i++ % count($frames)] . " " . $message;
                $textLen = mb_strwidth($spinnerText);
                $padding = $maxWidth - $textLen;

                echo "\r" . self::BRIGHT_BLUE . "│" . self::RESET .
                    " " . self::BRIGHT_CYAN . $spinnerText . self::RESET .
                    str_repeat(" ", max(0, $padding)) . " " .
                    self::BRIGHT_BLUE . "│" . self::RESET;

                usleep(120000);
            }
        }

        // Success line with full width
        $successText = "✔ " . $message;
        $textLen = mb_strwidth($successText);
        $padding = $maxWidth - $textLen;

        echo "\r" . self::BRIGHT_BLUE . "│" . self::RESET .
            " " . self::BRIGHT_GREEN . $successText . self::RESET .
            str_repeat(" ", max(0, $padding)) . " " .
            self::BRIGHT_BLUE . "│" . self::RESET . "\n";
    }

    public function progressBar(int $max, callable $step, string $label = 'Progress'): void
    {
        $maxWidth = $this->consoleWidth - 4;

        // Draw top border
        $this->line(self::BRIGHT_BLUE . "╭" . str_repeat("─", $maxWidth) . "╮" . self::RESET);

        // Draw label line
        $labelLen = mb_strwidth($label);
        $labelPadding = $maxWidth - $labelLen;
        $this->line(
            self::BRIGHT_BLUE . "│" . self::RESET .
            " " . self::BOLD . $label . self::RESET .
            str_repeat(" ", $labelPadding) . " " .
            self::BRIGHT_BLUE . "│" . self::RESET
        );

        // Draw separator
        $this->line(self::BRIGHT_BLUE . "├" . str_repeat("─", $maxWidth) . "┤" . self::RESET);

        for ($i = 1; $i <= $max; $i++) {
            $step($i);
            $percent = (int) (($i / $max) * 100);

            // Calculate bar width (leave space for percentage and counter)
            $barWidth = $maxWidth - 20; // Reserve space for " 100% (999/999) "
            $filled = (int) (($percent / 100) * $barWidth);
            $empty = $barWidth - $filled;

            $bar = str_repeat("█", $filled) . str_repeat("░", $empty);
            $color = $percent < 33 ? self::BRIGHT_RED : ($percent < 66 ? self::BRIGHT_YELLOW : self::BRIGHT_GREEN);

            $progressText = $color . "▐" . $bar . "▌" . self::RESET .
                " " . self::BOLD . str_pad($percent . "%", 4, " ", STR_PAD_LEFT) . self::RESET .
                " " . self::DIM . "($i/$max)" . self::RESET;

            $textLen = mb_strwidth(strip_tags($progressText));
            $padding = $maxWidth - $textLen - 20; // Adjust for ANSI codes

            echo "\r" . self::BRIGHT_BLUE . "│" . self::RESET .
                " " . $progressText .
                str_repeat(" ", max(0, $padding + 18)) . " " .
                self::BRIGHT_BLUE . "│" . self::RESET;

            usleep(50000);
        }

        echo "\n";

        // Draw completion message
        $completedText = "✅ Completed!";
        $textLen = mb_strwidth($completedText);
        $padding = $maxWidth - $textLen;

        $this->line(
            self::BRIGHT_BLUE . "│" . self::RESET .
            " " . self::BRIGHT_GREEN . $completedText . self::RESET .
            str_repeat(" ", $padding) . " " .
            self::BRIGHT_BLUE . "│" . self::RESET
        );

        // Draw bottom border
        $this->line(self::BRIGHT_BLUE . "╰" . str_repeat("─", $maxWidth) . "╯" . self::RESET);
        $this->line();
    }

    public function progress(int $current, int $total, string $message = ''): void
    {
        $maxWidth = $this->consoleWidth - 4;
        $percentage = ($current / $total) * 100;

        // Calculate bar width
        $barWidth = $maxWidth - 30; // Reserve space for percentage, counter, and message
        $filled = (int) ($barWidth * ($current / $total));
        $bar = str_repeat('█', $filled) . str_repeat('░', $barWidth - $filled);

        $progressText = self::BRIGHT_CYAN . 'Progress:' . self::RESET .
            " " . sprintf("%3d%%", $percentage) .
            " [" . $bar . "]";

        if ($message) {
            $progressText .= " " . self::DIM . $message . self::RESET;
        }

        $textLen = mb_strwidth($this->stripAnsiCodes($progressText));
        $padding = $maxWidth - $textLen;

        echo "\r" . self::BRIGHT_BLUE . "│" . self::RESET .
            " " . $progressText .
            str_repeat(" ", max(0, $padding)) . " " .
            self::BRIGHT_BLUE . "│" . self::RESET;

        if ($current === $total) {
            echo PHP_EOL;
        }
    }

    public function taskWithBox(string $message, callable $callback): mixed
    {
        $maxWidth = $this->consoleWidth - 4;

        // Draw top border
        $this->line(self::BRIGHT_BLUE . "╭" . str_repeat("─", $maxWidth) . "╮" . self::RESET);

        $frames = ['⠋', '⠙', '⠹', '⠸', '⠼', '⠴', '⠦', '⠧', '⠇', '⠏'];
        $result = null;
        $error = null;
        $completed = false;
        $i = 0;

        $startTime = microtime(true);

        while (!$completed) {
            $elapsed = microtime(true) - $startTime;
            $spinnerText = $frames[$i++ % count($frames)] . " " . $message;

            if ($elapsed > 0.1) {
                $timeText = sprintf("(%.1fs)", $elapsed);
                $fullText = $spinnerText . " " . $timeText;
            } else {
                $fullText = $spinnerText;
            }

            $textLen = mb_strwidth($fullText);
            $padding = $maxWidth - $textLen;

            echo "\r" . self::BRIGHT_BLUE . "│" . self::RESET .
                " " . self::BRIGHT_CYAN . $fullText . self::RESET .
                str_repeat(" ", max(0, $padding)) . " " .
                self::BRIGHT_BLUE . "│" . self::RESET;

            if (!$completed) {
                try {
                    $result = $callback();
                    $completed = true;
                } catch (\Throwable $e) {
                    $error = $e;
                    $completed = true;
                }
            }

            usleep(120000);

            if ($i > 500) {
                break;
            }
        }

        if ($error) {
            $errorText = "✗ " . $message . " - Failed!";
            $textLen = mb_strwidth($errorText);
            $padding = $maxWidth - $textLen;

            echo "\r" . self::BRIGHT_BLUE . "│" . self::RESET .
                " " . self::BRIGHT_RED . $errorText . self::RESET .
                str_repeat(" ", max(0, $padding)) . " " .
                self::BRIGHT_BLUE . "│" . self::RESET . "\n";

            $this->line(self::BRIGHT_BLUE . "╰" . str_repeat("─", $maxWidth) . "╯" . self::RESET);
            throw $error;
        }

        $totalTime = microtime(true) - $startTime;
        $successText = "✔ " . $message . sprintf(" (%.2fs)", $totalTime);
        $textLen = mb_strwidth($successText);
        $padding = $maxWidth - $textLen;

        echo "\r" . self::BRIGHT_BLUE . "│" . self::RESET .
            " " . self::BRIGHT_GREEN . $successText . self::RESET .
            str_repeat(" ", max(0, $padding)) . " " .
            self::BRIGHT_BLUE . "│" . self::RESET . "\n";

        // Draw bottom border
        $this->line(self::BRIGHT_BLUE . "╰" . str_repeat("─", $maxWidth) . "╯" . self::RESET);

        return $result;
    }

    public function table(array $headers, array $rows): void
    {
        if (empty($headers) || empty($rows)) {
            $this->warning("No data to display in table");
            return;
        }

        $cols = count($headers);
        $availableWidth = $this->consoleWidth - 2;

        // Calculate column widths
        $widths = [];
        foreach ($headers as $i => $header) {
            $widths[$i] = mb_strwidth((string) $header) + 2;
        }

        foreach ($rows as $row) {
            for ($i = 0; $i < $cols; $i++) {
                $cell = (string) ($row[$i] ?? '');
                $widths[$i] = max($widths[$i], mb_strwidth($this->stripAnsiCodes($cell)) + 2);
            }
        }

        $this->newLine();

        // Header
        echo "  ";
        foreach ($headers as $i => $header) {
            echo self::BOLD . self::BRIGHT_WHITE . str_pad($header, $widths[$i]) . self::RESET;
        }
        echo "\n";

        // Separator (minimal)
        echo "  ";
        foreach ($widths as $w) {
            echo self::DIM . str_repeat("─", $w - 2) . "  " . self::RESET;
        }
        echo "\n";

        // Rows
        foreach ($rows as $row) {
            echo "  ";
            for ($i = 0; $i < $cols; $i++) {
                $cell = (string) ($row[$i] ?? '');
                $cleanCell = $this->stripAnsiCodes($cell);
                $padding = $widths[$i] - mb_strwidth($cleanCell);
                echo $cell . str_repeat(" ", max(0, $padding));
            }
            echo "\n";
        }

        $this->newLine();
    }

    public function box(string $content, string $title = '', string $type = 'info'): void
    {
        $maxWidth = $this->consoleWidth - 4;

        $colors = [
            'info' => self::BRIGHT_BLUE,
            'success' => self::BRIGHT_GREEN,
            'warning' => self::BRIGHT_YELLOW,
            'error' => self::BRIGHT_RED,
            'default' => self::BRIGHT_WHITE,
        ];

        $color = $colors[$type] ?? $colors['default'];

        $this->line();

        if ($title) {
            $this->line("  " . $color . self::BOLD . strtoupper($title) . self::RESET);
        }

        $lines = explode("\n", $content);
        foreach ($lines as $line) {
            $this->line("  " . $color . "│" . self::RESET . " " . $line);
        }

        $this->line();
    }

    public function panel(string $content, string $title = ''): void
    {
        $this->newLine();
        if ($title) {
            $this->line("  " . self::BRIGHT_CYAN . "│ " . self::BOLD . $title . self::RESET);
        }

        $lines = explode("\n", $content);
        foreach ($lines as $line) {
            $this->line("  " . self::BRIGHT_CYAN . "│ " . self::RESET . $line);
        }
        $this->newLine();
    }

    public function title(string $text): void
    {
        $maxWidth = $this->consoleWidth - 4;
        $textLen = mb_strwidth($text);

        $this->line();
        $this->line(self::BRIGHT_CYAN . "╔" . str_repeat("═", $maxWidth) . "╗" . self::RESET);

        // Center the text
        $leftPadding = (int) (($maxWidth - $textLen - 2) / 2);
        $rightPadding = $maxWidth - $textLen - $leftPadding - 2;

        $this->line(
            self::BRIGHT_CYAN . "║" . self::RESET .
            str_repeat(" ", $leftPadding) .
            self::BRIGHT_WHITE . self::BOLD . $text . self::RESET .
            str_repeat(" ", $rightPadding) .
            self::BRIGHT_CYAN . "║" . self::RESET
        );

        $this->line(self::BRIGHT_CYAN . "╚" . str_repeat("═", $maxWidth) . "╝" . self::RESET);
        $this->line();
    }

    public function banner(string $text): void
    {
        $maxWidth = $this->consoleWidth;
        $textLen = mb_strwidth($text);

        // Calculate padding for centered text
        $totalPadding = $maxWidth - $textLen;
        $leftPadding = (int) ($totalPadding / 2);
        $rightPadding = $totalPadding - $leftPadding;

        $this->line();
        $this->gradient(str_repeat("█", $maxWidth));

        $centeredText = str_repeat(" ", $leftPadding) . $text . str_repeat(" ", $rightPadding);
        echo self::BOLD . self::BRIGHT_WHITE . $centeredText . self::RESET . "\n";

        $this->gradient(str_repeat("█", $maxWidth));
        $this->line();
    }

    public function gradient(string $text): void
    {
        $colors = [
            self::GRADIENT_PURPLE,
            self::GRADIENT_PINK,
            self::GRADIENT_ORANGE,
            self::BRIGHT_YELLOW,
            self::BRIGHT_GREEN,
            self::GRADIENT_TEAL,
            self::BRIGHT_CYAN,
            self::BRIGHT_BLUE
        ];

        $chars = mb_str_split($text);
        $len = count($chars);
        $colorCount = count($colors);

        for ($i = 0; $i < $len; $i++) {
            $colorIndex = (int) (($i / max(1, $len - 1)) * ($colorCount - 1));
            echo $colors[$colorIndex] . $chars[$i];
        }
        echo self::RESET . "\n";
    }

    public function countdown(int $seconds, string $message = 'Starting in'): void
    {
        $maxWidth = $this->consoleWidth - 4;

        // Draw top border
        $this->line(self::BRIGHT_BLUE . "╭" . str_repeat("─", $maxWidth) . "╮" . self::RESET);

        for ($i = $seconds; $i > 0; $i--) {
            $countdownText = $message . " " . self::BOLD . $i . self::RESET . "s...";
            $textLen = mb_strwidth($this->stripAnsiCodes($countdownText));
            $padding = (int) (($maxWidth - $textLen) / 2);
            $rightPadding = $maxWidth - $textLen - $padding;

            echo "\r" . self::BRIGHT_BLUE . "│" . self::RESET .
                str_repeat(" ", $padding) .
                self::BRIGHT_YELLOW . $countdownText . self::RESET .
                str_repeat(" ", $rightPadding) .
                self::BRIGHT_BLUE . "│" . self::RESET;

            sleep(1);
        }

        // Success message
        $successText = "🚀 Let's go!";
        $textLen = mb_strwidth($successText);
        $padding = (int) (($maxWidth - $textLen) / 2);
        $rightPadding = $maxWidth - $textLen - $padding;

        echo "\r" . self::BRIGHT_BLUE . "│" . self::RESET .
            str_repeat(" ", $padding) .
            self::BRIGHT_GREEN . $successText . self::RESET .
            str_repeat(" ", $rightPadding) .
            self::BRIGHT_BLUE . "│" . self::RESET . "\n";

        // Draw bottom border
        $this->line(self::BRIGHT_BLUE . "╰" . str_repeat("─", $maxWidth) . "╯" . self::RESET);
        $this->line();
    }

    public function alert(string $message, string $type = 'info'): void
    {
        $icons = [
            'info' => 'ℹ️',
            'success' => '✅',
            'warning' => '⚠️',
            'error' => '❌',
            'question' => '❓',
        ];

        $icon = $icons[$type] ?? $icons['info'];
        $this->box($message, "{$icon} Alert", $type);
    }

    public function quote(string $text, string $author = ''): void
    {
        $this->line();
        $this->line(self::ITALIC . self::BRIGHT_CYAN . "  \"" . $text . "\"" . self::RESET);
        if ($author) {
            $this->line(self::DIM . "    — " . $author . self::RESET);
        }
        $this->line();
    }

    public function step(int $current, int $total, string $message): void
    {
        $maxWidth = $this->consoleWidth - 4;
        $percentage = ($current / $total) * 100;
        $color = $percentage < 33 ? self::BRIGHT_RED : ($percentage < 66 ? self::BRIGHT_YELLOW : self::BRIGHT_GREEN);

        $stepText = $color . "[{$current}/{$total}]" . self::RESET . " {$message}";
        $textLen = mb_strwidth($this->stripAnsiCodes($stepText));
        $padding = $maxWidth - $textLen;

        $this->line(
            self::BRIGHT_BLUE . "│" . self::RESET .
            " " . $stepText .
            str_repeat(" ", max(0, $padding)) . " " .
            self::BRIGHT_BLUE . "│" . self::RESET
        );
    }

    public function bulletList(array $items, string $bullet = '•'): void
    {
        foreach ($items as $item) {
            $this->line("  " . self::BRIGHT_CYAN . $bullet . self::RESET . " {$item}");
        }
    }

    public function numberedList(array $items): void
    {
        foreach ($items as $index => $item) {
            $number = $index + 1;
            $this->line("  " . self::BRIGHT_CYAN . "{$number}." . self::RESET . " {$item}");
        }
    }

    public function tree(array $items, int $level = 0): void
    {
        foreach ($items as $key => $value) {
            $indent = str_repeat("  ", $level);
            $branch = $level > 0 ? "├─ " : "";

            if (is_array($value)) {
                $this->line($indent . self::BRIGHT_CYAN . $branch . $key . self::RESET);
                $this->tree($value, $level + 1);
            } else {
                $this->line($indent . self::DIM . $branch . self::RESET . $value);
            }
        }
    }

    public function keyValue(string $key, string $value, int $padding = 20): void
    {
        $key = str_pad($key, $padding);
        $this->line("  " . self::BRIGHT_WHITE . $key . self::RESET . ": " . $value);
    }

    public function diff(string $old, string $new): void
    {
        $this->line();
        $this->line(self::BRIGHT_RED . "- " . $old . self::RESET);
        $this->line(self::BRIGHT_GREEN . "+ " . $new . self::RESET);
        $this->line();
    }

    public function divider(string $char = '─', string $color = self::BRIGHT_BLACK): void
    {
        $this->line($color . str_repeat($char, $this->consoleWidth) . self::RESET);
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

    public function commandTable(array $commands): void
    {
        $this->line();
        $this->sectionTitle('Available Commands:');

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
            $this->line(self::BRIGHT_WHITE . "  " . $category . self::RESET);

            foreach ($categoryCommands as $name => $commandClass) {
                try {
                    $command = new $commandClass($name);
                    $description = $command->description();
                } catch (\Throwable $e) {
                    $description = 'No description';
                }

                $name = str_pad($name, 25);
                $this->line("    " . self::BRIGHT_GREEN . $name . self::RESET . $description);
            }
            $this->line();
        }
    }

    public function argumentList(array $arguments): void
    {
        if (empty($arguments))
            return;

        $this->line(self::BRIGHT_WHITE . "  Arguments:" . self::RESET);
        foreach ($arguments as $name => $description) {
            $this->line("    " . self::BRIGHT_GREEN . str_pad($name, 20) . self::RESET . $description);
        }
        $this->line();
    }

    public function optionList(array $options): void
    {
        if (empty($options))
            return;

        $this->line(self::BRIGHT_WHITE . "  Options:" . self::RESET);
        foreach ($options as $name => $description) {
            $this->line("    " . self::BRIGHT_GREEN . str_pad($name, 25) . self::RESET . $description);
        }
        $this->line();
    }

    // ========================================================================
    // INTERACTIVE INPUT METHODS
    // ========================================================================

    public function ask(string $question, ?string $default = null): string
    {
        $prompt = self::BRIGHT_CYAN . "? " . self::RESET . $question;

        if ($default !== null) {
            $prompt .= " " . self::DIM . "({$default})" . self::RESET;
        }

        echo $prompt . ": ";

        $handle = fopen("php://stdin", "r");
        $input = trim(fgets($handle));
        fclose($handle);

        return $input === '' && $default !== null ? $default : $input;
    }

    public function secret(string $question): string
    {
        echo self::BRIGHT_CYAN . "🔒 " . self::RESET . $question . ": ";

        if (strtoupper(substr(PHP_OS, 0, 3)) === 'WIN') {
            $password = '';
            while (true) {
                $char = fgetc(STDIN);
                if ($char === "\n" || $char === "\r") {
                    break;
                }
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
        $suffix = $default ? '[Y/n]' : '[y/N]';
        echo self::BRIGHT_YELLOW . "❓ " . self::RESET . $question . " " . self::DIM . $suffix . self::RESET . " ";

        $handle = fopen("php://stdin", "r");
        $input = strtolower(trim(fgets($handle)));
        fclose($handle);

        if ($input === '') {
            return $default;
        }

        return in_array($input, ['y', 'yes', '1', 'true']);
    }

    public function choice(string $question, array $choices, $default = null): string
    {
        $this->line();
        $this->line(self::BRIGHT_CYAN . "? " . self::RESET . self::BOLD . $question . self::RESET);
        $this->line();

        $indexedChoices = array_values($choices);

        foreach ($indexedChoices as $index => $choice) {
            $number = $index + 1;
            $highlight = ($default !== null && $choice === $default) ? self::BRIGHT_GREEN : self::BRIGHT_WHITE;
            echo "  " . $highlight . "[{$number}]" . self::RESET . " {$choice}\n";
        }

        $this->line();

        $prompt = self::BRIGHT_CYAN . "Select" . self::RESET;
        if ($default !== null) {
            $defaultIndex = array_search($default, $indexedChoices);
            $prompt .= " " . self::DIM . "(" . ($defaultIndex + 1) . ")" . self::RESET;
        }
        echo $prompt . ": ";

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
        $this->line(self::BRIGHT_CYAN . "? " . self::RESET . self::BOLD . $question . self::RESET);
        $this->line(self::DIM . "Enter numbers separated by commas (e.g., 1,3,4)" . self::RESET);
        $this->line();

        $indexedChoices = array_values($choices);

        foreach ($indexedChoices as $index => $choice) {
            $number = $index + 1;
            $isDefault = in_array($choice, $defaults);
            $marker = $isDefault ? self::BRIGHT_GREEN . "✓" . self::RESET : " ";
            echo "  [{$marker}] " . self::BRIGHT_WHITE . "[{$number}]" . self::RESET . " {$choice}\n";
        }

        $this->line();
        echo self::BRIGHT_CYAN . "Select (comma-separated)" . self::RESET . ": ";

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
            $index = (int) $number - 1;
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
        $this->line(self::BRIGHT_CYAN . "? " . self::RESET . $question);

        if (!empty($suggestions)) {
            $this->line(self::DIM . "Suggestions: " . implode(", ", $suggestions) . self::RESET);
        }

        if ($default !== null) {
            echo self::DIM . "({$default})" . self::RESET . " ";
        }

        echo ": ";

        $handle = fopen("php://stdin", "r");
        $input = trim(fgets($handle));
        fclose($handle);

        $this->line();
        return $input === '' && $default !== null ? $default : $input;
    }

    private function formatTime(float $seconds): string
    {
        if ($seconds < 0.001) {
            return number_format($seconds * 1000000, 2) . 'μs';
        } elseif ($seconds < 1) {
            return number_format($seconds * 1000, 2) . 'ms';
        } else {
            return number_format($seconds, 3) . 's';
        }
    }

    private function formatBytes(int $bytes): string
    {
        $units = ['B', 'KB', 'MB', 'GB'];
        $bytes = max($bytes, 0);
        $pow = floor(($bytes ? log($bytes) : 0) / log(1024));
        $pow = min($pow, count($units) - 1);
        $bytes /= (1 << (10 * $pow));

        return round($bytes, 2) . ' ' . $units[$pow];
    }

    private function stripAnsiCodes(string $text): string
    {
        return preg_replace('/\033\[[0-9;]*m/', '', $text);
    }

    public function metrics(float $time, int $memory): void
    {
        $this->line();
        $this->fullWidthLine('─', self::BRIGHT_BLACK);

        $timeFormatted = $this->formatTime($time);
        $memoryFormatted = $this->formatBytes($memory);

        $this->line("  " . self::BRIGHT_GREEN . "✓" . self::RESET . " Completed in " . self::BOLD . $timeFormatted . self::RESET);
        $this->line("  " . self::BRIGHT_BLUE . "📊" . self::RESET . " Memory: " . self::BOLD . $memoryFormatted . self::RESET);

        $this->fullWidthLine('─', self::BRIGHT_BLACK);
        $this->line();
    }

    public function errorBox(string $message, string $title = 'Error'): void
    {
        $this->box($message, $title, 'error');
    }

    private function wrapText(string $text, int $maxWidth): array
    {
        $words = explode(' ', $text);
        $lines = [];
        $currentLine = '';

        foreach ($words as $word) {
            $wordLen = mb_strwidth($word);
            $currentLen = mb_strwidth($this->stripAnsiCodes($currentLine));

            if ($currentLen + $wordLen + 1 <= $maxWidth) {
                $currentLine .= ($currentLine ? ' ' : '') . $word;
            } else {
                if ($currentLine) {
                    $lines[] = $currentLine;
                }
                $currentLine = $word;
            }
        }

        if ($currentLine) {
            $lines[] = $currentLine;
        }

        return $lines;
    }

    public function thePlugsSimpleLogo(): void
    {
        $logo = [
            "  _____ _  _ ___   ___ _   _   _  ___ ___ ",
            " |_   _| || | __| | _ \\ | | | | |/ __/ __|",
            "   | | | __ | _|  |  _/ |_| |_| | (_ \\__ \\",
            "   |_| |_||_|___| |_| |_(_)\\___/ \\___|___/",
            "  1 0 1 0 0 1   1 1 0 0 1 0   1 0 1 1 0 1 "
        ];

        $maxWidth = 0;
        foreach ($logo as $line) {
            $maxWidth = max($maxWidth, mb_strwidth($line));
        }

        $padding = (int) (($this->consoleWidth - $maxWidth) / 2);

        $this->line();
        foreach ($logo as $index => $line) {
            $spaces = str_repeat(" ", $padding);

            if ($index === count($logo) - 1) {
                // Color the binary line
                $colored = preg_replace_callback('/[01]/', function ($m) {
                    return ($m[0] === '1' ? self::BRIGHT_CYAN : self::BRIGHT_BLUE) . $m[0] . self::RESET;
                }, $line);
                $this->line($spaces . $colored);
            } else {
                $this->line($spaces . self::BRIGHT_CYAN . $line . self::RESET);
            }
        }
        $this->line();
    }

    public function thePlugsMinimalLogo(): void
    {
        $logo = [
            " _____ _  _ ___   ___ _   _   _  ___ ___ ",
            "|_   _| || | __| | _ \\ | | | | |/ __/ __|",
            "  | | | __ | _|  |  _/ |_| |_| | (_ \\__ \\",
            "  |_| |_||_|___| |_| |_(_)\\___/ \\___|___/",
        ];

        $binaryLine = "  1 0 1 0 0 1   1 1 0 0 1 0   1 0 1 1 0 1 ";

        $maxWidth = 0;
        foreach ($logo as $line) {
            $maxWidth = max($maxWidth, mb_strwidth($line));
        }

        $padding = (int) (($this->consoleWidth - $maxWidth) / 2);

        $this->line();
        foreach ($logo as $line) {
            $spaces = str_repeat(" ", $padding);
            $this->line($spaces . self::BRIGHT_CYAN . $line . self::RESET);
        }

        // Add binary line with colors
        $spaces = str_repeat(" ", $padding);
        $colored = preg_replace_callback('/[01]/', function ($m) {
            return ($m[0] === '1' ? self::BRIGHT_GREEN : self::BRIGHT_BLUE) . $m[0] . self::RESET;
        }, $binaryLine);
        $this->line($spaces . $colored);
        $this->line();
    }

    public function thePlugsDashLogo(): void
    {
        $logo = [
            " ----- -  - ---   --- -   -   - --- --- ",
            "|  -  | || | -| | - \\ | | | | |/ -/ -|",
            "  | | | -- | -  |  -/ |-| |-| | (- \\-- ",
            "  |-| |-||-|---| |-| |-(-)\\---/ \\_--|---|",
            " 1-0-1-0-0-1  1-1-0-0-1-0  1-0-1-1-0-1 "
        ];

        $maxWidth = 0;
        foreach ($logo as $line) {
            $maxWidth = max($maxWidth, mb_strwidth($line));
        }

        $padding = (int) (($this->consoleWidth - $maxWidth) / 2);

        $this->line();
        foreach ($logo as $index => $line) {
            $spaces = str_repeat(" ", $padding);

            if ($index === count($logo) - 1) {
                // Color the binary line
                $colored = preg_replace_callback('/[01]/', function ($m) {
                    return ($m[0] === '1' ? self::BRIGHT_CYAN : self::BRIGHT_BLUE) . $m[0] . self::RESET;
                }, $line);
                $this->line($spaces . $colored);
            } else {
                $this->line($spaces . self::BRIGHT_CYAN . $line . self::RESET);
            }
        }
        $this->line();
    }

    public function thePlugsCleanLogo(): void
    {
        $logo = [
            "_____ _  _ ___   ___ _   _   _  ___ ___ ",
            " | | | || | __| | _ | | | | | |/ __/ __|",
            " | | | __ | _|  |  _| |_| |_| | (_ __ |",
            " |_| |_||_|___| |_| |_(_)___/ ___|___/",
        ];

        $binaryLine = " 1-0-1-0-0-1  1-1-0-0-1-0  1-0-1-1-0-1";

        $maxWidth = 0;
        foreach ($logo as $line) {
            $maxWidth = max($maxWidth, mb_strwidth($line));
        }
        $maxWidth = max($maxWidth, mb_strwidth($binaryLine));

        $padding = (int) (($this->consoleWidth - $maxWidth) / 2);

        $this->line();
        foreach ($logo as $line) {
            $spaces = str_repeat(" ", $padding);
            $this->line($spaces . self::BRIGHT_CYAN . $line . self::RESET);
        }

        // Add centered binary line with colors
        $binaryPadding = (int) (($this->consoleWidth - mb_strwidth($binaryLine)) / 2);
        $spaces = str_repeat(" ", $binaryPadding);
        $colored = preg_replace_callback('/[01]/', function ($m) {
            return ($m[0] === '1' ? self::BRIGHT_GREEN : self::BRIGHT_BLUE) . $m[0] . self::RESET;
        }, $binaryLine);
        $this->line($spaces . $colored);
        $this->line();
    }

    public function thePlugsTinyLogo(): void
    {
        $logo = [
            "THE PLUGS",
            "1-0-1-0 1-1-0-1"
        ];

        foreach ($logo as $index => $line) {
            $padding = (int) (($this->consoleWidth - mb_strwidth($line)) / 2);
            $spaces = str_repeat(" ", $padding);

            if ($index === 0) {
                // Title line with letter spacing
                $spaced = implode(' ', str_split($line));
                $padding = (int) (($this->consoleWidth - mb_strwidth($spaced)) / 2);
                $spaces = str_repeat(" ", $padding);
                $this->line($spaces . self::BRIGHT_CYAN . self::BOLD . $spaced . self::RESET);
            } else {
                // Binary line
                $colored = preg_replace_callback('/[01]/', function ($m) {
                    return ($m[0] === '1' ? self::BRIGHT_GREEN : self::BRIGHT_BLUE) . $m[0] . self::RESET;
                }, $line);
                $this->line($spaces . $colored);
            }
        }
        $this->line();
    }

    public function columns(array $leftLines, array $rightLines, int $leftWidth = 40): void
    {
        $maxLines = max(count($leftLines), count($rightLines));

        $this->line();

        for ($i = 0; $i < $maxLines; $i++) {
            $left = $leftLines[$i] ?? '';
            $right = $rightLines[$i] ?? '';

            // Calculate padding needed for left column
            // We strip ANSI codes to get true length
            $visibleLeft = mb_strwidth($this->stripAnsiCodes($left));
            $padding = max(0, $leftWidth - $visibleLeft);

            $this->line($left . str_repeat(' ', $padding) . "  " . $right);
        }

        $this->line();
    }

    public function getCleanLogoAsArray(): array
    {
        // "PLUGS" in a cleaner, bold block font
        $logo = [
            "  _____  _      _    _   _____   _____ ",
            " |  __ \| |    | |  | | / ____| / ____|",
            " | |__) | |    | |  | || |  __ | (___  ",
            " |  ___/| |    | |  | || | |_ | \___ \ ",
            " | |    | |____| |__| || |__| | ____) |",
            " |_|    |______|\____/  \_____||_____/ ",
        ];

        // A simple, clear separator line
        $binaryLine = " ---------------------------------------";

        $formatted = [];

        foreach ($logo as $line) {
            $formatted[] = self::BOLD . self::BRIGHT_CYAN . $line . self::RESET;
        }

        $formatted[] = self::DIM . $binaryLine . self::RESET;

        return $formatted;
    }

}
