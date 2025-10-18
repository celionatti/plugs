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
    
    public function __construct(private ConsoleKernel $kernel) {}

    public function run(array $argv): int
    {
        $this->metrics['start'] = microtime(true);
        $this->metrics['memory_start'] = memory_get_usage(true);
        
        $parser = new ArgvParser($argv);
        $name   = $parser->commandName() ?? 'help';
        $input  = $parser->input();
        $output = new Output();

        try {
            if ($name !== 'help') {
                $this->displayCommandHeader($output, $name);
            }

            $this->metrics['resolve_start'] = microtime(true);
            $command = $this->kernel->resolve($name);
            $this->metrics['resolve_time'] = microtime(true) - $this->metrics['resolve_start'];

            if ($command === null) {
                $this->displayCommandNotFound($output, $name);
                return 1;
            }

            $command->setIO($input, $output);

            $this->metrics['execution_start'] = microtime(true);
            $exitCode = (int) $command->handle();
            $this->metrics['execution_time'] = microtime(true) - $this->metrics['execution_start'];

            if ($name !== 'help') {
                $this->displaySuccessMetrics($output);
            }

            return $exitCode;

        } catch (Throwable $e) {
            $this->displayError($output, $e, $input);
            return 1;
        }
    }

    private function displayCommandHeader(Output $output, string $name): void
    {
        $output->line();
        $output->gradient("▓▓▓▓▓▓▓▓▓▓▓▓▓▓▓▓▓▓▓▓▓▓▓▓▓▓▓▓▓▓▓▓▓▓▓▓▓▓▓▓▓▓▓▓▓▓▓▓▓▓▓▓▓▓▓▓▓▓");
        $output->line();
        $output->info("⚡ Command: " . strtoupper($name));
        $output->info("⏰ Started: " . date('Y-m-d H:i:s'));
        $output->line();
        $output->gradient("▓▓▓▓▓▓▓▓▓▓▓▓▓▓▓▓▓▓▓▓▓▓▓▓▓▓▓▓▓▓▓▓▓▓▓▓▓▓▓▓▓▓▓▓▓▓▓▓▓▓▓▓▓▓▓▓▓▓");
        $output->line();
    }

    private function displayCommandNotFound(Output $output, string $name): void
    {
        $output->line();
        $output->box(
            "Command '{$name}' was not found.\n\n" .
            "Did you mean one of these?\n" .
            $this->getSuggestions($name),
            "❌ Command Not Found",
            "error"
        );
        $output->line();
        $output->info("💡 Tip: Run 'help' to see all available commands");
        $output->line();
    }

    private function getSuggestions(string $name): string
    {
        $commands = array_keys($this->kernel->commands());
        $suggestions = [];
        
        foreach ($commands as $cmd) {
            $similarity = 0;
            similar_text($name, $cmd, $similarity);
            if ($similarity > 50) {
                $suggestions[] = "  • {$cmd}";
            }
        }

        return !empty($suggestions) 
            ? implode("\n", array_slice($suggestions, 0, 5))
            : "  No similar commands found";
    }

    private function displaySuccessMetrics(Output $output): void
    {
        $this->metrics['end'] = microtime(true);
        $this->metrics['memory_end'] = memory_get_usage(true);
        
        $totalTime = $this->metrics['end'] - $this->metrics['start'];
        $executionTime = $this->metrics['execution_time'];
        $resolveTime = $this->metrics['resolve_time'];
        $memoryUsed = $this->metrics['memory_end'] - $this->metrics['memory_start'];

        $output->line();
        $output->gradient("━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━");
        $output->line();
        
        $output->box(
            $this->buildMetricsContent($totalTime, $executionTime, $resolveTime, $memoryUsed),
            "✨ Performance Metrics",
            "success"
        );
        
        $output->gradient("━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━");
        $output->line();
    }

    private function buildMetricsContent(
        float $totalTime, 
        float $executionTime, 
        float $resolveTime, 
        int $memoryUsed
    ): string {
        $content = [];
        
        $content[] = "⏱️  Total Time:      " . $this->formatTime($totalTime);
        $content[] = "⚡ Execution Time:  " . $this->formatTime($executionTime);
        $content[] = "🔍 Resolution Time: " . $this->formatTime($resolveTime);
        
        $overhead = $totalTime - $executionTime;
        $content[] = "📊 Framework Time:  " . $this->formatTime($overhead);
        
        $content[] = "💾 Memory Used:     " . $this->formatBytes($memoryUsed);
        
        $peakMemory = memory_get_peak_usage(true);
        $content[] = "📈 Peak Memory:     " . $this->formatBytes($peakMemory);
        
        return implode("\n", $content);
    }

    private function displayError(Output $output, Throwable $e, $input): void
    {
        $output->line();
        $output->gradient("▓▓▓▓▓▓▓▓▓▓▓▓▓▓▓▓▓▓▓▓▓▓▓▓▓▓▓▓▓▓▓▓▓▓▓▓▓▓▓▓▓▓▓▓▓▓▓▓▓▓▓▓▓▓▓▓▓▓");
        $output->line();
        
        $output->critical("COMMAND EXECUTION FAILED");
        
        $output->line();
        $output->box(
            "Type:    " . get_class($e) . "\n" .
            "Message: " . $e->getMessage() . "\n" .
            "File:    " . $e->getFile() . "\n" .
            "Line:    " . $e->getLine(),
            "💥 Error Details",
            "error"
        );

        if ($input->options['debug'] ?? false) {
            $output->line();
            $output->subHeader("Stack Trace");
            $output->box($this->formatStackTrace($e), '', 'error');
            
            $output->line();
            $output->subHeader("System Information");
            $output->info("PHP Version: " . PHP_VERSION);
            $output->info("OS: " . PHP_OS);
            $output->info("Memory: " . $this->formatBytes(memory_get_usage(true)));
        } else {
            $output->line();
            $output->note("Run with --debug flag for detailed stack trace");
        }

        $output->line();
        $output->gradient("▓▓▓▓▓▓▓▓▓▓▓▓▓▓▓▓▓▓▓▓▓▓▓▓▓▓▓▓▓▓▓▓▓▓▓▓▓▓▓▓▓▓▓▓▓▓▓▓▓▓▓▓▓▓▓▓▓▓");
        $output->line();
    }

    private function formatStackTrace(Throwable $e): string
    {
        $trace = $e->getTraceAsString();
        $lines = explode("\n", $trace);
        $formatted = [];
        
        foreach (array_slice($lines, 0, 10) as $i => $line) {
            $formatted[] = sprintf("%2d) %s", $i + 1, trim($line));
        }
        
        if (count($lines) > 10) {
            $formatted[] = "... (" . (count($lines) - 10) . " more frames)";
        }
        
        return implode("\n", $formatted);
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
        $power = $bytes > 0 ? floor(log($bytes, 1024)) : 0;
        return number_format($bytes / pow(1024, $power), 2) . ' ' . $units[$power];
    }
}