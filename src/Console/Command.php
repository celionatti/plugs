<?php

declare(strict_types=1);

namespace Plugs\Console;

/*
|--------------------------------------------------------------------------
| Command Base Class
|--------------------------------------------------------------------------
| Enhanced with timing utilities and better helper methods
*/

use Plugs\Console\Support\Input;
use Plugs\Console\Support\Output;
use Plugs\Console\Contract\CommandInterface;


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
        $this->input  = $input;
        $this->output = $output;
    }

    // INPUT HELPERS
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

    protected function allArguments(): array
    {
        return $this->input->arguments;
    }

    protected function allOptions(): array
    {
        return $this->input->options;
    }

    // INTERACTIVE INPUT
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
        return $this->output->askConfirmation($question, $default);
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

    // OUTPUT METHODS
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

    protected function critical(string $text): void
    {
        $this->output->critical($text);
    }

    protected function note(string $text): void
    {
        $this->output->note($text);
    }

    protected function debug(string $text): void
    {
        if ($this->hasOption('debug')) {
            $this->output->debug($text);
        }
    }

    protected function header(string $text): void
    {
        $this->output->header($text);
    }

    protected function section(string $title): void
    {
        $this->output->line();
        $this->output->subHeader($title);
    }

    protected function table(array $headers, array $rows): void
    {
        $this->output->table($headers, $rows);
    }

    protected function box(string $content, string $title = '', string $type = 'info'): void
    {
        $this->output->box($content, $title, $type);
    }

    protected function banner(string $text): void
    {
        $this->output->banner($text);
    }

    protected function gradient(string $text): void
    {
        $this->output->gradient($text);
    }

    // PROGRESS & TIMING
    protected function task(string $message, callable $callback): mixed
    {
        $result = null;
        $error = null;
        $completed = false;
        
        $this->output->spinner($message, function() use ($callback, &$result, &$error, &$completed) {
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

    protected function withProgressBar(int $max, callable $step, string $label = 'Progress'): void
    {
        $this->output->progressBar($max, $step, $label);
    }

    protected function countdown(int $seconds, string $message = 'Starting in'): void
    {
        $this->output->countdown($seconds, $message);
    }

    protected function step(int $current, int $total, string $message): void
    {
        $percent = round(($current / $total) * 100);
        $this->output->info("[{$current}/{$total}] ({$percent}%) {$message}");
    }

    // TIMING UTILITIES
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

    protected function getExecutionTime(): float
    {
        return microtime(true) - $this->startTime;
    }

    protected function formatTime(float $seconds): string
    {
        if ($seconds < 0.001) {
            return number_format($seconds * 1000000, 2) . 'μs';
        } elseif ($seconds < 1) {
            return number_format($seconds * 1000, 2) . 'ms';
        } else {
            return number_format($seconds, 3) . 's';
        }
    }

    protected function displayTimings(): void
    {
        if (empty($this->checkpoints)) {
            return;
        }

        $this->section('Execution Timings');
        
        $rows = [];
        foreach ($this->checkpoints as $name => $time) {
            $duration = microtime(true) - $time;
            $rows[] = [$name, $this->formatTime($duration)];
        }

        $this->table(['Checkpoint', 'Duration'], $rows);
    }

    // FILE OPERATIONS
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

    // VALIDATION
    protected function validateRequired(array $required): void
    {
        $missing = [];
        
        foreach ($required as $arg) {
            if (!$this->hasArgument($arg)) {
                $missing[] = $arg;
            }
        }

        if (!empty($missing)) {
            throw new \InvalidArgumentException(
                "Missing required arguments: " . implode(', ', $missing)
            );
        }
    }

    // UTILITIES
    protected function abort(string $message, int $exitCode = 1)
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

    protected function newLine(int $count = 1): void
    {
        for ($i = 0; $i < $count; $i++) {
            $this->line();
        }
    }
}
