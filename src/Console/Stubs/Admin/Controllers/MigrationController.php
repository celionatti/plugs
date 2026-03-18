<?php

declare(strict_types=1);

namespace Modules\Admin\Controllers;

use Modules\Admin\Services\AdminMigrationService;
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
    protected AdminMigrationService $migrationService;

    public function __construct(AdminMigrationService $migrationService)
    {
        $this->migrationService = $migrationService;
    }

    /**
     * Display the migration dashboard.
     */
    public function index(ServerRequestInterface $request): ResponseInterface
    {
        $status = $this->migrationService->getStatus();

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
            $result = $this->migrationService->runMigrations();

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
            $result = $this->migrationService->rollbackMigrations();

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
            $this->migrationService->refreshDatabase();

            return redirect('/admin/migrations')->with('success', 'Database refreshed successfully.');
        } catch (\Exception $e) {
            return redirect('/admin/migrations')->with('error', 'Refresh failed: ' . $e->getMessage());
        }
    }
}
