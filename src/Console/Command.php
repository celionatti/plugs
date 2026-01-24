<?php

declare(strict_types=1);

namespace Plugs\Console;

/*
|--------------------------------------------------------------------------
| Command Base Class
|--------------------------------------------------------------------------
| Enhanced with timing utilities and better helper methods
*/

use Plugs\Console\Contract\CommandInterface;
use Plugs\Console\Support\Input;
use Plugs\Console\Support\Output;

abstract class Command implements CommandInterface
{
    protected string $name;
    protected string $description = '';
    protected Input $input;
    protected Output $output;

    private float $startTime;
    private array $checkpoints = [];

    public function __construct(string $name)
    {
        $this->name = $name;
        $this->startTime = microtime(true);
    }

    public function name(): string
    {
        return $this->name;
    }

    public function description(): string
    {
        return $this->description;
    }

    public function setIO(Input $input, Output $output): void
    {
        $this->input = $input;
        $this->output = $output;
    }

    // ==================== INPUT HELPERS ====================

    protected function argument(string $key, ?string $default = null): ?string
    {
        return $this->input->arguments[$key] ?? $default;
    }

    protected function option(string $key, string|int|bool|null $default = null): string|int|bool|null
    {
        return $this->input->options[$key] ?? $default;
    }

    protected function hasOption(string $key): bool
    {
        return isset($this->input->options[$key]);
    }

    protected function hasArgument(string $key): bool
    {
        return isset($this->input->arguments[$key]);
    }

    protected function arguments(): array
    {
        return $this->input->arguments;
    }

    protected function options(): array
    {
        return $this->input->options;
    }

    // ==================== COMMAND DEFINITION ====================

    public function getArguments(): array
    {
        return $this->defineArguments();
    }

    public function getOptions(): array
    {
        return $this->defineOptions();
    }

    protected function defineArguments(): array
    {
        return [];
    }

    protected function defineOptions(): array
    {
        return [];
    }

    // ==================== INTERACTIVE INPUT ====================

    protected function ask(string $question, ?string $default = null): string
    {
        return $this->output->ask($question, $default);
    }

    protected function secret(string $question): string
    {
        return $this->output->secret($question);
    }

    protected function confirm(string $question, bool $default = false): bool
    {
        return $this->output->confirm($question, $default);
    }

    protected function choice(string $question, array $choices, $default = null): string
    {
        return $this->output->choice($question, $choices, $default);
    }

    protected function multiChoice(string $question, array $choices, array $defaults = []): array
    {
        return $this->output->multiChoice($question, $choices, $defaults);
    }

    protected function anticipate(string $question, array $suggestions, ?string $default = null): string
    {
        return $this->output->anticipate($question, $suggestions, $default);
    }

    // ==================== OUTPUT METHODS ====================

    protected function line(string $text = ''): void
    {
        $this->output->line($text);
    }

    protected function info(string $text): void
    {
        $this->output->info($text);
    }

    protected function success(string $text): void
    {
        $this->output->success($text);
    }

    protected function warning(string $text): void
    {
        $this->output->warning($text);
    }

    protected function error(string $text): void
    {
        $this->output->error($text);
    }

    protected function note(string $text): void
    {
        $this->output->note($text);
    }

    protected function critical(string $text): void
    {
        $this->output->critical($text);
    }

    protected function debug(string $text): void
    {
        $this->output->debug($text);
    }

    protected function header(string $text): void
    {
        $this->output->header($text);
    }

    protected function section(string $title): void
    {
        $this->output->section($title);
    }

    protected function table(array $headers, array $rows): void
    {
        $this->output->table($headers, $rows);
    }

    protected function box(string $content, string $title = '', string $type = 'info'): void
    {
        $this->output->box($content, $title, $type);
    }

    protected function alert(string $message, string $type = 'info'): void
    {
        $this->output->alert($message, $type);
    }

    protected function panel(string $content, string $title = ''): void
    {
        $this->output->panel($content, $title);
    }

    protected function title(string $text): void
    {
        $this->output->title($text);
    }

    protected function banner(string $text): void
    {
        $this->output->banner($text);
    }

    protected function gradient(string $text): void
    {
        $this->output->gradient($text);
    }

    protected function quote(string $text, string $author = ''): void
    {
        $this->output->quote($text, $author);
    }

    protected function divider(string $char = '─'): void
    {
        $this->output->divider($char);
    }

    protected function newLine(int $count = 1): void
    {
        $this->output->newLine($count);
    }

    protected function clear(): void
    {
        $this->output->clear();
    }

    // ==================== LISTS ====================

    protected function bulletList(array $items, string $bullet = '•'): void
    {
        $this->output->bulletList($items, $bullet);
    }

    protected function numberedList(array $items): void
    {
        $this->output->numberedList($items);
    }

    protected function tree(array $items, int $level = 0): void
    {
        $this->output->tree($items, $level);
    }

    protected function keyValue(string $key, string $value, int $padding = 20): void
    {
        $this->output->keyValue($key, $value, $padding);
    }

    protected function diff(string $old, string $new): void
    {
        $this->output->diff($old, $new);
    }

    // ==================== PROGRESS ====================

    protected function task(string $message, callable $callback): mixed
    {
        $result = null;
        $error = null;
        $completed = false;

        $this->output->spinner($message, function () use ($callback, &$result, &$error, &$completed) {
            if (!$completed) {
                try {
                    $result = $callback();
                    $completed = true;
                } catch (\Throwable $e) {
                    $error = $e;
                    $completed = true;
                }
            }

            return $completed;
        });

        if ($error) {
            throw $error;
        }

        return $result;
    }

