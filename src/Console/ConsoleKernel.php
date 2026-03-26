<?php

declare(strict_types=1);

namespace Plugs\Console;

use Plugs\Console\Commands\CacheClearCommand;
use Plugs\Console\Commands\DemoCommand;
use Plugs\Console\Commands\HealthCommand;
use Plugs\Console\Commands\HelpCommand;
use Plugs\Console\Commands\AdminInstallCommand;
use Plugs\Console\Commands\PaymentInstallCommand;
use Plugs\Console\Commands\ExplainCommand;
use Plugs\Console\Commands\InspireCommand;
use Plugs\Console\Commands\MakeActionCommand;
use Plugs\Console\Commands\ConfigPublishCommand;
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
use Plugs\Console\Commands\MakeRepositoryCommand;
use Plugs\Console\Commands\MakeRequestCommand;
use Plugs\Console\Commands\MakeResourceCommand;
use Plugs\Console\Commands\MakeSeederCommand;
use Plugs\Console\Commands\MakeServiceCommand;
use Plugs\Console\Commands\MakeLangCommand;
use Plugs\Console\Commands\MigrateCommand;
use Plugs\Console\Commands\MigrateFreshCommand;
use Plugs\Console\Commands\MigrateResetCommand;
use Plugs\Console\Commands\MigrateRollbackCommand;
use Plugs\Console\Commands\MigrateStatusCommand;
use Plugs\Console\Commands\MigrateValidateCommand;
use Plugs\Console\Commands\QueueWorkCommand;
use Plugs\Console\Commands\QueueFailedCommand;
use Plugs\Console\Commands\QueueRetryCommand;
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
use Plugs\Console\Commands\DatabaseBackupCommand;
use Plugs\Console\Commands\DatabaseRestoreCommand;
use Plugs\Console\Commands\DatabaseAnalyzeCommand;
use Plugs\Console\Commands\SecurityScanCommand;
use Plugs\Console\Commands\ViewCacheCommand;
use Plugs\Console\Commands\ViewClearCommand;
use Plugs\Console\Commands\LogClearCommand;
use Plugs\Console\Commands\MakeAuthModuleCommand;
use Plugs\Console\Commands\MakeProviderCommand;
use Plugs\Console\Commands\MakeModuleCommand;
use Plugs\Console\Commands\MakeFeatureModuleCommand;
use Plugs\Console\Commands\ConfigCacheCommand;
use Plugs\Console\Commands\ConfigClearCommand;
use Plugs\Console\Commands\OptimizeCommand;
use Plugs\Console\Commands\OpCacheClearCommand;
use Plugs\Console\Commands\OpCacheStatusCommand;
use Plugs\Console\Commands\ContainerCacheCommand;
use Plugs\Console\Commands\ContainerClearCommand;
use Plugs\Console\Commands\UpCommand;
use Plugs\Console\Commands\DownCommand;
use Plugs\Console\Commands\ScheduleRunCommand;
use Plugs\Console\Commands\ScheduleListCommand;
use Plugs\Console\Commands\AIIndexDocsCommand;
use Plugs\Console\Commands\ShieldCommand;
use Plugs\Console\Commands\SecurityInstallCommand;
use Plugs\Console\Commands\AuthInstallCommand;
use Plugs\Console\Commands\IdentityInstallCommand;
use Plugs\Console\Commands\MakeThemeCommand;
use Plugs\Console\Commands\NebulaThemeCommand;
use Plugs\Console\Commands\MakeWelcomeCommand;
use Plugs\Console\Commands\MakeCrudCommand;
use Plugs\Console\Commands\TinkerCommand;
use Plugs\Console\Commands\EnvSyncCommand;
use Plugs\Console\Commands\ShareCommand;
use Plugs\Console\Commands\AiScaffoldCommand;
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
        'make:provider' => MakeProviderCommand::class,
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
        'make:pagination-template' => MakePaginationTemplateCommand::class,
        'make:component' => MakeComponentCommand::class,
        'make:event' => MakeEventCommand::class,
        'make:listener' => MakeListenerCommand::class,
        'make:notification' => MakeNotificationCommand::class,
        'make:ai-migration' => MakeAiMigrationCommand::class,
        'make:module' => MakeModuleCommand::class,
        'make:feature-module' => MakeFeatureModuleCommand::class,
        'make:auth-module' => MakeAuthModuleCommand::class,
        'make:lang' => MakeLangCommand::class,
        'make:theme' => MakeThemeCommand::class,
        'make:welcome' => MakeWelcomeCommand::class,
        'make:crud' => MakeCrudCommand::class,
        'theme:nebula' => NebulaThemeCommand::class,

        'db:backup' => DatabaseBackupCommand::class,
        'db:restore' => DatabaseRestoreCommand::class,
        'db:seed' => SeedCommand::class,

        'route:list' => RouteListCommand::class,
        'route:cache' => RouteCacheCommand::class,
        'route:clear' => RouteClearCommand::class,
        'route:test' => RouteTestCommand::class,
        'tinker' => TinkerCommand::class,
        'serve' => ServeCommand::class,
        'cache:clear' => CacheClearCommand::class,
        'logs:clear' => LogClearCommand::class,
        'config:publish' => ConfigPublishCommand::class,
        'config:cache' => ConfigCacheCommand::class,
        'config:clear' => ConfigClearCommand::class,
        'optimize' => OptimizeCommand::class,
        'env:sync' => EnvSyncCommand::class,
        'opcache:clear' => OpCacheClearCommand::class,
        'opcache:status' => OpCacheStatusCommand::class,
        'container:cache' => ContainerCacheCommand::class,
        'container:clear' => ContainerClearCommand::class,

        'migrate' => MigrateCommand::class,
        'migrate:rollback' => MigrateRollbackCommand::class,
        'migrate:status' => MigrateStatusCommand::class,
        'migrate:fresh' => MigrateFreshCommand::class,
        'migrate:validate' => MigrateValidateCommand::class,
        'migrate:reset' => MigrateResetCommand::class,
        'queue:work' => QueueWorkCommand::class,
        'queue:failed' => QueueFailedCommand::class,
        'queue:retry' => QueueRetryCommand::class,
        'health' => HealthCommand::class,
        'storage:link' => StorageLinkCommand::class,
        'up' => UpCommand::class,
        'down' => DownCommand::class,
        'schedule:run' => ScheduleRunCommand::class,
        'schedule:list' => ScheduleListCommand::class,
        'type:gen' => TypeGenCommand::class,
        'ai:chat' => AIChatCommand::class,
        'ai:fix' => AiFixCommand::class,
        'ai:audit' => AiAuditCommand::class,
        'ai:agent' => AIAgentCommand::class,
        'ai:think' => AIThinkCommand::class,
        'ai:scaffold' => AiScaffoldCommand::class,
        'ai:index-docs' => AIIndexDocsCommand::class,
        'share' => ShareCommand::class,

        'shield:list' => ShieldCommand::class,
        'shield:unblock' => ShieldCommand::class,
        'shield:block' => ShieldCommand::class,
        'shield:clear' => ShieldCommand::class,
        'shield:stats' => ShieldCommand::class,
        'security:install' => SecurityInstallCommand::class,
        'security:scan' => SecurityScanCommand::class,
        'make:ai-test' => MakeAiTestCommand::class,
        'db:analyze' => DatabaseAnalyzeCommand::class,
        'framework:scan-security' => SecurityScanCommand::class,
        'auth:install' => AuthInstallCommand::class,
        'view:cache' => ViewCacheCommand::class,
        'view:clear' => ViewClearCommand::class,
        'identity:install' => IdentityInstallCommand::class,
        'admin:install' => AdminInstallCommand::class,
        'payment:install' => PaymentInstallCommand::class,
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
        'g:crud' => 'make:crud',
        'g:repo' => 'make:repository',
        'g:con' => 'make:connector',
        'g:areq' => 'make:api-request',
        'g:fact' => 'make:factory',
        'g:seed' => 'make:seeder',
        'g:comp' => 'make:component',
        'g:evt' => 'make:event',
        'g:lis' => 'make:listener',
        'g:not' => 'make:notification',
        'g:mod' => 'make:module',
        'g:fmod' => 'make:feature-module',
        'g:auth' => 'make:auth-module',
        'g:lang' => 'make:lang',
        'g:theme' => 'make:theme',
        'seed' => 'db:seed',
        'routes' => 'route:list',
        'route:show' => 'route:list',
        's' => 'serve',
        'cc' => 'cache:clear',
        'lc' => 'logs:clear',
        'oc' => 'optimize',
        't' => 'tinker',
        'sync' => 'env:sync',
        'i' => 'inspire',
        'm' => 'migrate',
        'm:r' => 'migrate:rollback',
        'm:s' => 'migrate:status',
        'dba' => 'db:analyze',
        'dbb' => 'db:backup',
        'share' => 'share',
        'ais' => 'ai:scaffold',
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
            'make:pagination-template',
            'make:component',
            'make:event',
            'make:listener',
            'make:notification',
            'make:ai-migration',
            'make:module',
            'make:feature-module',
            'make:auth-module',
            'make:lang',
            'make:theme',
            'make:crud',
            'theme:nebula',
        ],
        'Routes' => ['route:list', 'route:cache', 'route:clear', 'route:test'],
        'Utility' => ['serve', 'tinker', 'env:sync', 'share', 'ai:scaffold', 'cache:clear', 'logs:clear', 'view:cache', 'view:clear', 'config:publish', 'config:cache', 'optimize', 'opcache:clear', 'opcache:status', 'queue:work', 'queue:failed', 'queue:retry', 'health', 'storage:link', 'type:gen', 'ai:chat', 'ai:fix', 'ai:audit', 'make:ai-test', 'ai:agent', 'ai:think', 'framework:scan-security', 'auth:install', 'identity:install', 'payment:install', 'admin:install'],

        'Scheduling' => ['schedule:run', 'schedule:list'],
        'Database' => ['migrate', 'migrate:rollback', 'migrate:status', 'migrate:fresh', 'migrate:validate', 'migrate:reset', 'make:migration', 'db:seed', 'db:analyze', 'db:backup', 'db:restore'],
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
