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
        $this->output->branding('1.0.0');

        $this->output->section('Usage');
        $this->line('  php theplugs <command> [options] [arguments]');

        $this->output->section('Global Options');
        $globalOptions = [
            '--help, -h' => 'Display help for a command',
            '--quiet, -q' => 'Suppress all output',
            '--version, -V' => 'Display framework version',
            '--verbose, -v' => 'Increase output verbosity',
        ];
        $this->output->twoColumnList($globalOptions, 20);

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
            $this->newLine();
            $this->line("  " . \Plugs\Console\Support\Output::BOLD . \Plugs\Console\Support\Output::YELLOW . strtoupper($category) . \Plugs\Console\Support\Output::RESET);

            $items = [];
            foreach ($categoryCommands as $name => $commandClass) {
                try {
                    $command = new $commandClass($name);
                    $description = $command->description();
                } catch (\Throwable $e) {
                    $description = '';
                }

                if (mb_strlen($description) > 60) {
                    $description = mb_substr($description, 0, 57) . '...';
                }
                $items[$name] = $description;
            }
            $this->output->twoColumnList($items, 28);
        }

        $this->newLine();
        $this->line("  " . \Plugs\Console\Support\Output::DIM . "Run 'php theplugs help <command>' for more information." . \Plugs\Console\Support\Output::RESET);
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
        }

        // Usage
        $this->output->section('USAGE');
        $this->line("  php theplugs {$commandName} [options] [arguments]");

        // Arguments
        $arguments = $command->getArguments();
        if (!empty($arguments)) {
            $this->output->section('ARGUMENTS');
            foreach ($arguments as $argument => $description) {
                $this->line("  " . \Plugs\Console\Support\Output::BRIGHT_GREEN . str_pad($argument, 20) . \Plugs\Console\Support\Output::RESET . " " . $description);
            }
        }

        // Options
        $options = $command->getOptions();
        if (!empty($options)) {
            $this->output->section('OPTIONS');
            foreach ($options as $option => $description) {
                $this->line("  " . \Plugs\Console\Support\Output::BRIGHT_GREEN . str_pad($option, 25) . \Plugs\Console\Support\Output::RESET . " " . $description);
            }
        }

        // Examples
        $examples = method_exists($command, 'getExamples') ? $command->getExamples() : [];
        if (empty($examples) && method_exists($command, 'getUsageExamples')) {
            $examples = $command->getUsageExamples();
        }

        if (!empty($examples)) {
            $this->output->section('EXAMPLES');
            foreach ($examples as $example => $description) {
                $this->line("  " . \Plugs\Console\Support\Output::DIM . "# " . $description . \Plugs\Console\Support\Output::RESET);
                $this->line("  php theplugs {$commandName} {$example}");
                $this->line();
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