    protected function loading(string $message, callable $callback): mixed
    {
        return $this->output->loading($message, $callback);
    }

    protected function withProgressBar(int $max, callable $step, string $label = 'Progress'): void
    {
        $this->output->progressBar($max, $step, $label);
    }

    protected function step(int $current, int $total, string $message): void
    {
        $this->output->step($current, $total, $message);
    }

    protected function countdown(int|string $seconds, string $message = 'Starting in'): void
    {
        $this->output->countdown($seconds, $message);
    }

    // ==================== FILE OPERATIONS ====================

    protected function fileExists(string $path): bool
    {
        return file_exists($path);
    }

    protected function readFile(string $path): string
    {
        if (!$this->fileExists($path)) {
            throw new \RuntimeException("File not found: {$path}");
        }

        return file_get_contents($path);
    }

    protected function writeFile(string $path, string $content): void
    {
        $dir = dirname($path);
        if (!is_dir($dir)) {
            mkdir($dir, 0755, true);
        }
        file_put_contents($path, $content);
    }

    protected function validatePath(string $path): bool
    {
        if (!$this->fileExists($path)) {
            $this->error("Path does not exist: {$path}");

            return false;
        }

        return true;
    }

    protected function validateDirectory(string $path): bool
    {
        if (!is_dir($path)) {
            $this->error("Directory does not exist: {$path}");

            return false;
        }

        return true;
    }

    protected function ensureDirectory(string $path): bool
    {
        if (!is_dir($path)) {
            return mkdir($path, 0755, true);
        }

        return true;
    }

    protected function copyFile(string $source, string $destination): bool
    {
        if (!$this->fileExists($source)) {
            $this->error("Source file not found: {$source}");

            return false;
        }

        $this->ensureDirectory(dirname($destination));

        return copy($source, $destination);
    }

    // ==================== SHELL COMMANDS ====================

    protected function exec(string $command, array &$output = [], int &$exitCode = 0): string
    {
        exec($command, $output, $exitCode);

        return implode("\n", $output);
    }

    protected function execRealtime(string $command): int
    {
        $process = popen($command, 'r');

        if (!$process) {
            $this->error("Failed to execute command: {$command}");

            return 1;
        }

        while (!feof($process)) {
            $line = fgets($process);
            if ($line !== false) {
                echo $line;
            }
        }

        return pclose($process);
    }

    protected function call(string $command, array $arguments = []): int
    {
        $cmd = "php theplugs {$command}";

        foreach ($arguments as $key => $value) {
            if (is_int($key)) {
                $cmd .= " {$value}";
            } else {
                $cmd .= " --{$key}={$value}";
            }
        }

        if ($this->isVerbose()) {
            $this->info("Calling: {$cmd}");
        }

        return $this->execRealtime($cmd);
    }

    // ==================== TIMING & CHECKPOINTS ====================

    protected function checkpoint(string $name): void
    {
        $this->checkpoints[$name] = microtime(true);
    }

    protected function getCheckpointTime(string $name): ?float
    {
        if (!isset($this->checkpoints[$name])) {
            return null;
        }

        return microtime(true) - $this->checkpoints[$name];
    }

    protected function displayTimings(): void
    {
        if (empty($this->checkpoints)) {
            return;
        }

        $this->line();
        $this->section('Execution Timings');
        $this->line();

        $previous = $this->startTime;
        foreach ($this->checkpoints as $name => $time) {
            $elapsed = $time - $previous;
            $total = $time - $this->startTime;

            $this->line(sprintf(
                "  %s %s (+%s total: %s)",
                "\033[36m▶\033[0m",
                str_pad($name, 30),
                $this->formatTime($elapsed),
                $this->formatTime($total)
            ));

            $previous = $time;
        }

        $this->line();
    }

    protected function getExecutionTime(): float
    {
        return microtime(true) - $this->startTime;
    }

    protected function elapsed(): float
    {
        return microtime(true) - $this->startTime;
    }

    // ==================== UTILITIES ====================

    protected function abort(string $message, int $exitCode = 1): never
    {
        $this->error($message);
        exit($exitCode);
    }

    protected function isVerbose(): bool
    {
        return $this->hasOption('verbose') || $this->hasOption('v');
    }

    protected function isQuiet(): bool
    {
        return $this->hasOption('quiet') || $this->hasOption('q');
    }

    protected function isProduction(): bool
    {
        return ($this->option('env') ?? getenv('APP_ENV')) === 'production';
    }

    protected function isForce(): bool
    {
        return $this->hasOption('force') || $this->hasOption('f');
    }

    protected function sleep(int $seconds, ?string $message = null): void
    {
        if ($message) {
            $this->info($message);
        }
        sleep($seconds);
    }

    protected function formatTime(float $seconds): string
    {
        if ($seconds < 0.001) {
            return number_format($seconds * 1000000, 0) . 'μs';
        } elseif ($seconds < 1) {
            return number_format($seconds * 1000, 0) . 'ms';
        }

        return number_format($seconds, 2) . 's';
    }

    protected function truncate(string $text, int $length = 50): string
    {
        if (mb_strlen($text) <= $length) {
            return $text;
        }

        return mb_substr($text, 0, $length - 3) . '...';
    }

    protected function formatNumber(int|float $number, int $decimals = 0): string
    {
        return number_format($number, $decimals, '.', ',');
    }
}
