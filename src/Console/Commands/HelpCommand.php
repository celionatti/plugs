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
use Plugs\Console\Support\Output;

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
            '--search=QUERY' => 'Search for commands matching query',
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

        $this->output->sideBySide($usage, $globalOptions, 'Usage', 'Global Options');

        $commands = $kernel->commands();
        $search = $this->option('search');

        if ($search) {
            $commands = array_filter($commands, fn($class, $name) => str_contains(strtolower($name), strtolower($search)), ARRAY_FILTER_USE_BOTH);
            if (empty($commands)) {
                $this->output->warning("No commands found matching '{$search}'.");
                return 0;
            }
        }

        $commandGroups = $kernel->commandGroups();
        $aliases = $kernel->aliases();

        // Create a fast lookup for aliases: command_name => array of aliases
        $aliasMap = [];
        foreach ($aliases as $alias => $cmd) {
            $aliasMap[$cmd][] = $alias;
        }

        $grouped = [];
        $allGroupedCmds = [];

        foreach ($commandGroups as $groupName => $cmds) {
            foreach ($cmds as $cmdName) {
                if (isset($commands[$cmdName])) {
                    $grouped[$groupName][$cmdName] = $commands[$cmdName];
                    $allGroupedCmds[] = $cmdName;
                }
            }
        }

        // Add ungrouped commands to 'Other'
        foreach ($commands as $name => $class) {
            if (!in_array($name, $allGroupedCmds) && !isset($aliases[$name])) {
                $grouped['Other'][$name] = $class;
            }
        }

        // Add icons for common groups
        $icons = [
            'General' => '◈',
            'Make' => '⟐',
            'Database' => '●',
            'Routes' => '✦',
            'Utility' => '⚙',
            'Scheduling' => '⏱',
        ];

        foreach ($grouped as $category => $categoryCommands) {
            $icon = $icons[$category] ?? '◈';
            $this->output->line(Output::ACCENT . $icon . " " . Output::BRIGHT_WHITE . Output::BOLD . $category . Output::RESET);
            
            foreach ($categoryCommands as $name => $commandClass) {
                try {
                    $command = new $commandClass($name);
                    $description = $command->description();
                } catch (\Throwable $e) {
                    $description = 'No description available';
                }

                $cmdNameDisplay = Output::MINT . $name . Output::RESET;
                
                // Show aliases if any
                if (isset($aliasMap[$name])) {
                    $cmdNameDisplay .= Output::MUTED . " [" . implode(', ', $aliasMap[$name]) . "]" . Output::RESET;
                }
                
                // Strip tags for length calculation
                $visibleNameLen = mb_strwidth($this->output->stripAnsiCodes($cmdNameDisplay));
                
                if (mb_strlen($description) > 70) {
                    $description = mb_substr($description, 0, 67) . '...';
                }
                
                $padding = max(1, 35 - $visibleNameLen);
                $this->output->line("  " . $cmdNameDisplay . str_repeat(' ', $padding) . Output::SUBTLE . $description . Output::RESET);
            }
            $this->output->newLine();
        }

        $this->output->note("Run 'php theplugs help <command>' for more information.");
        $this->output->newLine();

        return 0;
    }

    private function displayCommandHelp(ConsoleKernel $kernel, string $commandName): int
    {
        $command = $kernel->resolve($commandName);

        if (!$command) {
            $this->output->error("Command '{$commandName}' not found.");
            $this->showCommandSuggestions($kernel, $commandName);
            return 1;
        }

        $this->output->title($commandName);

        // Description
        if ($description = $command->description()) {
            $this->output->line("  " . Output::MINT . "ℹ" . Output::RESET . " " . $description);
            $this->output->newLine();
        }

        // Usage
        $this->output->line("  " . Output::MUTED . "Usage:" . Output::RESET);
        $this->output->line("  " . Output::BRIGHT_WHITE . "php theplugs " . Output::ACCENT . "{$commandName}" . Output::RESET . " [options] [arguments]");
        $this->output->newLine();

        // Arguments & Options
        $arguments = $command->getArguments();
        $options = $command->getOptions();

        if (!empty($arguments)) {
            $this->output->section('Arguments');
            $this->output->twoColumnList($arguments, 20);
        }

        if (!empty($options)) {
            $this->output->section('Options');
            $this->output->twoColumnList($options, 25);
        }

        // Examples
        $examples = $command->getExamples();
        if (empty($examples)) {
            $examples = $command->getUsageExamples();
        }

        if (!empty($examples)) {
            $this->output->section('Examples');
            foreach ($examples as $example => $description) {
                $this->output->line("  " . Output::MUTED . "● " . $description . Output::RESET);
                $this->output->line("    " . Output::BRIGHT_WHITE . "php theplugs {$commandName} " . Output::SKY . $example . Output::RESET);
                $this->output->newLine();
            }
        }

        $this->output->newLine();

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
            $this->output->box(
                "Did you mean one of these?\n\n" . 
                implode("\n", array_map(fn($c) => "  • " . Output::BRIGHT_WHITE . $c . Output::RESET, array_slice($suggestions, 0, 5))),
                "💡 Suggestions",
                "warning"
            );
        }
    }
}
