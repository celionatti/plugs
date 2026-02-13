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
    public const RESET = "\033[0m";

    // Standard colors
    public const BLACK = "\033[30m";
    public const RED = "\033[31m";
    public const GREEN = "\033[32m";
    public const YELLOW = "\033[33m";
    public const BLUE = "\033[34m";
    public const MAGENTA = "\033[35m";
    public const CYAN = "\033[36m";
    public const WHITE = "\033[37m";

    // Bright colors
    public const BRIGHT_BLACK = "\033[90m";
    public const BRIGHT_RED = "\033[91m";
    public const BRIGHT_GREEN = "\033[92m";
    public const BRIGHT_YELLOW = "\033[93m";
    public const BRIGHT_BLUE = "\033[94m";
    public const BRIGHT_MAGENTA = "\033[95m";
    public const BRIGHT_CYAN = "\033[96m";
    public const BRIGHT_WHITE = "\033[97m";

    // Background colors
    public const BG_RED = "\033[41m";
    public const BG_GREEN = "\033[42m";
    public const BG_YELLOW = "\033[43m";
    public const BG_BLUE = "\033[44m";
    public const BG_MAGENTA = "\033[45m";
    public const BG_CYAN = "\033[46m";

    // Text styles
    public const BOLD = "\033[1m";
    public const DIM = "\033[2m";
    public const ITALIC = "\033[3m";
    public const UNDERLINE = "\033[4m";
    public const BLINK = "\033[5m";
    public const REVERSE = "\033[7m";
    public const STRIKETHROUGH = "\033[9m";

    // Gradient colors
    public const GRADIENT_PURPLE = "\033[38;5;135m";
    public const GRADIENT_PINK = "\033[38;5;211m";
    public const GRADIENT_ORANGE = "\033[38;5;208m";
    public const GRADIENT_TEAL = "\033[38;5;80m";

    private int $consoleWidth;

    public function __construct()
    {
        $this->consoleWidth = $this->getConsoleWidth();
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
        $contentWidth = $this->consoleWidth - 4;
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
        // Standard left margin: 2 spaces
        $margin = "  ";

        // Use full console width minus margin
        $contentWidth = $this->consoleWidth - 4;

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
                echo $margin . $lineContent . PHP_EOL;
            }
        }
    }

    public function info(string $text): void
    {
        $this->line("  " . self::BRIGHT_BLUE . "INFO" . self::RESET . " " . $text);
    }

    public function success(string $text): void
    {
        $this->line("  " . self::BRIGHT_GREEN . "DONE" . self::RESET . " " . $text);
    }

    public function warning(string $text): void
    {
        $this->line("  " . self::BRIGHT_YELLOW . "WARN" . self::RESET . " " . $text);
    }

    public function error(string $text): void
    {
        $this->line("  " . self::BRIGHT_RED . "FAIL" . self::RESET . " " . $text);
    }

    public function note(string $text): void
    {
        $this->line("  " . self::DIM . "NOTE" . self::RESET . " " . self::DIM . $text . self::RESET);
    }

    public function critical(string $text): void
    {
        $this->line("  " . self::BG_RED . self::BRIGHT_WHITE . self::BOLD . " ERROR " . self::RESET . " " . self::BRIGHT_RED . $text . self::RESET);
    }

    public function debug(string $text): void
    {
        $this->line("  " . self::DIM . "DEBUG" . self::RESET . " " . self::DIM . $text . self::RESET);
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

    public function gradientProgressBar(int $current, int $total, string $message = ''): void
    {
        $maxWidth = $this->consoleWidth - 4;
        $percentage = ($current / $total) * 100;

        $barWidth = $maxWidth - 30;
        $filled = (int) ($barWidth * ($current / $total));

        $bar = $this->getGradientString(str_repeat('█', $filled)) . self::DIM . str_repeat('░', $barWidth - $filled) . self::RESET;

        $progressText = self::BOLD . self::BRIGHT_WHITE . 'Progress:' . self::RESET .
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

    private function getGradientString(string $text): string
    {
        $colors = [
            self::GRADIENT_PURPLE,
            self::GRADIENT_PINK,
            self::GRADIENT_ORANGE,
            self::BRIGHT_YELLOW,
            self::BRIGHT_GREEN,
            self::GRADIENT_TEAL,
            self::BRIGHT_CYAN,
            self::BRIGHT_BLUE,
        ];

        $chars = mb_str_split($text);
        $len = count($chars);
        $colorCount = count($colors);
        $result = '';

        for ($i = 0; $i < $len; $i++) {
            $colorIndex = (int) (($i / max(1, $len - 1)) * ($colorCount - 1));
            $result .= $colors[$colorIndex] . $chars[$i];
        }

        return $result . self::RESET;
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

            try {
                $result = $callback();
                $completed = true;
            } catch (\Throwable $e) {
                $error = $e;
                $completed = true;
            }

            usleep(120000);

            /** @phpstan-ignore greater.alwaysFalse */
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

    public function sideBySide(string $left, string $right, string $leftTitle = '', string $rightTitle = ''): void
    {
        $halfWidth = (int) (($this->consoleWidth - 10) / 2);

        $leftLines = $this->wrapText($left, $halfWidth);
        $rightLines = $this->wrapText($right, $halfWidth);

        if ($leftTitle || $rightTitle) {
            $lt = self::BOLD . self::BRIGHT_WHITE . str_pad($leftTitle, $halfWidth) . self::RESET;
            $rt = self::BOLD . self::BRIGHT_WHITE . str_pad($rightTitle, $halfWidth) . self::RESET;
            $this->line("  " . self::BRIGHT_CYAN . "│ " . self::RESET . $lt . self::BRIGHT_CYAN . " │ " . self::RESET . $rt);
            $this->line("  " . self::BRIGHT_CYAN . "├─" . str_repeat("─", $halfWidth) . "┼─" . str_repeat("─", $halfWidth) . "┤" . self::RESET);
        }

        $maxLines = max(count($leftLines), count($rightLines));

        for ($i = 0; $i < $maxLines; $i++) {
            $l = str_pad($leftLines[$i] ?? '', $halfWidth);
            $r = str_pad($rightLines[$i] ?? '', $halfWidth);
            $this->line("  " . self::BRIGHT_CYAN . "│ " . self::RESET . $l . self::BRIGHT_CYAN . " │ " . self::RESET . $r);
        }
        $this->newLine();
    }

    public function json(mixed $data): void
    {
        $json = json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);

        // Simple regex colorizer
        $json = preg_replace_callback(
            '/(".*?")(\s*:)|(".*?")|(\b\d+\b)|(true|false|null)/',
            function ($matches) {
                if (!empty($matches[1]) && !empty($matches[2])) {
                    // Key
                    return self::BRIGHT_CYAN . $matches[1] . self::RESET . $matches[2];
                } elseif (!empty($matches[3])) {
                    // String value
                    return self::BRIGHT_GREEN . $matches[3] . self::RESET;
                } elseif (!empty($matches[4])) {
                    // Number
                    return self::BRIGHT_YELLOW . $matches[4] . self::RESET;
                } elseif (!empty($matches[5])) {
                    // Bool/Null
                    return self::BRIGHT_MAGENTA . $matches[5] . self::RESET;
                }
                return $matches[0];
            },
            $json
        );

        $this->panel($json, 'JSON Data');
    }

    public function statusCard(string $title, array $stats, string $type = 'info'): void
    {
        $maxWidth = $this->consoleWidth - 4;
        $colors = [
            'info' => self::BRIGHT_BLUE,
            'success' => self::BRIGHT_GREEN,
            'warning' => self::BRIGHT_YELLOW,
            'error' => self::BRIGHT_RED,
        ];
        $color = $colors[$type] ?? self::BRIGHT_WHITE;

        $this->line(self::DIM . "╭" . str_repeat("─", $maxWidth) . "╮" . self::RESET);
        $this->line(self::DIM . "│ " . self::RESET . $color . self::BOLD . strtoupper($title) . self::RESET . str_repeat(" ", $maxWidth - mb_strwidth($title) - 1) . self::DIM . "│" . self::RESET);
        $this->line(self::DIM . "├" . str_repeat("─", $maxWidth) . "┤" . self::RESET);

        foreach ($stats as $key => $value) {
            $line = "  • " . self::BRIGHT_WHITE . $key . self::RESET . ": " . $value;
            $len = mb_strwidth($this->stripAnsiCodes($line));
            $this->line(self::DIM . "│ " . self::RESET . $line . str_repeat(" ", max(0, $maxWidth - $len - 1)) . self::DIM . "│" . self::RESET);
        }

        $this->line(self::DIM . "╰" . str_repeat("─", $maxWidth) . "╯" . self::RESET);
    }

    public function highlight(string $text, array $words, string $color = self::BRIGHT_YELLOW): void
    {
        $highlighted = $text;
        foreach ($words as $word) {
            $highlighted = str_replace($word, $color . $word . self::RESET, $highlighted);
        }
        $this->line($highlighted);
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
        $maxWidth = $this->consoleWidth - 4;
        $textLen = mb_strwidth($text);

        // Calculate padding for centered text
        $totalPadding = max(0, $maxWidth - $textLen);
        $leftPadding = (int) ($totalPadding / 2);
        $rightPadding = $totalPadding - $leftPadding;

        $this->line();
        $this->gradient(str_repeat("█", $maxWidth));

        $centeredText = str_repeat(" ", $leftPadding) . $text . str_repeat(" ", $rightPadding);
        $this->line(self::BOLD . self::BRIGHT_WHITE . $centeredText . self::RESET);

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
            self::BRIGHT_BLUE,
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
        if (empty($arguments)) {
            return;
        }

        $this->line(self::BRIGHT_WHITE . "  Arguments:" . self::RESET);
        foreach ($arguments as $name => $description) {
            $this->line("    " . self::BRIGHT_GREEN . str_pad($name, 20) . self::RESET . $description);
        }
        $this->line();
    }

    public function optionList(array $options): void
    {
        if (empty($options)) {
            return;
        }

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

    public function branding(string $version = '1.0.0'): void
    {
        $this->brandingLogo();
        $this->line("  " . self::DIM . "FRAMEWORK  v" . $version . self::RESET);
        $this->newLine();
    }

    public function brandingLogo(): void
    {
        $logo = [
            '  ___ _               ',
            ' / _ \ |_  _ __ _ ___',
            '|  __/ | || / _` (_-<',
            '|_|  |_|\_,_\__, /__/',
            '            |___/    ',
        ];

        $this->newLine();
        foreach ($logo as $line) {
            $this->line("  " . self::BRIGHT_CYAN . $line . self::RESET);
        }
    }

    public function advancedHeader(string $title, string $subtitle = ''): void
    {
        $this->brandingLogo();

        $maxWidth = $this->consoleWidth - 4;
        $this->line(self::BRIGHT_BLUE . "╭" . str_repeat("─", $maxWidth) . "╮" . self::RESET);

        // Gradient title
        $paddedTitle = " " . strtoupper($title) . " ";
        $titleLen = mb_strwidth($paddedTitle);
        $leftPadding = (int) (($maxWidth - $titleLen) / 2);
        $rightPadding = $maxWidth - $titleLen - $leftPadding;

        echo "  " . self::BRIGHT_BLUE . "│" . self::RESET . str_repeat(" ", $leftPadding);
        $this->gradientRaw($paddedTitle);
        echo str_repeat(" ", $rightPadding) . self::BRIGHT_BLUE . "│" . self::RESET . "\n";

        if ($subtitle) {
            $subTitleLen = mb_strwidth($subtitle);
            $leftSubPadding = (int) (($maxWidth - $subTitleLen) / 2);
            $rightSubPadding = $maxWidth - $subTitleLen - $leftSubPadding;
            $this->line(
                self::BRIGHT_BLUE . "│" . self::RESET .
                str_repeat(" ", $leftSubPadding) .
                self::DIM . $subtitle . self::RESET .
                str_repeat(" ", $rightSubPadding) .
                self::BRIGHT_BLUE . "│" . self::RESET
            );
        }

        $this->line(self::BRIGHT_BLUE . "╰" . str_repeat("─", $maxWidth) . "╯" . self::RESET);
        $this->newLine();
    }

    private function gradientRaw(string $text): void
    {
        $colors = [
            self::GRADIENT_PURPLE,
            self::GRADIENT_PINK,
            self::GRADIENT_ORANGE,
            self::BRIGHT_YELLOW,
            self::BRIGHT_GREEN,
            self::GRADIENT_TEAL,
            self::BRIGHT_CYAN,
            self::BRIGHT_BLUE,
        ];

        $chars = mb_str_split($text);
        $len = count($chars);
        $colorCount = count($colors);

        for ($i = 0; $i < $len; $i++) {
            $colorIndex = (int) (($i / max(1, $len - 1)) * ($colorCount - 1));
            echo $colors[$colorIndex] . $chars[$i];
        }
        echo self::RESET;
    }

    public function twoColumnList(array $items, int $leftWidth = 30): void
    {
        foreach ($items as $left => $right) {
            $visibleLeft = mb_strwidth($this->stripAnsiCodes($left));
            $padding = max(0, $leftWidth - $visibleLeft);

            $this->line("  " . self::BRIGHT_GREEN . $left . self::RESET . str_repeat(' ', $padding) . $right);
        }
    }
}
