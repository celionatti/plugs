<?php

declare(strict_types=1);

namespace Plugs\Console;

use Plugs\Console\Command;
use Plugs\Console\Commands\DemoCommand;
use Plugs\Console\Commands\HelpCommand;
use Plugs\Console\Commands\ServeCommand;
use Plugs\Console\Commands\InspireCommand;
use Plugs\Console\Commands\MakeModelCommand;
use Plugs\Console\Commands\RouteListCommand;
use Plugs\Console\Commands\RouteTestCommand;
use Plugs\Console\Commands\CacheClearCommand;
use Plugs\Console\Commands\RouteCacheCommand;
use Plugs\Console\Commands\RouteClearCommand;
use Plugs\Console\Commands\MakeCommandCommand;
use Plugs\Console\Commands\MakeMigrationCommand;
use Plugs\Console\Commands\MakeControllerCommand;
use Plugs\Console\Commands\MakeMiddlewareCommand;

/*
|--------------------------------------------------------------------------
| ConsoleKernel Class
|--------------------------------------------------------------------------
| Command registry and resolver
*/

class ConsoleKernel
{
    protected array $commands = [
        'help'              => HelpCommand::class,
        'demo'              => DemoCommand::class,
        'inspire'           => InspireCommand::class,

        'make:controller'   => MakeControllerCommand::class,
        'make:model'        => MakeModelCommand::class,
        'make:command'      => MakeCommandCommand::class,
        'make:middleware'   => MakeMiddlewareCommand::class,
        'make:migration'    => MakeMigrationCommand::class,

        'route:list'   => RouteListCommand::class,
        'route:cache'  => RouteCacheCommand::class,
        'route:clear'  => RouteClearCommand::class,
        'route:test'   => RouteTestCommand::class,

        'serve'             => ServeCommand::class,
        'cache:clear'       => CacheClearCommand::class,
    ];

    protected array $aliases = [
        'g:c'      => 'make:controller',
        'g:m'      => 'make:model',
        'g:cmd'    => 'make:command',
        'g:mid'    => 'make:middleware',
        'g:mig'    => 'make:migration',
        'routes'       => 'route:list',
        'route:show'   => 'route:list',
        's'        => 'serve',
        'cc'       => 'cache:clear',
        'i'        => 'inspire',
    ];

    protected array $commandGroups = [
        'General' => ['help', 'demo', 'inspire'],
        'Make' => ['make:controller', 'make:model', 'make:command', 'make:middleware', 'make:migration'],
        'Utility' => ['serve', 'cache:clear'],
    ];

    public function commands(): array
    {
        return $this->commands;
    }

    public function aliases(): array
    {
        return $this->aliases;
    }

    public function commandGroups(): array
    {
        return $this->commandGroups;
    }

    public function register(string $name, string $class): void
    {
        if (!class_exists($class)) {
            throw new \InvalidArgumentException("Command class '{$class}' does not exist.");
        }

        if (!is_subclass_of($class, Command::class)) {
            throw new \InvalidArgumentException("Command class must extend " . Command::class);
        }

        $this->commands[$name] = $class;
    }

    public function registerBatch(array $commands): void
    {
        foreach ($commands as $name => $class) {
            $this->register($name, $class);
        }
    }

    public function alias(string $alias, string $commandName): void
    {
        if (!isset($this->commands[$commandName])) {
            throw new \InvalidArgumentException("Cannot alias non-existent command '{$commandName}'");
        }

        $this->aliases[$alias] = $commandName;
    }

    public function resolve(string $name): ?Command
    {
        $lookup = $this->aliases[$name] ?? $name;
        $class = $this->commands[$lookup] ?? null;

        if (!$class || !class_exists($class)) {
            return null;
        }

        return new $class($lookup);
    }

    public function has(string $name): bool
    {
        $lookup = $this->aliases[$name] ?? $name;
        return isset($this->commands[$lookup]);
    }

    public function getCommandList(): array
    {
        return array_keys($this->commands);
    }

    public function getGroupedCommands(): array
    {
        $grouped = [];

        foreach ($this->commandGroups as $group => $commandNames) {
            $grouped[$group] = [];
            foreach ($commandNames as $name) {
                if (isset($this->commands[$name])) {
                    $grouped[$group][$name] = $this->commands[$name];
                }
            }
        }

        $allGrouped = array_merge(...array_values($this->commandGroups));
        foreach ($this->commands as $name => $class) {
            if (!in_array($name, $allGrouped)) {
                $grouped['Other'][$name] = $class;
            }
        }

        return $grouped;
    }

    public function findByPattern(string $pattern): array
    {
        $matches = [];
        foreach (array_keys($this->commands) as $name) {
            if (fnmatch($pattern, $name)) {
                $matches[] = $name;
            }
        }
        return $matches;
    }
}
