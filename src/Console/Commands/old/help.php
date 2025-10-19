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


class help extends Command
{
    protected string $description = 'Display help information and list all available commands';

    public function handle(): int
    {
        $kernel = new ConsoleKernel();
        $specificCommand = $this->argument('0');

        if ($specificCommand && $kernel->has($specificCommand)) {
            return $this->displayCommandHelp($kernel, $specificCommand);
        }

        $this->displayBanner();
        $this->displayUsage();
        $this->displayCommands($kernel);
        $this->displayAliases($kernel);
        $this->displayGlobalOptions();
        $this->displayExamples($kernel);
        $this->displayFooter();

        return 0;
    }

    private function displayBanner(): void
    {
        $this->line();
        $this->banner('✨ THE PLUGS CONSOLE ✨');
        $this->line();
        
        $this->box(
            "A powerful command-line interface for your PHP framework.\n" .
            "Build, manage, and deploy with style and speed.",
            "Welcome",
            "info"
        );
    }

    private function displayUsage(): void
    {
        $this->section('Usage');
        
        $this->info('  php theplugs <command> [arguments] [options]');
        $this->line();
        $this->note('Arguments and options are command-specific');
    }

    private function displayCommands(ConsoleKernel $kernel): void
    {
        $this->section('Available Commands');
        
        $grouped = $this->groupCommands($kernel->commands());
        
        foreach ($grouped as $category => $commands) {
            if ($category !== 'general') {
                $this->line();
                $this->gradient("  ▶ " . strtoupper($category));
                $this->line();
            }
            
            $rows = [];
            foreach ($commands as $name => $class) {
                try {
                    $commandInstance = new $class($name);
                    $desc = $commandInstance->description() ?: 'No description available';
                    $rows[] = ["  {$name}", $desc];
                } catch (\Throwable $e) {
                    $rows[] = ["  {$name}", "Error loading command"];
                }
            }
            
            if (!empty($rows)) {
                $this->table(['Command', 'Description'], $rows);
            }
        }
    }

    private function groupCommands(array $commands): array
    {
        $grouped = ['general' => []];
        
        foreach ($commands as $name => $class) {
            if (str_contains($name, ':')) {
                $category = explode(':', $name)[0];
                $grouped[$category][$name] = $class;
            } else {
                $grouped['general'][$name] = $class;
            }
        }
        
        ksort($grouped);
        
        return $grouped;
    }

    private function displayAliases(ConsoleKernel $kernel): void
    {
        $aliases = $kernel->aliases();
        
        if (empty($aliases)) {
            return;
        }

        $this->section('Command Aliases');
        $this->note('Shortcuts for commonly used commands');
        $this->line();
        
        $rows = [];
        foreach ($aliases as $alias => $command) {
            $rows[] = ["  {$alias}", "→  {$command}"];
        }
        
        $this->table(['Alias', 'Command'], $rows);
    }

    private function displayGlobalOptions(): void
    {
        $this->section('Global Options');
        
        $options = [
            ['--help, -h', 'Display help information for any command'],
            ['--debug', 'Enable debug mode with detailed error output'],
            ['--verbose, -v', 'Increase output verbosity'],
            ['--quiet, -q', 'Suppress all output except errors'],
            ['--no-interaction', 'Run without any interactive prompts'],
        ];
        
        $this->table(['Option', 'Description'], $options);
    }

    private function displayExamples(ConsoleKernel $kernel): void
    {
        $this->section('Examples');
        
        $examples = [
            'Display this help screen' => 'php theplugs help',
            'Get help for specific command' => 'php theplugs help <command>',
            'Run command with debug info' => 'php theplugs <command> --debug',
        ];
        
        $commands = $kernel->commands();
        if (isset($commands['make:controller'])) {
            $examples['Create a new controller'] = 'php theplugs make:controller UserController';
        }
        if (isset($commands['make:model'])) {
            $examples['Generate a model'] = 'php theplugs make:model User';
        }
        
        foreach ($examples as $description => $command) {
            $this->line();
            $this->info("  {$description}:");
            $this->line("    \033[90m{$command}\033[0m");
        }
        
        $this->line();
    }

    private function displayFooter(): void
    {
        $this->line();
        $this->gradient("━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━");
        $this->line();
        
        $tips = [
            "💡 Use --help with any command for detailed information",
            "⚡ Commands support tab completion in most terminals",
            "🎨 Customize colors by setting TERM environment variable",
            "📚 Visit documentation for advanced usage patterns"
        ];
        
        $this->note($tips[array_rand($tips)]);
        $this->line();
    }

    private function displayCommandHelp(ConsoleKernel $kernel, string $commandName): int
    {
        $command = $kernel->resolve($commandName);
        
        if (!$command) {
            $this->error("Command '{$commandName}' not found.");
            return 1;
        }

        $this->line();
        $this->header("Help: {$commandName}");
        
        $description = $command->description();
        if ($description) {
            $this->box($description, 'Description', 'info');
            $this->line();
        }

        $this->section('Usage');
        $this->info("  php theplugs {$commandName} [arguments] [options]");
        $this->line();

        if (method_exists($command, 'getArguments')) {
            $this->section('Arguments');
            foreach ($command->getArguments() as $arg => $desc) {
                $this->info("  {$arg}");
                $this->line("    {$desc}");
            }
            $this->line();
        }

        if (method_exists($command, 'getOptions')) {
            $this->section('Options');
            foreach ($command->getOptions() as $opt => $desc) {
                $this->info("  {$opt}");
                $this->line("    {$desc}");
            }
            $this->line();
        }

        return 0;
    }
}