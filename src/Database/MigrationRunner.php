<?php

declare(strict_types=1);

namespace Plugs\Database;

/*
|--------------------------------------------------------------------------
| MigrationRunner Class
|--------------------------------------------------------------------------
|
| This class handles database migrations. It allows you to run, rollback,
| and reset migrations to manage your database schema over time.
*/

class MigrationRunner
{
    private $connection;
    private $migrationsPath;
    
    public function __construct(Connection $connection, string $migrationsPath)
    {
        $this->connection = $connection;
        $this->migrationsPath = $migrationsPath;
        $this->createMigrationsTable();
    }
    
    private function createMigrationsTable(): void
    {
        $sql = "CREATE TABLE IF NOT EXISTS migrations (
            id INT AUTO_INCREMENT PRIMARY KEY,
            migration VARCHAR(255) NOT NULL,
            batch INT NOT NULL,
            executed_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
        )";
        
        $this->connection->execute($sql);
    }
    
    public function run(): void
    {
        $migrations = $this->getPendingMigrations();
        
        if (empty($migrations)) {
            echo "No pending migrations.\n";
            return;
        }
        
        $batch = $this->getNextBatchNumber();
        
        foreach ($migrations as $migration) {
            echo "Migrating: {$migration}\n";
            
            $instance = require $this->migrationsPath . '/' . $migration;
            $instance->up();
            
            $this->connection->query(
                "INSERT INTO migrations (migration, batch) VALUES (?, ?)",
                [$migration, $batch]
            );
            
            echo "Migrated: {$migration}\n";
        }
        
        echo "Migration completed!\n";
    }
    
    public function rollback(int $steps = 1): void
    {
        $batches = $this->connection->fetchAll(
            "SELECT DISTINCT batch FROM migrations ORDER BY batch DESC LIMIT ?",
            [$steps]
        );
        
        if (empty($batches)) {
            echo "Nothing to rollback.\n";
            return;
        }
        
        foreach ($batches as $batch) {
            $migrations = $this->connection->fetchAll(
                "SELECT migration FROM migrations WHERE batch = ? ORDER BY id DESC",
                [$batch['batch']]
            );
            
            foreach ($migrations as $migration) {
                echo "Rolling back: {$migration['migration']}\n";
                
                $instance = require $this->migrationsPath . '/' . $migration['migration'];
                $instance->down();
                
                $this->connection->query(
                    "DELETE FROM migrations WHERE migration = ?",
                    [$migration['migration']]
                );
                
                echo "Rolled back: {$migration['migration']}\n";
            }
        }
        
        echo "Rollback completed!\n";
    }
    
    public function reset(): void
    {
        $migrations = $this->connection->fetchAll(
            "SELECT migration FROM migrations ORDER BY id DESC"
        );
        
        foreach ($migrations as $migration) {
            echo "Rolling back: {$migration['migration']}\n";
            
            $instance = require $this->migrationsPath . '/' . $migration['migration'];
            $instance->down();
        }
        
        $this->connection->execute("TRUNCATE TABLE migrations");
        echo "All migrations rolled back!\n";
    }
    
    private function getPendingMigrations(): array
    {
        $files = glob($this->migrationsPath . '/*.php');
        $files = array_map('basename', $files);
        sort($files);
        
        $executed = $this->connection->fetchAll("SELECT migration FROM migrations");
        $executed = array_column($executed, 'migration');
        
        return array_diff($files, $executed);
    }
    
    private function getNextBatchNumber(): int
    {
        $result = $this->connection->fetch("SELECT MAX(batch) as batch FROM migrations");
        return ($result['batch'] ?? 0) + 1;
    }
}