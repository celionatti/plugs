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
    private $logsTable = 'migration_logs';

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
                $table->string('checksum')->nullable();
                $table->timestamp('migrated_at')->useCurrent();
            });
        } elseif (!Schema::hasColumn($this->migrationsTable, 'checksum')) {
            Schema::table($this->migrationsTable, function (Blueprint $table) {
                $table->string('checksum')->nullable()->after('batch');
            });
        }

        if (!Schema::hasTable($this->logsTable)) {
            Schema::create($this->logsTable, function (Blueprint $table) {
                $table->id();
                $table->integer('migration_id');
                $table->string('migration');
                $table->string('action'); // 'up' or 'down'
                $table->text('sql_queries')->nullable();
                $table->float('duration')->nullable(); // in seconds
                $table->integer('memory_used')->nullable(); // in bytes
                $table->timestamp('executed_at')->useCurrent();
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
            return ['status' => 'success', 'message' => 'Nothing to migrate.', 'migrations' => []];
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
            'status' => 'success',
            'message' => 'Migration completed successfully.',
            'migrations' => $ran,
            'batch' => $batch,
        ];
    }

    /**
     * Rollback migrations
     */
    public function rollback(?int $steps = null): array
    {
        $migrations = $this->getLastBatchMigrations();

        if (empty($migrations)) {
            return ['status' => 'success', 'message' => 'Nothing to rollback.', 'migrations' => []];
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
            'status' => 'success',
            'message' => 'Rollback completed successfully.',
            'migrations' => $rolledBack,
        ];
    }

    /**
     * Reset all migrations
     */
    public function reset(): array
    {
        $migrations = $this->getRanMigrations();

        if (empty($migrations)) {
            return ['status' => 'success', 'message' => 'Nothing to reset.', 'migrations' => []];
        }

        $rolledBack = [];

        foreach ($migrations as $migrationData) {
            $this->rollbackMigration($migrationData['migration']);
            $rolledBack[] = $migrationData['migration'];
        }

        return [
            'status' => 'success',
            'message' => 'Database reset successfully.',
            'migrations' => $rolledBack,
        ];
    }

    /**
     * Refresh migrations (reset + run)
     */
    public function refresh(): array
    {
        $reset = $this->reset();
        $run = $this->run();

        return [
            'reset' => $reset,
            'run' => $run,
        ];
    }

    /**
     * Get migration status with modification detection
     */
    public function status(): array
    {
        $this->ensureMigrationTableExists();

        $allMigrations = $this->getAllMigrationFiles();
        $ranMigrations = $this->getRanMigrations();

        $ranMap = [];
        foreach ($ranMigrations as $ran) {
            $ranMap[$ran['migration']] = $ran;
        }

        $status = [];
        foreach ($allMigrations as $migration) {
            $ran = isset($ranMap[$migration]);
            $currentChecksum = $this->calculateChecksum($migration);
            $storedChecksum = $ranMap[$migration]['checksum'] ?? null;
            $modified = $ran && $storedChecksum && $storedChecksum !== $currentChecksum;

            $status[] = [
                'migration' => $migration,
                'ran' => $ran,
                'batch' => $ranMap[$migration]['batch'] ?? null,
                'migrated_at' => $ranMap[$migration]['migrated_at'] ?? null,
                'modified' => $modified,
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
        $checksum = $this->calculateChecksum($migration);

        $startTime = microtime(true);
        $startMemory = memory_get_usage();

        $this->connection->clearQueryLog();
        $this->connection->enableQueryLog();

        $this->connection->beginTransaction();

        try {
            $instance->up();

            $sqlInsert = "INSERT INTO {$this->migrationsTable} (migration, batch, checksum) VALUES (?, ?, ?)";
            $this->connection->execute($sqlInsert, [$migration, $batch, $checksum]);
            $migrationId = (int) $this->connection->getLastInsertId();

            $executedQueries = $this->connection->getQueryLog();
            $this->connection->disableQueryLog();

            $duration = microtime(true) - $startTime;
            $memoryUsed = memory_get_usage() - $startMemory;

            $this->logMigration($migrationId, $migration, 'up', $executedQueries, $duration, $memoryUsed);

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

        $startTime = microtime(true);
        $startMemory = memory_get_usage();

        $this->connection->clearQueryLog();
        $this->connection->enableQueryLog();

        $this->connection->beginTransaction();

        try {
            $sqlGetId = "SELECT id FROM {$this->migrationsTable} WHERE migration = ?";
            $migrationData = $this->connection->fetch($sqlGetId, [$migration]);
            $migrationId = $migrationData ? (int) $migrationData['id'] : 0;

            $instance->down();

            $sqlDelete = "DELETE FROM {$this->migrationsTable} WHERE migration = ?";
            $this->connection->execute($sqlDelete, [$migration]);

            $executedQueries = $this->connection->getQueryLog();
            $this->connection->disableQueryLog();

            $duration = microtime(true) - $startTime;
            $memoryUsed = memory_get_usage() - $startMemory;

            $this->logMigration($migrationId, $migration, 'down', $executedQueries, $duration, $memoryUsed);

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
     * Calculate checksum for a migration file
     */
    private function calculateChecksum(string $migration): string
    {
        $file = $this->migrationPath . '/' . $migration . '.php';
        return file_exists($file) ? md5_file($file) : '';
    }

    /**
     * Log migration execution details
     */
    private function logMigration(int $migrationId, string $migration, string $action, array $queries, float $duration, int $memory): void
    {
        $sqlQueries = json_encode($queries);
        $sql = "INSERT INTO {$this->logsTable} (migration_id, migration, action, sql_queries, duration, memory_used) VALUES (?, ?, ?, ?, ?, ?)";
        $this->connection->execute($sql, [$migrationId, $migration, $action, $sqlQueries, $duration, $memory]);
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
        if (!Schema::hasTable($this->migrationsTable)) {
            return [];
        }

        // Get columns to check for migrated_at
        $columns = Schema::getColumns($this->migrationsTable);
        $columnNames = array_map(function ($col) {
            return $col['Field'] ?? $col['field'] ?? '';
        }, $columns);

        $orderBy = in_array('migrated_at', $columnNames) ? 'migrated_at' : 'id';
        $sql = "SELECT * FROM {$this->migrationsTable} ORDER BY {$orderBy} DESC";

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
        if (!Schema::hasTable($this->migrationsTable)) {
            return 0;
        }

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

        $instance = require_once $file;

        if ($instance instanceof Migration) {
            $instance->setConnection($this->connection);
            return $instance;
        }

        $parts = explode('_', $migration);

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
        $className = str_replace('_', '', ucwords($className, '_'));

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
