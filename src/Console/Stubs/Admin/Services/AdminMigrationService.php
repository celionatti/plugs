<?php

declare(strict_types=1);

namespace Modules\Admin\Services;

use Plugs\Database\Connection;
use Plugs\Database\MigrationRunner;
use Plugs\FeatureModule\FeatureModuleManager;

class AdminMigrationService
{
    /**
     * Get migration status.
     */
    public function getStatus(): array
    {
        return $this->getRunner()->status();
    }

    /**
     * Run pending migrations.
     */
    public function runMigrations(): array
    {
        return $this->getRunner()->run();
    }

    /**
     * Rollback last batch.
     */
    public function rollbackMigrations(): array
    {
        return $this->getRunner()->rollback();
    }

    /**
     * Fresh migration.
     */
    public function refreshDatabase(): void
    {
        $this->getRunner()->refresh();
    }

    /**
     * Get a configured MigrationRunner instance.
     */
    protected function getRunner(): MigrationRunner
    {
        $connection = Connection::getInstance();
        $basePath = database_path('Migrations');
        
        $migrationPaths = [$basePath];
        
        // Include feature module migration paths
        $featureManager = FeatureModuleManager::getInstance();
        $modulePaths = $featureManager->getMigrationPaths();
        $migrationPaths = array_merge($migrationPaths, $modulePaths);

        return new MigrationRunner($connection, $migrationPaths);
    }
}
