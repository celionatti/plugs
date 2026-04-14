<?php

declare(strict_types=1);

namespace Plugs\Console\Commands;

/*
|--------------------------------------------------------------------------
| Migrate Command
|--------------------------------------------------------------------------
*/

use Plugs\Console\Command;
use Plugs\Database\Connection;
use Plugs\Database\MigrationRunner;

class MigrateCommand extends Command
{
    protected string $description = 'Run the database migrations';

    protected function defineOptions(): array
    {
        return [
            '--force' => 'Run the migrations without confirmation',
            '--step' => 'Number of migrations to run',
        ];
    }

    public function handle(): int
    {
        $this->checkpoint('start');

        $this->info('Initializing migration runner...');

        try {
            $connection = Connection::getInstance();

            // Silence slow query warnings during migrations unless verbose mode is enabled
            if (!$this->isVerbose()) {
                Connection::silenceSlowQueryAlerts(true);
                if (class_exists(\Plugs\Database\Analysis\QueryAnalyzer::class)) {
                    \Plugs\Database\Analysis\QueryAnalyzer::silenceConsoleWarnings(true);
                }
            }

            $migrationPath = base_path('database/Migrations');

            // Collect migration paths: base + feature modules
            $migrationPaths = [$migrationPath];
            $featureManager = \Plugs\FeatureModule\FeatureModuleManager::getInstance();
            $modulePaths = $featureManager->getMigrationPaths();
            $migrationPaths = array_merge($migrationPaths, $modulePaths);

            $runner = new MigrationRunner($connection, $migrationPaths);

            $this->section('Migration Summary');
            $this->keyValue('Database', $connection->getName());
            $this->keyValue('Path', str_replace(base_path(), '', $migrationPath));
            $this->newLine();

            if (!$this->hasOption('force') && !$this->confirm('Run pending migrations?', true)) {
                $this->warning('Migration execution cancelled.');

                return 0;
            }

            $this->checkpoint('migrating');

            $steps = $this->option('step');

            $result = $this->loading('Running migrations', function () use ($runner, $steps) {
                return $runner->run($steps ? (int) $steps : null);
            });

            $this->checkpoint('finished');

            if (empty($result['migrations'])) {
                $this->newLine();
                $this->note($result['message']);

                return 0;
            }

            $this->output->newLine();
            $this->output->section('Migrated Files');
            foreach ($result['migrations'] as $migration) {
                $this->fileModified($migration . '.php');
            }

            $this->output->newLine();
            $this->resultSummary([
                'Batch' => $result['batch'],
                'Migrated' => count($result['migrations'])
            ]);

            return 0;
        } catch (\Exception $e) {
            $this->error("Migration failed: " . $e->getMessage());

            return 1;
        }
    }
}
