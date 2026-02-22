<?php

declare(strict_types=1);

namespace Plugs\Console;

use Plugs\Console\Commands\CacheClearCommand;
use Plugs\Console\Commands\DemoCommand;
use Plugs\Console\Commands\HealthCommand;
use Plugs\Console\Commands\HelpCommand;
use Plugs\Console\Commands\ExplainCommand;
use Plugs\Console\Commands\InspireCommand;
use Plugs\Console\Commands\MakeActionCommand;
use Plugs\Console\Commands\MakeApiRequestCommand;
use Plugs\Console\Commands\MakeCommandCommand;
use Plugs\Console\Commands\MakeComponentCommand;
use Plugs\Console\Commands\MakeConnectorCommand;
use Plugs\Console\Commands\MakeControllerCommand;
use Plugs\Console\Commands\MakeDTOCommand;
use Plugs\Console\Commands\MakeEnumCommand;
use Plugs\Console\Commands\MakeEventCommand;
use Plugs\Console\Commands\MakeFactoryCommand;
use Plugs\Console\Commands\MakeListenerCommand;
use Plugs\Console\Commands\MakeMiddlewareCommand;
use Plugs\Console\Commands\MakeMigrationCommand;
use Plugs\Console\Commands\MakeModelCommand;
use Plugs\Console\Commands\MakeNotificationCommand;
use Plugs\Console\Commands\MakePaginationTemplateCommand;
use Plugs\Console\Commands\MakePdfTemplateCommand;
use Plugs\Console\Commands\MakeRepositoryCommand;
use Plugs\Console\Commands\MakeRequestCommand;
use Plugs\Console\Commands\MakeResourceCommand;
use Plugs\Console\Commands\MakeSeederCommand;
use Plugs\Console\Commands\MakeServiceCommand;
use Plugs\Console\Commands\MakeSpaAssetCommand;
use Plugs\Console\Commands\MakeEditorAssetCommand;
use Plugs\Console\Commands\MakePlugsAssetsCommand;
use Plugs\Console\Commands\MigrateCommand;
use Plugs\Console\Commands\MigrateFreshCommand;
use Plugs\Console\Commands\MigrateResetCommand;
use Plugs\Console\Commands\MigrateRollbackCommand;
use Plugs\Console\Commands\MigrateStatusCommand;
use Plugs\Console\Commands\MigrateValidateCommand;
use Plugs\Console\Commands\QueueWorkCommand;
use Plugs\Console\Commands\RouteCacheCommand;
use Plugs\Console\Commands\RouteClearCommand;
use Plugs\Console\Commands\RouteListCommand;
use Plugs\Console\Commands\RouteTestCommand;
use Plugs\Console\Commands\SeedCommand;
use Plugs\Console\Commands\ServeCommand;
use Plugs\Console\Commands\StorageLinkCommand;
use Plugs\Console\Commands\TypeGenCommand;
use Plugs\Console\Commands\AIChatCommand;
use Plugs\Console\Commands\MakeAiMigrationCommand;
use Plugs\Console\Commands\MakeAiTestCommand;
use Plugs\Console\Commands\AiFixCommand;
use Plugs\Console\Commands\AiAuditCommand;
use Plugs\Console\Commands\AIAgentCommand;
use Plugs\Console\Commands\AIThinkCommand;
use Plugs\Console\Commands\DatabaseAnalyzeCommand;
use Plugs\Console\Commands\SecurityScanCommand;
use Plugs\Tenancy\Console\TenantMigrateCommand;
use Plugs\Exceptions\ConsoleException;


/*
|--------------------------------------------------------------------------
| ConsoleKernel Class
|--------------------------------------------------------------------------
| Command registry and resolver
*/

