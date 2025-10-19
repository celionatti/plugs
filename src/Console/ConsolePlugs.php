<?php

declare(strict_types=1);

namespace Plugs\Console;

/*
|--------------------------------------------------------------------------
| ConsolePlugs Class
|--------------------------------------------------------------------------
| Enhanced with timing, better error handling, and improved output
*/

use Throwable;
use Plugs\Console\Support\Output;
use Plugs\Console\Support\ArgvParser;


class ConsolePlugs
{
    private array $metrics = [];
    private array $checkpoints = [];

    public function __construct(private ConsoleKernel $kernel) 
    {
        $this->loadFunctions();
    }

    public function run(array $argv): int
    {
        $this->metrics['start'] = microtime(true);
        $this->metrics['memory_start'] = memory_get_usage(true);
        
        $parser = new ArgvParser($argv);
        $name   = $parser->commandName() ?? 'help';
        $input  = $parser->input();
        $output = new Output();

        if ($this->shouldShowVersion($input)) {
            $this->displayVersion($output);
            return 0;
        }

        if ($this->shouldShowHelp($input) && $name !== 'help') {
            $name = 'help';
        }

        try {
            $command = $this->kernel->resolve($name);
            
            if ($command === null) {
                $this->displayCommandNotFound($output, $name);
                return 1;
            }

            if (!$this->isQuiet($input)) {
                $this->displayCommandHeader($output, $name);
            }

            $command->setIO($input, $output);
            $exitCode = (int) $command->handle();

            if ($exitCode === 0 && !in_array($name, ['help', 'demo']) && !$this->isQuiet($input)) {
                $this->displaySuccessMetrics($output);
            }

            return $exitCode;
            
        } catch (Throwable $e) {
            $this->displayError($output, $e, $input);
            return 1;
        }
    }

    private function shouldShowVersion($input): bool
    {
        return $input->options['version'] ?? false;
    }

    private function shouldShowHelp($input): bool
    {
        return $input->options['help'] ?? $input->options['h'] ?? false;
    }

    private function isQuiet($input): bool
    {
        return $input->options['quiet'] ?? $input->options['q'] ?? false;
    }

    private function displayVersion(Output $output): void
    {
        $output->line();
        $output->gradient("╔══════════════════════════════════════════╗");
        $output->line();
        $output->line("   ThePlugs Console Framework v1.0.0");
        $output->line("   PHP " . PHP_VERSION);
        $output->line();
        $output->gradient("╚══════════════════════════════════════════╝");
        $output->line();
    }

    private function displayCommandHeader(Output $output, string $name): void
    {
        $output->commandHeader($name);
    }

    private function displayCommandNotFound(Output $output, string $name): void
    {
        $output->line();
        $output->error("Command \"{$name}\" is not defined.");
        $output->line();
        
        $similar = $this->getSimilarCommands($name);
        if (!empty($similar)) {
            $output->box(
                "Did you mean one of these?\n\n" . 
                implode("\n", array_map(fn($cmd) => "  • {$cmd}", $similar)),
                "💡 Suggestions",
                "warning"
            );
        }
        
        $output->note("Run 'php theplugs help' to see all available commands");
        $output->line();
    }

    private function getSimilarCommands(string $name): array
    {
        $commands = array_keys($this->kernel->commands());
        $similar = [];
        
        foreach ($commands as $cmd) {
            $similarity = 0;
            similar_text($name, $cmd, $similarity);
            if ($similarity > 60) {
                $similar[] = $cmd;
            }
        }
        
        return array_slice($similar, 0, 5);
    }

    private function displaySuccessMetrics(Output $output): void
    {
        $this->metrics['end'] = microtime(true);
        $this->metrics['memory_end'] = memory_get_usage(true);
        
        $totalTime = $this->metrics['end'] - $this->metrics['start'];
        $memoryUsed = $this->metrics['memory_end'] - $this->metrics['memory_start'];
        $memoryPeak = memory_get_peak_usage(true);
        
        $output->line();
        $output->fullWidthLine('─', "\033[90m");
        
        $timeFormatted = $this->formatTime($totalTime);
        $memoryFormatted = $this->formatMemory($memoryUsed);
        $peakFormatted = $this->formatMemory($memoryPeak);
        
        $output->line("  \033[92m✓\033[0m Command completed successfully");
        $output->line("  \033[36m⏱\033[0m  Execution time: \033[1m{$timeFormatted}\033[0m");
        $output->line("  \033[35m📊\033[0m Memory used: \033[1m{$memoryFormatted}\033[0m");
        $output->line("  \033[33m📈\033[0m Peak memory: \033[1m{$peakFormatted}\033[0m");
        
        $output->fullWidthLine('─', "\033[90m");
        $output->line();
    }

    private function displayError(Output $output, Throwable $e, $input): void
    {
        $output->line();
        
        $errorContent = "Message: {$e->getMessage()}\n\n";
        $errorContent .= "File: {$e->getFile()}\n";
        $errorContent .= "Line: {$e->getLine()}";
        
        $output->box($errorContent, "❌ Command Failed", "error");
        
        if ($input->options['debug'] ?? $input->options['verbose'] ?? false) {
            $output->line();
            $output->section('Stack Trace');
            $output->line();
            
            foreach ($e->getTrace() as $index => $trace) {
                $file = $trace['file'] ?? 'unknown';
                $line = $trace['line'] ?? 0;
                $function = $trace['function'] ?? 'unknown';
                $class = $trace['class'] ?? '';
                $type = $trace['type'] ?? '';
                
                $output->line("  \033[2m#{$index}\033[0m {$class}{$type}{$function}()");
                $output->line("      \033[2m{$file}:{$line}\033[0m");
            }
            
            $output->line();
        } else {
            $output->note("Run with --debug or --verbose for detailed stack trace");
        }
        
        $output->line();
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

    private function formatMemory(int $bytes): string
    {
        $units = ['B', 'KB', 'MB', 'GB'];
        $bytes = max($bytes, 0);
        $pow = floor(($bytes ? log($bytes) : 0) / log(1024));
        $pow = min($pow, count($units) - 1);
        $bytes /= (1 << (10 * $pow));
        
        return round($bytes, 2) . ' ' . $units[$pow];
    }

    public function loadFunctions(): void
    {
        $functionsDir = __DIR__ . '/functions/';

        if (!is_dir($functionsDir)) {
            return;
        }

        $files = glob($functionsDir . '*.php');

        foreach ($files as $file) {
            if (file_exists($file) && is_file($file)) {
                require_once $file;
            }
        }
    }
}
