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
    
    // Modern gradient colors (256-color mode)
    private const GRADIENT_PURPLE = "\033[38;5;135m";
    private const GRADIENT_PINK = "\033[38;5;211m";
    private const GRADIENT_ORANGE = "\033[38;5;208m";
    private const GRADIENT_TEAL = "\033[38;5;80m";

    public function header(string $text): void
    {
        $textWithEmojis = "🚀 $text 🚀";
        $displayWidth = mb_strwidth($textWithEmojis);
        $width = max(60, $displayWidth + 10);
        $padding = ($width - $displayWidth) / 2;
        
        echo "\n";
        echo self::GRADIENT_PURPLE . self::BOLD . "╔" . str_repeat("═", $width - 2) . "╗" . self::RESET . "\n";
        echo self::GRADIENT_PURPLE . self::BOLD . "║" . str_repeat(" ", (int)floor($padding)) . "🚀 " . self::BRIGHT_WHITE . self::BOLD . $text . self::GRADIENT_PURPLE . " 🚀" . str_repeat(" ", (int)ceil($padding)) . "║" . self::RESET . "\n";
        echo self::GRADIENT_PURPLE . self::BOLD . "╚" . str_repeat("═", $width - 2) . "╝" . self::RESET . "\n\n";
    }

    public function subHeader(string $text): void
    {
        echo "\n" . self::GRADIENT_TEAL . self::BOLD . "▶ " . $text . self::RESET . "\n";
        echo self::DIM . str_repeat("─", mb_strwidth($text) + 2) . self::RESET . "\n";
    }

    public function line(string $text = ''): void
    {
        echo $text . PHP_EOL;
    }

    public function info(string $text): void
    {
        $this->line(self::BRIGHT_BLUE . "ℹ " . self::RESET . $text);
    }

    public function success(string $text): void
    {
        $this->line(self::BRIGHT_GREEN . "✓ " . self::RESET . $text);
    }

    public function warning(string $text): void
    {
        $this->line(self::BRIGHT_YELLOW . "⚠ " . self::RESET . $text);
    }

    public function error(string $text): void
    {
        $this->line(self::BRIGHT_RED . "✗ " . self::RESET . $text);
    }

    public function critical(string $text): void
    {
        $this->line(self::BG_RED . self::BRIGHT_WHITE . self::BOLD . " CRITICAL " . self::RESET . " " . self::BRIGHT_RED . $text . self::RESET);
    }

    public function note(string $text): void
    {
        $this->line(self::GRADIENT_PINK . "📝 Note: " . self::RESET . self::DIM . $text . self::RESET);
    }

    public function debug(string $text): void
    {
        $this->line(self::DIM . "🐛 Debug: " . $text . self::RESET);
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
        
        $maxContentWidth = 0;
        foreach ($lines as $line) {
            $maxContentWidth = max($maxContentWidth, mb_strwidth($this->stripAnsiCodes($line)));
        }
        
        $titleWidth = mb_strwidth($title);
        $contentWidth = max($maxContentWidth, $titleWidth);
        $boxWidth = $contentWidth + 4;

        $colors = [
            'info' => self::BRIGHT_BLUE,
            'success' => self::BRIGHT_GREEN,
            'warning' => self::BRIGHT_YELLOW,
            'error' => self::BRIGHT_RED,
            'note' => self::GRADIENT_PINK
        ];

        $color = $colors[$type] ?? self::BRIGHT_BLUE;

        echo $color . "╭" . str_repeat("─", $boxWidth - 2) . "╮" . self::RESET . "\n";
        
        if ($title) {
            $titlePadding = ($boxWidth - $titleWidth - 4) / 2;
            echo $color . "│" . self::RESET . str_repeat(" ", (int)floor($titlePadding)) . self::BOLD . $title . self::RESET . str_repeat(" ", (int)ceil($titlePadding)) . $color . "│" . self::RESET . "\n";
            echo $color . "├" . str_repeat("─", $boxWidth - 2) . "┤" . self::RESET . "\n";
        }
        
        foreach ($lines as $line) {
            $cleanLine = $this->stripAnsiCodes($line);
            $lineWidth = mb_strwidth($cleanLine);
            $padding = $contentWidth - $lineWidth;
            echo $color . "│" . self::RESET . " " . $line . str_repeat(" ", $padding + 1) . $color . "│" . self::RESET . "\n";
        }
        
        echo $color . "╰" . str_repeat("─", $boxWidth - 2) . "╯" . self::RESET . "\n\n";
    }

    private function stripAnsiCodes(string $text): string
    {
        return preg_replace('/\033\[[0-9;]*m/', '', $text);
    }

    public function countdown(int $seconds, string $message = 'Starting in'): void
    {
        for ($i = $seconds; $i > 0; $i--) {
            echo "\r" . self::BRIGHT_YELLOW . $message . " " . self::BOLD . $i . self::RESET . "s...";
            sleep(1);
        }
        echo "\r" . self::BRIGHT_GREEN . "🚀 Let's go!" . self::RESET . str_repeat(" ", 20) . "\n";
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

    public function banner(string $text): void
    {
        echo "\n";
        $bannerChars = str_repeat("█", mb_strwidth($text) + 10);
        $this->gradient($bannerChars);
        echo self::BOLD . self::BRIGHT_WHITE . str_repeat(" ", 5) . $text . str_repeat(" ", 5) . self::RESET . "\n";
        $this->gradient($bannerChars);
        echo "\n";
    }

    // ========================================================================
    // INTERACTIVE INPUT METHODS
    // ========================================================================

    /**
     * Ask a question and get user input
     */
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

    /**
     * Ask for password input (hidden)
     */
    public function secret(string $question): string
    {
        echo self::BRIGHT_CYAN . "🔒 " . self::RESET . $question . ": ";
        
        if (strtoupper(substr(PHP_OS, 0, 3)) === 'WIN') {
            // Windows
            $password = '';
            while (true) {
                $char = fgetc(STDIN);
                if ($char === "\n" || $char === "\r") {
                    break;
                }
                $password .= $char;
            }
        } else {
            // Unix-like systems
            system('stty -echo');
            $handle = fopen("php://stdin", "r");
            $password = trim(fgets($handle));
            fclose($handle);
            system('stty echo');
        }
        
        echo "\n";
        return $password;
    }

    /**
     * Ask a yes/no confirmation question
     */
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

    /**
     * Present a choice menu and get selection
     */
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

    /**
     * Multiple choice selection (checkboxes)
     */
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

    /**
     * Autocomplete suggestion input
     */
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
}