class ConsoleKernel
{
    protected array $commands = [
        'help' => HelpCommand::class,
        'demo' => DemoCommand::class,
        'inspire' => InspireCommand::class,

        'framework:explain' => ExplainCommand::class,
        'make:controller' => MakeControllerCommand::class,
        'make:model' => MakeModelCommand::class,
        'make:command' => MakeCommandCommand::class,
        'make:middleware' => MakeMiddlewareCommand::class,
        'make:provider' => \Plugs\Console\Commands\MakeProviderCommand::class,
        'make:migration' => MakeMigrationCommand::class,
        'make:request' => MakeRequestCommand::class,
        'make:service' => MakeServiceCommand::class,
        'make:action' => MakeActionCommand::class,
        'make:enum' => MakeEnumCommand::class,
        'make:dto' => MakeDTOCommand::class,
        'make:resource' => MakeResourceCommand::class,
        'make:repository' => MakeRepositoryCommand::class,
        'make:factory' => MakeFactoryCommand::class,
        'make:seeder' => MakeSeederCommand::class,
        'make:connector' => MakeConnectorCommand::class,
        'make:api-request' => MakeApiRequestCommand::class,
        'make:pdf-template' => MakePdfTemplateCommand::class,
        'make:pagination-template' => MakePaginationTemplateCommand::class,
        'make:spa-asset' => MakeSpaAssetCommand::class,
        'make:editor-asset' => MakeEditorAssetCommand::class,
        'make:plugs-assets' => MakePlugsAssetsCommand::class,
        'make:component' => MakeComponentCommand::class,
        'make:event' => MakeEventCommand::class,
        'make:listener' => MakeListenerCommand::class,
        'make:notification' => MakeNotificationCommand::class,
        'make:ai-migration' => MakeAiMigrationCommand::class,

        'db:seed' => SeedCommand::class,

        'route:list' => RouteListCommand::class,
        'route:cache' => RouteCacheCommand::class,
        'route:clear' => RouteClearCommand::class,
        'route:test' => RouteTestCommand::class,

        'serve' => ServeCommand::class,
        'cache:clear' => CacheClearCommand::class,
        'config:cache' => \Plugs\Console\Commands\ConfigCacheCommand::class,
        'config:clear' => \Plugs\Console\Commands\ConfigClearCommand::class,
        'optimize' => \Plugs\Console\Commands\OptimizeCommand::class,
        'opcache:clear' => \Plugs\Console\Commands\OpCacheClearCommand::class,
        'opcache:status' => \Plugs\Console\Commands\OpCacheStatusCommand::class,

        'migrate' => MigrateCommand::class,
        'migrate:rollback' => MigrateRollbackCommand::class,
        'migrate:status' => MigrateStatusCommand::class,
        'migrate:fresh' => MigrateFreshCommand::class,
        'migrate:validate' => MigrateValidateCommand::class,
        'migrate:reset' => MigrateResetCommand::class,
        'queue:work' => QueueWorkCommand::class,
        'health' => HealthCommand::class,
        'storage:link' => StorageLinkCommand::class,
        'up' => \Plugs\Console\Commands\UpCommand::class,
        'down' => \Plugs\Console\Commands\DownCommand::class,
        'schedule:run' => \Plugs\Console\Commands\ScheduleRunCommand::class,
        'schedule:list' => \Plugs\Console\Commands\ScheduleListCommand::class,
        'type:gen' => TypeGenCommand::class,
        'ai:chat' => AIChatCommand::class,
        'ai:fix' => AiFixCommand::class,
        'ai:audit' => AiAuditCommand::class,
        'ai:agent' => AIAgentCommand::class,
        'ai:think' => AIThinkCommand::class,
        'make:ai-test' => MakeAiTestCommand::class,
        'db:analyze' => DatabaseAnalyzeCommand::class,
        'framework:scan-security' => SecurityScanCommand::class,
        'tenant:migrate' => TenantMigrateCommand::class,
    ];


    protected array $aliases = [
        'g:c' => 'make:controller',
        'g:m' => 'make:model',
        'g:cmd' => 'make:command',
        'g:mid' => 'make:middleware',
        'g:mig' => 'make:migration',
        'g:req' => 'make:request',
        'g:srv' => 'make:service',
        'g:act' => 'make:action',
        'g:enum' => 'make:enum',
        'g:dto' => 'make:dto',
        'g:res' => 'make:resource',
        'g:repo' => 'make:repository',
        'g:con' => 'make:connector',
        'g:areq' => 'make:api-request',
        'g:fact' => 'make:factory',
        'g:seed' => 'make:seeder',
        'g:spa' => 'make:spa-asset',
        'g:editor' => 'make:editor-asset',
        'g:assets' => 'make:plugs-assets',
        'g:comp' => 'make:component',
        'g:evt' => 'make:event',
        'g:lis' => 'make:listener',
        'g:not' => 'make:notification',
        'seed' => 'db:seed',
        'routes' => 'route:list',
        'route:show' => 'route:list',
        's' => 'serve',
        'cc' => 'cache:clear',
        'oc' => 'optimize',
        'i' => 'inspire',
        'm' => 'migrate',
        'm:r' => 'migrate:rollback',
        'm:s' => 'migrate:status',
        'dba' => 'db:analyze',
    ];

    protected array $commandGroups = [
        'General' => ['help', 'demo', 'inspire'],
        'Make' => [
            'make:controller',
            'make:model',
            'make:command',
            'make:middleware',
            'make:provider',
            'make:migration',
            'make:request',
            'make:service',
            'make:action',
            'make:enum',
            'make:dto',
            'make:resource',
            'make:repository',
            'make:factory',
            'make:seeder',
            'make:connector',
            'make:api-request',
            'make:pdf-template',
            'make:pagination-template',
            'make:spa-asset',
            'make:editor-asset',
            'make:plugs-assets',
            'make:component',
            'make:event',
            'make:listener',
            'make:notification',
            'make:ai-migration',
        ],
        'Routes' => ['route:list', 'route:cache', 'route:clear', 'route:test'],
        'Utility' => ['serve', 'cache:clear', 'config:cache', 'optimize', 'opcache:clear', 'opcache:status', 'queue:work', 'health', 'storage:link', 'type:gen', 'ai:chat', 'ai:fix', 'ai:audit', 'make:ai-test', 'ai:agent', 'ai:think', 'framework:scan-security'],

        'Scheduling' => ['schedule:run', 'schedule:list'],
        'Database' => ['migrate', 'migrate:rollback', 'migrate:status', 'migrate:fresh', 'migrate:validate', 'migrate:reset', 'make:migration', 'db:seed', 'db:analyze', 'tenant:migrate'],
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
            throw ConsoleException::classNotFound($class);
        }

        if (!is_subclass_of($class, Command::class)) {
            throw ConsoleException::invalidClass($class, Command::class);
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
            throw ConsoleException::aliasNotFound($commandName);
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
