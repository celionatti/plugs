<?php

declare(strict_types=1);

namespace Plugs\Database;

/*
|--------------------------------------------------------------------------
| Migration Runner Class
|--------------------------------------------------------------------------
|
| This class manages the execution of database migrations. It tracks
| which migrations have been run, supports rollbacks, and provides
| batch management for grouped migrations.
*/

class MigrationRunner
{
    private $connection;
    private $migrationPath;
    private $migrationsTable = 'migrations';

    public function __construct(Connection $connection, string $migrationPath)
    {
        $this->connection = $connection;
        $this->migrationPath = rtrim($migrationPath, '/\\');
        $this->ensureMigrationTableExists();
    }

    /**
     * Create the migrations tracking table if it doesn't exist
     */
    private function ensureMigrationTableExists(): void
    {
        Schema::setConnection($this->connection);

        if (!Schema::hasTable($this->migrationsTable)) {
            Schema::create($this->migrationsTable, function (Blueprint $table) {
                $table->id();
                $table->string('migration');
                $table->integer('batch');
                $table->timestamp('migrated_at')->useCurrent();
            });
        }
    }

    /**
     * Run all pending migrations
     */
    public function run(?int $steps = null): array
    {
        $migrations = $this->getPendingMigrations();

        if (empty($migrations)) {
            return ['message' => 'Nothing to migrate.', 'migrations' => []];
        }

        if ($steps !== null) {
            $migrations = array_slice($migrations, 0, $steps);
        }

        $batch = $this->getNextBatchNumber();
        $ran = [];

        foreach ($migrations as $migration) {
            $this->runMigration($migration, $batch);
            $ran[] = $migration;
        }

        return [
            'message' => 'Migration completed successfully.',
            'migrations' => $ran,
            'batch' => $batch
        ];
    }

    /**
     * Rollback the last batch of migrations
     */
    public function rollback(?int $steps = null): array
    {
        $migrations = $this->getLastBatchMigrations();

        if (empty($migrations)) {
            return ['message' => 'Nothing to rollback.', 'migrations' => []];
        }

        if ($steps !== null) {
            $migrations = array_slice($migrations, 0, $steps);
        }

        $rolledBack = [];

        foreach ($migrations as $migrationData) {
            $this->rollbackMigration($migrationData['migration']);
            $rolledBack[] = $migrationData['migration'];
        }

        return [
            'message' => 'Rollback completed successfully.',
            'migrations' => $rolledBack
        ];
    }

    /**
     * Reset all migrations (rollback everything)
     */
    public function reset(): array
    {
        $migrations = $this->getRanMigrations();

        if (empty($migrations)) {
            return ['message' => 'Nothing to reset.', 'migrations' => []];
        }

        $rolledBack = [];

        foreach ($migrations as $migrationData) {
            $this->rollbackMigration($migrationData['migration']);
            $rolledBack[] = $migrationData['migration'];
        }

        return [
            'message' => 'Database reset successfully.',
            'migrations' => $rolledBack
        ];
    }

    /**
     * Refresh the database (reset + run)
     */
    public function refresh(): array
    {
        $reset = $this->reset();
        $run = $this->run();

        return [
            'reset' => $reset,
            'run' => $run
        ];
    }

    /**
     * Get migration status
     */
    public function status(): array
    {
        $allMigrations = $this->getAllMigrationFiles();
        $ranMigrations = $this->getRanMigrations();

        $ranMap = [];
        foreach ($ranMigrations as $ran) {
            $ranMap[$ran['migration']] = $ran;
        }

        $status = [];
        foreach ($allMigrations as $migration) {
            $status[] = [
                'migration' => $migration,
                'ran' => isset($ranMap[$migration]),
                'batch' => $ranMap[$migration]['batch'] ?? null
            ];
        }

        return $status;
    }

    /**
     * Run a single migration
     */
    private function runMigration(string $migration, int $batch): void
    {
        $instance = $this->resolveMigration($migration);

        // Run the migration within a transaction
        $this->connection->beginTransaction();

        try {
            $instance->up();

            // Record the migration
            $sql = "INSERT INTO {$this->migrationsTable} (migration, batch) VALUES (?, ?)";
            $this->connection->execute($sql, [$migration, $batch]);

            if ($this->connection->inTransaction()) {
                $this->connection->commit();
            }
        } catch (\Exception $e) {
            if ($this->connection->inTransaction()) {
                $this->connection->rollBack();
            }
            throw new \RuntimeException(
                "Migration failed: {$migration}\nError: " . $e->getMessage(),
                0,
                $e
            );
        }
    }

