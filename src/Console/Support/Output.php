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
                return max(80, min((int)$width, 200));
            }
        }

        // Method 3: Try mode con (Windows)
        if (strtoupper(substr(PHP_OS, 0, 3)) === 'WIN') {
            $output = [];
            @exec('mode con', $output);
            foreach ($output as $line) {
                if (preg_match('/Columns:\s*(\d+)/i', $line, $matches)) {
                    return max(80, min((int)$matches[1], 200));
                }
            }
        }

        // Method 4: Default fallback
        return 120; // Safe default width
    }

    public function fullWidthLine(string $char = '─', string $color = self::BRIGHT_BLACK): void
    {
        $this->line($color . str_repeat($char, $this->consoleWidth) . self::RESET);
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
        // Display the logo
        $this->thePlugsLogo();

        $this->line();
        $this->fullWidthLine('- ', self::BRIGHT_BLUE);
        $this->line();
        $this->line(self::BRIGHT_BLUE . "  Command: " . self::BRIGHT_WHITE . $command . self::RESET);
        $this->line(self::BRIGHT_BLUE . "  Started: " . self::DIM . date('Y-m-d H:i:s') . self::RESET);
        $this->line();
        $this->fullWidthLine('- ', self::BRIGHT_BLUE);
        $this->line();
    }

    // public function commandHeader(string $command): void
    // {
    //     // Display the logo
    //     $this->thePlugsLogo();

    //     // Display command info
    //     // $this->line(self::BRIGHT_BLUE . "  Command: " . self::BRIGHT_WHITE . self::BOLD . $command . self::RESET);
    //     // $this->line(self::BRIGHT_BLUE . "  Started: " . self::DIM . date('Y-m-d H:i:s') . self::RESET);
    //     // $this->line();
    //     $this->fullWidthLine('─', self::BRIGHT_BLACK);
    //     $this->line();
    // }

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
        echo $text . PHP_EOL;
    }

    public function info(string $text): void
    {
        $this->line(self::BRIGHT_BLUE . "  INFO: " . self::RESET . $text);
    }

    public function success(string $text): void
    {
        $this->line(self::BRIGHT_GREEN . "  SUCCESS: " . self::RESET . $text);
    }

    public function warning(string $text): void
    {
        $this->line(self::BRIGHT_YELLOW . "  WARNING: " . self::RESET . $text);
    }

    public function error(string $text): void
    {
        $this->line(self::BRIGHT_RED . "  ERROR: " . self::RESET . $text);
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
        $padding = str_repeat(" ", (int)(($width - mb_strwidth($text)) / 2));

        $this->line();
        $this->line(self::BRIGHT_BLUE . str_repeat(" ", $width) . self::RESET);
        $this->line(self::BRIGHT_BLUE . $padding . self::BRIGHT_WHITE . self::BOLD . $text . self::BRIGHT_BLUE . $padding . self::RESET);
        $this->line(self::BRIGHT_BLUE . str_repeat(" ", $width) . self::RESET);
        $this->line();
    }

    public function section(string $title): void
    {
        $this->line();
        $this->line(self::BRIGHT_CYAN . "▶ " . self::BOLD . $title . self::RESET);
        $this->line(self::DIM . str_repeat("─", mb_strwidth($title) + 2) . self::RESET);
    }

    public function spinner(string $message, int|callable $secondsOrCallback = 2): void
    {
        $frames = ['⠋', '⠙', '⠹', '⠸', '⠼', '⠴', '⠦', '⠧', '⠇', '⠏'];
        $i = 0;

        if (is_callable($secondsOrCallback)) {
            while (!$secondsOrCallback()) {
                echo "\r" . self::BRIGHT_CYAN . $frames[$i++ % count($frames)] . self::RESET . " $message";
                usleep(120000);

                if ($i > 500) {
                    $this->warning("Spinner timeout reached");
                    break;
                }
            }
        } else {
            $end = time() + $secondsOrCallback;
            while (time() < $end) {
                echo "\r" . self::BRIGHT_CYAN . $frames[$i++ % count($frames)] . self::RESET . " $message";
                usleep(120000);
            }
        }

        echo "\r" . self::BRIGHT_GREEN . "✔" . self::RESET . " $message" . str_repeat(" ", 10) . "\n";
    }

    public function progressBar(int $max, callable $step, string $label = 'Progress'): void
    {
        echo self::BOLD . $label . ":" . self::RESET . "\n";

        for ($i = 1; $i <= $max; $i++) {
            $step($i);
            $percent = (int)(($i / $max) * 100);
            $filled = (int)($percent / 2.5);
            $empty = 40 - $filled;

            $bar = str_repeat("█", $filled) . str_repeat("░", $empty);
            $color = $percent < 33 ? self::BRIGHT_RED : ($percent < 66 ? self::BRIGHT_YELLOW : self::BRIGHT_GREEN);

            echo "\r" . $color . "▐" . $bar . "▌" . self::RESET . " " . self::BOLD . $percent . "%" . self::RESET . " ($i/$max)";
            usleep(50000);
        }
        echo "\n" . self::BRIGHT_GREEN . "✅ Completed!" . self::RESET . "\n\n";
    }

    public function progress(int $current, int $total, string $message = ''): void
    {
        $percentage = ($current / $total) * 100;
        $barWidth = 50;
        $filled = (int) ($barWidth * ($current / $total));
        $bar = str_repeat('█', $filled) . str_repeat('░', $barWidth - $filled);

        $output = sprintf(
            "\r  %s %3d%% [%s] %s",
            self::BRIGHT_CYAN . 'Progress:' . self::RESET,
            $percentage,
            $bar,
            $message
        );

        echo $output;
        if ($current === $total) {
            echo PHP_EOL;
        }
    }

    public function table(array $headers, array $rows): void
    {
        if (empty($headers) || empty($rows)) {
            $this->warning("No data to display in table");
            return;
        }

        $cols = count($headers);
        $widths = array_map(static fn($h) => mb_strwidth((string)$h), $headers);

        foreach ($rows as $row) {
            for ($i = 0; $i < $cols; $i++) {
                $widths[$i] = max($widths[$i], mb_strwidth((string)($row[$i] ?? '')));
            }
        }

        $pad = fn($s, $w) => str_pad((string)$s, $w + (strlen($s) - mb_strwidth($s)));

        echo self::BRIGHT_CYAN . "╭" . implode("┬", array_map(fn($w) => str_repeat("─", $w + 2), $widths)) . "╮" . self::RESET . "\n";

        echo self::BRIGHT_CYAN . "│" . self::RESET;
        foreach ($headers as $i => $header) {
            echo " " . self::BOLD . self::BRIGHT_WHITE . $pad($header, $widths[$i]) . self::RESET . " " . self::BRIGHT_CYAN . "│" . self::RESET;
        }
        echo "\n";

        echo self::BRIGHT_CYAN . "├" . implode("┼", array_map(fn($w) => str_repeat("─", $w + 2), $widths)) . "┤" . self::RESET . "\n";

        foreach ($rows as $rowIndex => $row) {
            $rowColor = $rowIndex % 2 === 0 ? self::RESET : self::DIM;
            echo self::BRIGHT_CYAN . "│" . self::RESET;

            for ($i = 0; $i < $cols; $i++) {
                $cellValue = $row[$i] ?? '';
                echo " " . $rowColor . $pad($cellValue, $widths[$i]) . self::RESET . " " . self::BRIGHT_CYAN . "│" . self::RESET;
            }
            echo "\n";
        }

        echo self::BRIGHT_CYAN . "╰" . implode("┴", array_map(fn($w) => str_repeat("─", $w + 2), $widths)) . "╯" . self::RESET . "\n\n";
    }

    public function box(string $content, string $title = '', string $type = 'info'): void
    {
        $lines = explode("\n", $content);
        $maxWidth = $this->consoleWidth - 6;

        $colors = [
            'info' => self::BRIGHT_BLUE,
            'success' => self::BRIGHT_GREEN,
            'warning' => self::BRIGHT_YELLOW,
            'error' => self::BRIGHT_RED,
        ];

        $color = $colors[$type] ?? self::BRIGHT_BLUE;
        $border = $color . "╭" . str_repeat("─", $maxWidth) . "╮" . self::RESET;

        $this->line($border);

        if ($title) {
            $titleLine = $color . "│" . self::RESET . " " . self::BOLD . $title . self::RESET . str_repeat(" ", $maxWidth - mb_strwidth($title) - 1) . $color . "│" . self::RESET;
            $this->line($titleLine);
            $this->line($color . "├" . str_repeat("─", $maxWidth) . "┤" . self::RESET);
        }

        foreach ($lines as $line) {
            $cleanLine = $this->stripAnsiCodes($line);
            $padding = $maxWidth - mb_strwidth($cleanLine) - 1;
            $this->line($color . "│" . self::RESET . " " . $line . str_repeat(" ", max(0, $padding)) . $color . "│" . self::RESET);
        }

        $this->line($color . "╰" . str_repeat("─", $maxWidth) . "╯" . self::RESET);
        $this->line();
    }

    public function panel(string $content, string $title = ''): void
    {
        $lines = explode("\n", $content);
        $maxWidth = max(array_map('mb_strwidth', $lines));
        $maxWidth = max($maxWidth, mb_strwidth($title)) + 4;

        $this->line();
        if ($title) {
            $titlePadding = str_repeat("─", (int)(($maxWidth - mb_strwidth($title) - 2) / 2));
            $this->line(self::BRIGHT_BLUE . "┌{$titlePadding} {$title} {$titlePadding}┐" . self::RESET);
        } else {
            $this->line(self::BRIGHT_BLUE . "┌" . str_repeat("─", $maxWidth) . "┐" . self::RESET);
        }

        foreach ($lines as $line) {
            $padding = $maxWidth - mb_strwidth($line);
            $this->line(self::BRIGHT_BLUE . "│" . self::RESET . " {$line}" . str_repeat(" ", $padding - 1) . self::BRIGHT_BLUE . "│" . self::RESET);
        }

        $this->line(self::BRIGHT_BLUE . "└" . str_repeat("─", $maxWidth) . "┘" . self::RESET);
        $this->line();
    }

    public function title(string $text): void
    {
        $this->line();
        $this->gradient("╔" . str_repeat("═", mb_strwidth($text) + 4) . "╗");
        $this->line(self::BRIGHT_WHITE . self::BOLD . "║  {$text}  ║" . self::RESET);
        $this->gradient("╚" . str_repeat("═", mb_strwidth($text) + 4) . "╝");
        $this->line();
    }

    public function banner(string $text): void
    {
        echo "\n";
        $bannerChars = str_repeat("█", mb_strwidth($text) + 10);
        $this->gradient($bannerChars);
        echo self::BOLD . self::BRIGHT_WHITE . str_repeat(" ", 5) . $text . str_repeat(" ", 5) . self::RESET . "\n";
        $this->gradient($bannerChars);
        echo "\n";
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
            $colorIndex = (int)(($i / max(1, $len - 1)) * ($colorCount - 1));
            echo $colors[$colorIndex] . $chars[$i];
        }
        echo self::RESET . "\n";
    }

    public function countdown(string $seconds, string $message = 'Starting in'): void
    {
        for ($i = $seconds; $i > 0; $i--) {
            echo "\r" . self::BRIGHT_YELLOW . $message . " " . self::BOLD . $i . self::RESET . "s...";
            sleep(1);
        }
        echo "\r" . self::BRIGHT_GREEN . "🚀 Let's go!" . self::RESET . str_repeat(" ", 20) . "\n";
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
        $percentage = ($current / $total) * 100;
        $color = $percentage < 33 ? self::BRIGHT_RED : ($percentage < 66 ? self::BRIGHT_YELLOW : self::BRIGHT_GREEN);

        $this->line("  {$color}[{$current}/{$total}]" . self::RESET . " {$message}");
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

    public function loading(string $message, callable $callback): mixed
    {
        $frames = ['⠋', '⠙', '⠹', '⠸', '⠼', '⠴', '⠦', '⠧', '⠇', '⠏'];
        $result = null;
        $completed = false;
        $i = 0;

        $startTime = microtime(true);

        while (!$completed) {
            echo "\r" . self::BRIGHT_CYAN . $frames[$i++ % count($frames)] . self::RESET . " {$message}";

            try {
                $result = $callback();
                $completed = true;
            } catch (\Throwable $e) {
                $completed = true;
                throw $e;
            }

            usleep(100000);

            if (microtime(true) - $startTime > 30) {
                break;
            }
        }

        echo "\r" . self::BRIGHT_GREEN . "✔" . self::RESET . " {$message}" . str_repeat(" ", 10) . "\n";

        return $result;
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
        if (empty($arguments)) return;

        $this->line(self::BRIGHT_WHITE . "  Arguments:" . self::RESET);
        foreach ($arguments as $name => $description) {
            $this->line("    " . self::BRIGHT_GREEN . str_pad($name, 20) . self::RESET . $description);
        }
        $this->line();
    }

    public function optionList(array $options): void
    {
        if (empty($options)) return;

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

        $selectedIndex = (int)$input - 1;

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

    public function thePlugsLogo(): void
    {
        $logo = [
            "  ████████╗██╗  ██╗███████╗    ██████╗ ██╗     ██╗   ██╗ ██████╗ ███████╗",
            "  ╚══██╔══╝██║  ██║██╔════╝    ██╔══██╗██║     ██║   ██║██╔════╝ ██╔════╝",
            "     ██║   ███████║█████╗      ██████╔╝██║     ██║   ██║██║  ███╗███████╗",
            "     ██║   ██╔══██║██╔══╝      ██╔═══╝ ██║     ██║   ██║██║   ██║╚════██║",
            "     ██║   ██║  ██║███████╗    ██║     ███████╗╚██████╔╝╚██████╔╝███████║",
            "     ╚═╝   ╚═╝  ╚═╝╚══════╝    ╚═╝     ╚══════╝ ╚═════╝  ╚═════╝ ╚══════╝"
        ];

        $this->line();
        foreach ($logo as $line) {
            $this->gradient($line);
        }
        $this->line(self::DIM . str_repeat("─", 75) . self::RESET);
        $this->line();
    }

    public function thePlugsBinaryLogo(): void
    {
        $logo = [
            "  ████████╗ ██╗  ██╗ ███████╗    ██████╗  ██╗     ██╗   ██╗  ██████╗  ███████╗",
            "  ║1█0█1█0█║ ║█0║ ║1█║ ║0█1█0█║    ║█0█1█║  ║1█║    ║0█║  ║1█║ ║0█1█0█║  ║1█0█1█║",
            "  ║0╚══10╔═║ ║1█║ ║0█║ ║1█0═══║    ║0█╔══║  ║0█║    ║1█║  ║0█║ ║1█0═══║  ║0█1═══║",
            "  ║1   10║  ║0███1█║ ║1█0█1█║    ║1█01█║  ║1█║    ║0█║  ║1█║ ║0█║ ║1█0║ ║1█0█1█║",
            "  ║0   10║  ║1█0══1█║ ║0█1══║    ║0█═══║  ║0█║    ║1█║  ║0█║ ║1█║ ║0█║ ║1╚═══0█║",
            "  ║1   10║  ║0█║ ║1█║ ║1█0█1█║    ║1█║    ║1█0█1█║ ║0██1█0█║ ║1██0█1█║ ║0█1█0█1█║",
            "  ║0   ╚═║  ║1╚═║ ║0╚═║ ║1╚═══║    ║0╚═║    ║0╚═══║  ║1╚═══╚═║  ║0╚═══╚═║ ║1╚═══0═║"
        ];

        $this->line();
        foreach ($logo as $line) {
            // Colorize 1s and 0s differently
            $coloredLine = str_replace('1', self::BRIGHT_CYAN . '1' . self::RESET, $line);
            $coloredLine = str_replace('0', self::BRIGHT_BLUE . '0' . self::RESET, $coloredLine);
            $this->line($coloredLine);
        }
        $this->line(self::DIM . "  " . str_repeat("─", 75) . self::RESET);
        $this->line();
    }

    public function thePlugsSimpleBinaryLogo(): void
    {
        $logo = [
            "  ███████╗██╗  ██╗███████╗   ██████╗ ██╗     ██╗   ██╗ ██████╗ ███████╗",
            "  ██║0001║██║01██║██║0010║   ██║010║██║1100║██║010██║██║1001║██║1010║",
            "  ██║1110║██████║█████║     ██████║██║0011║██║101██║██║ ███║███████║",
            "  ██║0100║██╔══██║██╔══║     ██╔═══║██║1001║██║010██║██║  ██║╚═══0██║",
            "  ███████║██║  ██║███████║   ██║1101║███████║╚██████║╚██████║███████║",
            "  ╚══════╝╚═╝  ╚═╝╚══════╝   ╚═╝0110╝╚══════╝ ╚═════╝ ╚═════╝╚══════╝"
        ];

        $this->line();
        foreach ($logo as $line) {
            // Color the binary digits
            $colored = preg_replace_callback('/[01]/', function ($matches) {
                return ($matches[0] === '1' ? self::BRIGHT_GREEN : self::BRIGHT_BLUE) . $matches[0] . self::RESET;
            }, $line);
            $this->line($colored);
        }
        $this->line(self::DIM . "  " . str_repeat("─", 72) . self::RESET);
        $this->line();
    }

    public function thePlugsMatrixLogo(): void
    {
        // Matrix-style with falling 1s and 0s
        $logo = [
            "  ████████╗██╗  ██╗███████╗    ██████╗ ██╗     ██╗   ██╗ ██████╗ ███████╗",
            "  1██0█1█0█║0█1║ ║1█0║1█0█1█║    1█0█1█0║1█0║    0█1║  ║0█1║0█1█0█║ 1█0█1█0║",
            "  0║1  10║ ║0█1█0█║ ║1█0█1║     ║1█01█║ ║0█1║   ║1█0║ ║1█0║1█║ ║0█║ ║1█0█1║",
            "  1║0  10║ ║1█0══1█║ ║0█1══║     ║0█═══║ ║1█0║   ║0█1║ ║0█1║0█║ ║1█║ 0╚═══1█║",
            "  0║1  10║ ║0█1║ ║1█0║1█0█1█║    ║1█0║   1█0█1█║ 0██1█0█║ 1██0█1█║ 0█1█0█1█║",
            "  1║0  ╚═║ ║1╚═║ 0╚═║ 1╚═══║    0╚═║    1╚═══║  0╚═══╚═║ 1╚═══╚═║ 0╚═══1═║"
        ];

        $this->line();
        foreach ($logo as $line) {
            $colored = preg_replace_callback('/[01]/', function ($matches) {
                $colors = [self::BRIGHT_GREEN, self::GREEN, self::BRIGHT_CYAN, self::CYAN];
                return $colors[array_rand($colors)] . $matches[0] . self::RESET;
            }, $line);
            $this->line($colored);
        }
        $this->line(self::BRIGHT_GREEN . "  " . str_repeat("─", 75) . self::RESET);
        $this->line();
    }

    public function thePlugsCompactBinary(): void
    {
        $logo = [
            " ████████╗██╗  ██╗███████╗   ██████╗ ██╗     ██╗   ██╗ ██████╗ ███████╗",
            " 1█0█1██0║0█║01██║1█0█1█0║   0█1█0█║1█║0110║0█║101██║1█0█1█║0█1█0█1║",
            "   ██║1  0███1█0║1█0█1║     1█01█║ 0█║1001║0█║101██║1█║ 0█║1█0█1█║",
            "   ██║0  1█0══1█║0█1══║     0█═══║ 1█║0110║0█║101██║1█║ 0█║1╚═══0█║",
            "   ██║1  0█║ 1█║1█0█1█║    1█║0  1█0█1█║0██1█0█║1██0█1█║0█1█0█1█║",
            "   ╚═╝0  1╚═║0╚═║1╚═══║    0╚═║  1╚═══║ 0╚═══╚═║1╚═══╚═║0╚═══1═║"
        ];

        $this->line();
        foreach ($logo as $line) {
            $colored = str_replace('1', self::BRIGHT_CYAN . '1' . self::RESET, $line);
            $colored = str_replace('0', self::BRIGHT_BLUE . '0' . self::RESET, $colored);
            $this->line($colored);
        }
        $this->line(self::DIM . " " . str_repeat("─", 72) . self::RESET);
        $this->line();
    }
}
