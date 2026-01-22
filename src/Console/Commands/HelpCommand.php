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
use Plugs\Console\Support\Str;

class HelpCommand extends Command
{
    protected string $description = 'Display help information and list all available commands';

    protected function defineArguments(): array
    {
        return [
            'command_name' => 'The command name to show help for'
        ];
    }

    protected function defineOptions(): array
    {
        return [
            '--format=FORMAT' => 'The output format (text, json)',
            '--raw' => 'To output raw command help'
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
        $this->line();

        $this->output->section('Global Options');
        $globalOptions = [
            '--help, -h' => 'Display help for a command',
            '--quiet, -q' => 'Suppress all output',
            '--version, -V' => 'Display framework version',
            '--verbose, -v' => 'Increase output verbosity',
        ];
        $this->output->twoColumnList($globalOptions, 20);
        $this->line();

        $this->output->section('Available Commands');

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
            $this->line();
            $this->line("  \033[1;33m" . $category . "\033[0m");

            $items = [];
            foreach ($categoryCommands as $name => $commandClass) {
                try {
                    $command = new $commandClass($name);
                    $description = $command->description();
                } catch (\Throwable $e) {
                    $description = '';
                }

                if (strlen($description) > 45) {
                    $description = substr($description, 0, 42) . '...';
                }
                $items[$name] = $description;
            }
            $this->output->twoColumnList($items, 28);
        }

        $this->line();
        $this->line("  \033[2mRun 'php theplugs help <command>' for more information.\033[0m");
        $this->line();

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

        $this->header($commandName);

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
                $this->line("  " . "\033[32m" . str_pad($argument, 20) . "\033[0m" . $description);
            }
        }

        // Options
        $options = $command->getOptions();
        if (!empty($options)) {
            $this->output->section('OPTIONS');
            foreach ($options as $option => $description) {
                $this->line("  " . "\033[32m" . str_pad($option, 25) . "\033[0m" . $description);
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
                $this->line("  " . "\033[90m# " . $description . "\033[0m");
                $this->line("  php theplugs {$commandName} {$example}");
                $this->line();
            }
        }

        $this->line();

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