    /**
     * Rollback a single migration
     */
    private function rollbackMigration(string $migration): void
    {
        $instance = $this->resolveMigration($migration);

        // Rollback within a transaction
        $this->connection->beginTransaction();

        try {
            $instance->down();

            // Remove the migration record
            $sql = "DELETE FROM {$this->migrationsTable} WHERE migration = ?";
            $this->connection->execute($sql, [$migration]);

            if ($this->connection->inTransaction()) {
                $this->connection->commit();
            }
        } catch (\Exception $e) {
            if ($this->connection->inTransaction()) {
                $this->connection->rollBack();
            }
            throw new \RuntimeException(
                "Rollback failed: {$migration}\nError: " . $e->getMessage(),
                0,
                $e
            );
        }
    }

    /**
     * Get all migration files
     */
    private function getAllMigrationFiles(): array
    {
        if (!is_dir($this->migrationPath)) {
            return [];
        }

        $files = glob($this->migrationPath . '/*.php');
        $migrations = [];

        foreach ($files as $file) {
            $migrations[] = basename($file, '.php');
        }

        sort($migrations);

        return $migrations;
    }

    /**
     * Get pending migrations
     */
    private function getPendingMigrations(): array
    {
        $allMigrations = $this->getAllMigrationFiles();
        $ranMigrations = $this->getRanMigrations();

        $ran = array_column($ranMigrations, 'migration');

        return array_values(array_diff($allMigrations, $ran));
    }

    /**
     * Get migrations that have been run
     */
    private function getRanMigrations(): array
    {
        $sql = "SELECT migration, batch FROM {$this->migrationsTable} ORDER BY id DESC";
        return $this->connection->fetchAll($sql);
    }

    /**
     * Get the last batch of migrations
     */
    private function getLastBatchMigrations(): array
    {
        $lastBatch = $this->getLastBatchNumber();

        if ($lastBatch === 0) {
            return [];
        }

        $sql = "SELECT migration, batch FROM {$this->migrationsTable} 
                WHERE batch = ? ORDER BY id DESC";
        return $this->connection->fetchAll($sql, [$lastBatch]);
    }

    /**
     * Get the last batch number
     */
    private function getLastBatchNumber(): int
    {
        $sql = "SELECT MAX(batch) as batch FROM {$this->migrationsTable}";
        $result = $this->connection->fetch($sql);
        return (int) ($result['batch'] ?? 0);
    }

    /**
     * Get the next batch number
     */
    private function getNextBatchNumber(): int
    {
        return $this->getLastBatchNumber() + 1;
    }

    /**
     * Resolve a migration instance from filename
     */
    private function resolveMigration(string $migration): Migration
    {
        $file = $this->migrationPath . '/' . $migration . '.php';

        if (!file_exists($file)) {
            throw new \RuntimeException("Migration file not found: {$file}");
        }

        require_once $file;

        // Extract class name from migration filename
        // Format: 2024_01_01_000000_create_users_table.php
        $parts = explode('_', $migration);

        // Remove timestamp parts (first 4 elements: YYYY_MM_DD_HHMMSS)
        // Adjusting logic: usually timestamp is 4 parts if joined by _ (YYYY, MM, DD, TIME)
        // Let's be smart about it. We look for the part that doesn't look like a number/timestamp.
        $classParts = [];
        $foundStart = false;
        foreach ($parts as $part) {
            if (!$foundStart && !is_numeric($part)) {
                $foundStart = true;
            }
            if ($foundStart) {
                $classParts[] = $part;
            }
        }

        $className = implode('_', $classParts);

        // Convert to StudlyCase
        $className = str_replace('_', '', ucwords($className, '_'));

        // Try to find the class
        if (!class_exists($className)) {
            throw new \RuntimeException("Migration class not found: {$className}");
        }

        return new $className($this->connection);
    }

    /**
     * Set custom migrations table name
     */
    public function setMigrationsTable(string $table): void
    {
        $this->migrationsTable = $table;
    }
}