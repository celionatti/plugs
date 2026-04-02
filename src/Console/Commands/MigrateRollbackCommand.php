<?php

declare(strict_types=1);

namespace Plugs\Console\Commands;

/*
|--------------------------------------------------------------------------
| Migrate: Rollback Command
|--------------------------------------------------------------------------
*/

use Plugs\Console\Command;
use Plugs\Database\Connection;
use Plugs\Database\MigrationRunner;

class MigrateRollbackCommand extends Command
{
    protected string $description = 'Rollback the last database migration batch';

    public function handle(): int
    {
        $this->checkpoint('start');
        $this->title('Migration Rollback');

        $this->info('Initializing rollback...');

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
            $migrationPaths = array_merge($migrationPaths, $featureManager->getMigrationPaths());

            $runner = new MigrationRunner($connection, $migrationPaths);

            $this->section('Rollback Summary');
            $this->keyValue('Database', $connection->getName());
            $this->keyValue('Path', str_replace(getcwd() . '/', '', $migrationPath));
            $this->newLine();

            if (!$this->confirm('Rollback last migration batch?', true)) {
                $this->warning('Rollback cancelled.');

                return 0;
            }

            $this->checkpoint('rolling_back');

            $steps = $this->option('step');
            $result = $runner->rollback($steps ? (int) $steps : null);

            $this->checkpoint('finished');

            if (empty($result['migrations'])) {
                $this->newLine();
                $this->note($result['message'] ?? 'No migrations found to rollback.');

                return 0;
            }

            $this->newLine();
            $this->section('Rolled Back Files');
            foreach ($result['migrations'] as $migration) {
                $this->fileDeleted($migration . '.php');
            }

            $this->newLine();
            $this->resultSummary([
                'Batch Reversed' => $result['batch'] ?? 'N/A',
                'Migrated' => count($result['migrations'])
            ], $this->elapsed());

            return 0;
        } catch (\Exception $e) {
            $this->error("Rollback failed: " . $e->getMessage());

            return 1;
        }
    }
}
