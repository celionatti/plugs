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
        $this->header('ThePlugs Console');
        $this->line();
        $this->info('Usage:');
        $this->line('  command [options] [arguments]');
        $this->line();

        $this->info('Options:');
        $globalOptions = [
            '--help'           => 'Display help for the command',
            '--quiet'          => 'Do not output any message',
            '--version'        => 'Display this application version',
            '--ansi'           => 'Force ANSI output',
            '--no-ansi'        => 'Disable ANSI output',
            '--no-interaction' => 'Do not ask any interactive question',
            '--verbose'        => 'Increase the verbosity of messages'
        ];

        foreach ($globalOptions as $option => $description) {
            $this->line("  " . str_pad($option, 20) . $description);
        }

        $this->line();

        // Display commands in Laravel-style grouped table
        $this->output->commandTable($kernel->commands());

        $this->line();
        $this->note('To see help for a specific command, use: php theplugs help <command>');

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
            $this->line();
            $this->box($description, 'Description', 'info');
        }

        // Usage
        $this->line();
        $this->output->sectionTitle('Usage:');
        $this->line("  php theplugs {$commandName} [options] [arguments]");

        // Arguments
        $arguments = $command->getArguments();
        if (!empty($arguments)) {
            $this->line();
            $this->output->sectionTitle('Arguments:');
            foreach ($arguments as $argument => $description) {
                $this->line("  " . \Plugs\Console\Support\Str::studly($argument));
                $this->line("    " . $description);
                $this->line();
            }
        }

        // Options
        $options = $command->getOptions();
        if (!empty($options)) {
            $this->output->sectionTitle('Options:');
            foreach ($options as $option => $description) {
                $this->line("  " . str_pad($option, 25) . $description);
            }
        }

        // Examples
        $examples = method_exists($command, 'getExamples') ? $command->getExamples() : [];
        if (empty($examples) && method_exists($command, 'getUsageExamples')) {
            $examples = $command->getUsageExamples();
        }

        if (!empty($examples)) {
            $this->line();
            $this->output->sectionTitle('Examples:');
            foreach ($examples as $example => $description) {
                $this->line("  # " . $description);
                $this->line("  php theplugs {$commandName} {$example}");
                $this->line();
            }
        }

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
                $this->line("  • {$suggestion}");
            }
        }
    }
}
