<?php

declare(strict_types=1);

namespace Plugs\Console;

use Plugs\Console\Command;
use Plugs\Console\Commands\DemoCommand;
use Plugs\Console\Commands\HelpCommand;
use Plugs\Console\Commands\MakeModelCommand;
use Plugs\Console\Commands\MakeControllerCommand;

/*
|--------------------------------------------------------------------------
| ConsoleKernel Class
|--------------------------------------------------------------------------
| Command registry and resolver
*/

class ConsoleKernel
{
    /** @var array<string, string> */
    protected array $commands = [
        'help'            => HelpCommand::class,
        'demo'            => DemoCommand::class,
        // 'make:framework'  => MakeFrameworkCommand::class,
        'make:controller' => MakeControllerCommand::class,
        'make:model'      => MakeModelCommand::class,
        // 'make:migration'  => MakeMigrationCommand::class,
        // 'make:command'    => MakeCommandCommand::class,
        // 'key:generate'    => KeyGenerateCommand::class,
        // 'cache:clear'     => CacheClearCommand::class,
        // 'config:cache'    => ConfigCacheCommand::class,
        // 'route:list'      => RouteListCommand::class,
        // 'db:seed'         => DbSeedCommand::class,
    ];

    /** @var array<string, string> */
    protected array $aliases = [
        'g:c' => 'make:controller',
        'g:m' => 'make:model',
        'g:cmd' => 'make:command',
    ];

    public function commands(): array 
    { 
        return $this->commands; 
    }

    public function aliases(): array 
    { 
        return $this->aliases; 
    }

    public function register(string $name, string $class): void
    {
        if (!class_exists($class)) {
            throw new \InvalidArgumentException("Command class '{$class}' does not exist.");
        }

        if (!is_subclass_of($class, Command::class)) {
            throw new \InvalidArgumentException("Command class '{$class}' must extend " . Command::class);
        }

        $this->commands[$name] = $class;
    }

    public function alias(string $alias, string $commandName): void
    {
        if (!isset($this->commands[$commandName])) {
            throw new \InvalidArgumentException("Cannot create alias for non-existent command '{$commandName}'");
        }

        $this->aliases[$alias] = $commandName;
    }

    public function resolve(string $name): ?Command
    {
        // Check if it's an alias first
        $lookup = $this->aliases[$name] ?? $name;
        
        // Get the command class
        $class = $this->commands[$lookup] ?? null;
        
        if (!$class) {
            return null;
        }

        // Verify class exists before instantiation
        if (!class_exists($class)) {
            throw new \RuntimeException("Command class '{$class}' for '{$lookup}' does not exist.");
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
}