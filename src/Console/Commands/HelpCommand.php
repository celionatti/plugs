<?php

declare(strict_types=1);

namespace Plugs\Console\Commands;

/*
|--------------------------------------------------------------------------
| Help Command
|--------------------------------------------------------------------------
| Displays all registered commands with descriptions
*/

use Plugs\Console\Command;
use Plugs\Console\ConsoleKernel;

class HelpCommand extends Command
{
    protected string $description = 'Display help information and list all available commands';

    protected function defineArguments(): array
    {
        return [
            'command_name' => 'The command name to show help for',
        ];
    }

    protected function defineOptions(): array
    {
        return [
            '--format=FORMAT' => 'The output format (text, json)',
            '--raw' => 'To output raw command help',
        ];
    }

    public function handle(): int
    {
        $kernel = new ConsoleKernel();
        $specificCommand = $this->argument('0');

        if ($specificCommand && $kernel->has($specificCommand)) {
            return $this->displayCommandHelp($kernel, $specificCommand);
        }

        return $this->displayCommandList($kernel);
    }

    private function displayCommandList(ConsoleKernel $kernel): int
    {
        $this->branding();

        $usage = "php theplugs <command> [options] [arguments]";
        $globalOptions = "--help, -h       Display help for a command\n" .
            "--quiet, -q       Suppress all output\n" .
            "--version, -V     Display framework version\n" .
            "--verbose, -v     Increase output verbosity";

        $this->sideBySide($usage, $globalOptions, 'Usage', 'Global Options');

        $commands = $kernel->commands();
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
            $items = [];
            foreach ($categoryCommands as $name => $commandClass) {
                try {
                    $command = new $commandClass($name);
                    $description = $command->description();
                } catch (\Throwable $e) {
                    $description = 'No description available';
                }

                if (mb_strlen($description) > 70) {
                    $description = mb_substr($description, 0, 67) . '...';
                }
                $items[$name] = $description;
            }

            $this->statusCard($category, $items, 'info');
            $this->newLine();
        }

        $this->newLine();
        $this->note("Run 'php theplugs help <command>' for more information.");
        $this->newLine();

        return 0;
    }

    private function displayCommandHelp(ConsoleKernel $kernel, string $commandName): int
    {
        $command = $kernel->resolve($commandName);

        if (!$command) {
            $this->error("Command '{$commandName}' not found.");
            $this->showCommandSuggestions($kernel, $commandName);

            return 1;
        }

        $this->output->title($commandName);

        // Description
        if ($description = $command->description()) {
            $this->line("  " . $description);
            $this->newLine();
        }

        // Usage
        $this->info("Usage: " . \Plugs\Console\Support\Output::BRIGHT_WHITE . "php theplugs {$commandName} [options] [arguments]" . \Plugs\Console\Support\Output::RESET);

        // Arguments & Options
        $arguments = $command->getArguments();
        $options = $command->getOptions();

        if (!empty($arguments)) {
            $this->statusCard('Arguments', $arguments, 'info');
            $this->newLine();
        }

        if (!empty($options)) {
            $this->statusCard('Options', $options, 'success');
            $this->newLine();
        }

        // Examples
        $examples = method_exists($command, 'getExamples') ? $command->getExamples() : [];
        if (empty($examples) && method_exists($command, 'getUsageExamples')) {
            $examples = $command->getUsageExamples();
        }

        if (!empty($examples)) {
            $this->section('Usage Examples');
            foreach ($examples as $example => $description) {
                $this->highlight("  # " . $description, [$description], \Plugs\Console\Support\Output::DIM);
                $this->line("  php theplugs {$commandName} {$example}");
                $this->newLine();
            }
        }

        $this->newLine();

        return 0;
    }

    private function showCommandSuggestions(ConsoleKernel $kernel, string $commandName): void
    {
        $allCommands = array_keys($kernel->commands());
        $suggestions = [];

        foreach ($allCommands as $cmd) {
            $similarity = 0;
            similar_text($commandName, $cmd, $similarity);
            if ($similarity > 50) {
                $suggestions[] = $cmd;
            }
        }

        if (!empty($suggestions)) {
            $this->line();
            $this->info("Did you mean one of these?");
            foreach (array_slice($suggestions, 0, 5) as $suggestion) {
                $this->line("  ➜ {$suggestion}");
            }
        }
    }
}
