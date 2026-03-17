<?php

declare(strict_types=1);

namespace Modules\Admin\Controllers;

use Plugs\Database\Connection;
use Plugs\Database\MigrationRunner;
use Plugs\FeatureModule\FeatureModuleManager;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;

/**
 * MigrationController
 * 
 * Handles database migration management in the Admin panel.
 */
class MigrationController
{
    /**
     * Display the migration dashboard.
     */
    public function index(ServerRequestInterface $request): ResponseInterface
    {
        $runner = $this->getMigrationRunner();
        $status = $runner->status();

        return response(view('admin::migrations.index', [
            'status' => $status,
            'title' => 'Database Migrations'
        ]));
    }

    /**
     * Run all pending migrations.
     */
    public function migrate(ServerRequestInterface $request): ResponseInterface
    {
        try {
            $runner = $this->getMigrationRunner();
            $result = $runner->run();

            if (empty($result['migrations'])) {
                return redirect('/admin/migrations')->with('success', 'Nothing to migrate.');
            }

            return redirect('/admin/migrations')->with('success', 'Successfully ran ' . count($result['migrations']) . ' migrations.');
        } catch (\Exception $e) {
            return redirect('/admin/migrations')->with('error', 'Migration failed: ' . $e->getMessage());
        }
    }

    /**
     * Rollback the last migration batch.
     */
    public function rollback(ServerRequestInterface $request): ResponseInterface
    {
        try {
            $runner = $this->getMigrationRunner();
            $result = $runner->rollback();

            if (empty($result['migrations'])) {
                return redirect('/admin/migrations')->with('success', 'Nothing to rollback.');
            }

            return redirect('/admin/migrations')->with('success', 'Successfully rolled back ' . count($result['migrations']) . ' migrations.');
        } catch (\Exception $e) {
            return redirect('/admin/migrations')->with('error', 'Rollback failed: ' . $e->getMessage());
        }
    }

    /**
     * Fresh migration (Reset and re-run).
     */
    public function fresh(ServerRequestInterface $request): ResponseInterface
    {
        try {
            $runner = $this->getMigrationRunner();
            $runner->refresh();

            return redirect('/admin/migrations')->with('success', 'Database refreshed successfully.');
        } catch (\Exception $e) {
            return redirect('/admin/migrations')->with('error', 'Refresh failed: ' . $e->getMessage());
        }
    }

    /**
     * Get a configured MigrationRunner instance.
     */
    protected function getMigrationRunner(): MigrationRunner
    {
        $connection = Connection::getInstance();
        $basePath = base_path('database/Migrations');
        
        $migrationPaths = [$basePath];
        
        // Include feature module migration paths
        $featureManager = FeatureModuleManager::getInstance();
        $modulePaths = $featureManager->getMigrationPaths();
        $migrationPaths = array_merge($migrationPaths, $modulePaths);

        return new MigrationRunner($connection, $migrationPaths);
    }
}
