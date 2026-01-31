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

        return $this->sortMigrationsByDependency(array_values(array_diff($allMigrations, $ran)));
    }

    /**
     * Sort migrations based on foreign key dependencies
     */
    private function sortMigrationsByDependency(array $migrations): array
    {
        $tableCreators = []; // Map: tableName => migrationName
        $dependencies = [];  // Map: migrationName => [dependentTableNames]

        // Step 1: Analyze all migrations to find what they create and what they need
        foreach ($migrations as $migration) {
            $file = $this->migrationPath . '/' . $migration . '.php';
            if (!file_exists($file)) {
                continue;
            }

            $content = file_get_contents($file);

            // detecting table creation: Schema::create('users', ...)
            if (preg_match("/Schema::create\s*\(\s*['\"](\w+)['\"]/", $content, $matches)) {
                $tableCreators[$matches[1]] = $migration;
            }

            // detecting dependencies: ->on('users') or Schema::table('users')
            $neededTables = [];

            // Foreign keys: ->on('users')
            if (preg_match_all("/->on\s*\(\s*['\"](\w+)['\"]\)/", $content, $matches)) {
                $neededTables = array_merge($neededTables, $matches[1]);
            }

            // Table modification: Schema::table('users') - implies user table must exist
            if (preg_match("/Schema::table\s*\(\s*['\"](\w+)['\"]/", $content, $matches)) {
                $neededTables[] = $matches[1];
            }

            $dependencies[$migration] = array_unique($neededTables);
        }

        // Step 2: Build the graph
        // Nodes are migrations. Edge A -> B means A must run before B.
        // If B depends on Table T, and A creates Table T, then A -> B.
        $graph = []; // adjacency list: migration => [next_migrations] (dependents)
        $inDegree = []; // migration => count (how many deps not yet met)

        foreach ($migrations as $m) {
            $graph[$m] = [];
            $inDegree[$m] = 0;
        }

        foreach ($migrations as $migration) {
            $needs = $dependencies[$migration] ?? [];
            foreach ($needs as $table) {
                if (isset($tableCreators[$table])) {
                    $creator = $tableCreators[$table];
                    if ($creator !== $migration) {
                        // Dependencies might be on migrations NOT in the pending list (already ran).
                        // If creator is not in $migrations list, we ignore it (assumed satisfied).
                        if (in_array($creator, $migrations)) {
                            if (!in_array($migration, $graph[$creator])) {
                                $graph[$creator][] = $migration;
                                $inDegree[$migration]++;
                            }
                        }
                    }
                }
            }
        }

        // Step 3: Topological Sort (Kahn's Algorithm)
        $queue = [];
        // Initialize queue with nodes having in-degree 0 (no dependencies)
        foreach ($migrations as $m) {
            // Tie-breaking: if generic in-degree is 0, sort by name to be deterministic
            // We'll sort the queue later or just rely on initial order
            if ($inDegree[$m] === 0) {
                $queue[] = $m;
            }
        }

        // For deterministic output, usually better to use a priority queue or sort input
        sort($queue);

        $sorted = [];
        while (!empty($queue)) {
            $current = array_shift($queue);
            $sorted[] = $current;

            if (isset($graph[$current])) {
                foreach ($graph[$current] as $neighbor) {
                    $inDegree[$neighbor]--;
                    if ($inDegree[$neighbor] === 0) {
                        $queue[] = $neighbor;
                        sort($queue); // Keep queue sorted for deterministic order
                    }
                }
            }
        }

        // Check for circular dependencies
        if (count($sorted) !== count($migrations)) {
            // Cycle detected or disconnected components behaving oddly.
            // Fallback: return original order but log warning? 
            // Or throw error.
            // For now, let's append remaining items (broken) to end, 
            // ensuring we return all migrations.
            $remaining = array_diff($migrations, $sorted);
            return array_merge($sorted, $remaining);
        }

        return $sorted;
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
