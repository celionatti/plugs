<?php

declare(strict_types=1);

namespace Plugs\Console;

/*
|--------------------------------------------------------------------------
| ConsolePlugs Class
|--------------------------------------------------------------------------
| Enhanced with timing, better error handling, and improved output
*/

use Plugs\Console\Support\ArgvParser;
use Plugs\Console\Support\Output;
use Throwable;

class ConsolePlugs
{
    private array $metrics = [];

    public function __construct(private ConsoleKernel $kernel)
    {
        $this->loadFunctions();
    }

    public function run(array $argv): int
    {
        $this->metrics['start'] = microtime(true);
        $this->metrics['memory_start'] = memory_get_usage(true);

        $parser = new ArgvParser($argv);
        $name = $parser->commandName() ?? 'help';
        $input = $parser->input();
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
        $output->branding(\Plugs\Plugs::version());
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
        $memoryPeak = memory_get_peak_usage(true);

        $output->commandFooter($totalTime, $memoryPeak);
    }

    private function displayError(Output $output, Throwable $e, $input): void
    {
        $output->line();

        $errorContent = $e->getMessage() . "\n\n";
        $errorContent .= Output::MUTED . "File: " . Output::RESET . $e->getFile() . "\n";
        $errorContent .= Output::MUTED . "Line: " . Output::RESET . $e->getLine();

        $output->box($errorContent, Output::EMBER . "✖ Command Failed" . Output::RESET, 'error');

        if ($input->options['debug'] ?? $input->options['verbose'] ?? false) {
            $output->section('Stack Trace');

            foreach ($e->getTrace() as $index => $trace) {
                $file = $trace['file'] ?? 'unknown';
                $line = $trace['line'] ?? 0;
                /** @phpstan-ignore nullCoalesce.offset */
                $function = $trace['function'] ?? 'unknown';
                $class = $trace['class'] ?? '';
                $type = $trace['type'] ?? '';

                $output->line("  " . Output::MUTED . "#{$index}" . Output::RESET . " {$class}{$type}{$function}()");
                $output->line("      " . Output::MUTED . "{$file}:{$line}" . Output::RESET);
            }

            $output->line();
        } else {
            $output->note("Run with --debug or --verbose for detailed stack trace");
        }

        $this->consultAiForFix($output, $e, $input);

        $output->line();
    }

    private function consultAiForFix(Output $output, Throwable $e, $input): void
    {
        // Don't consult AI if we are already in an AI command or if no driver is configured
        if (str_starts_with($input->commandName() ?? '', 'ai:') || !isset($GLOBALS['app'])) {
            return;
        }

        try {
            $ai = $GLOBALS['app']->make(\Plugs\AI\AIManager::class);
            if (!$ai->getDefaultDriver()) {
                return;
            }
        } catch (Throwable $err) {
            return;
        }

        if ($output->confirm("\n  " . Output::GOLD . "💡 Would you like me to consult the AI for a potential fix?" . Output::RESET, true)) {
            $output->newLine();
            $output->spinner("Consulting AI expert...", function () use ($output, $e, $ai) {
                $prompt = <<<PROMPT
You are an expert debugger for the Plugs PHP Framework.
A command failed with the following error:

Error: {$e->getMessage()}
File: {$e->getFile()}
Line: {$e->getLine()}

Stack Trace snippet:
{$this->getCondensedTrace($e)}

Please provide:
1. A concise explanation of why this happened.
2. A specific code fix or CLI command to solve it.
3. Keep it brief and actionable.
PROMPT;

                try {
                    $suggestion = $ai->prompt($prompt);
                    $output->newLine();
                    $output->box($suggestion, "🤖 AI Suggested Fix", "warning");
                    return true;
                } catch (Throwable $err) {
                    $output->error("AI Consultation failed: " . $err->getMessage());
                    return true;
                }
            });
        }
    }

    private function getCondensedTrace(Throwable $e): string
    {
        $trace = '';
        foreach (array_slice($e->getTrace(), 0, 5) as $index => $item) {
            $file = $item['file'] ?? 'unknown';
            $line = $item['line'] ?? 0;
            $class = $item['class'] ?? '';
            $function = $item['function'] ?? 'unknown';
            $trace .= "#{$index} {$file}({$line}): {$class}{$function}\n";
        }
        return $trace;
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
        $functionsDir = dirname(__DIR__) . '/functions/';

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
