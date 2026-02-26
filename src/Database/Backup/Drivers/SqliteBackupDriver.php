<?php

declare(strict_types=1);

namespace Plugs\Database\Backup\Drivers;

use Plugs\Database\Connection;
use Plugs\Exceptions\DatabaseException;

/**
 * Class SqliteBackupDriver
 * 
 * Handles SQLite database backup and restoration.
 */
class SqliteBackupDriver implements BackupDriverInterface
{
    /**
     * The database connection.
     */
    protected Connection $connection;

    /**
     * Create a new SQLite backup driver instance.
     *
     * @param Connection $connection
     */
    public function __construct(Connection $connection)
    {
        $this->connection = $connection;
    }

    /**
     * @inheritDoc
     */
    public function backup(string $path, array $options = []): bool
    {
        $config = $this->connection->getConfig();
        $sourcePath = $config['database'];

        if (!file_exists($sourcePath)) {
            throw new DatabaseException("SQLite database file not found at: {$sourcePath}");
        }

        if (!copy($sourcePath, $path)) {
            throw new DatabaseException("Failed to copy SQLite database from {$sourcePath} to {$path}");
        }

        return true;
    }

    /**
     * @inheritDoc
     */
    public function restore(string $path, array $options = []): bool
    {
        $config = $this->connection->getConfig();
        $targetPath = $config['database'];

        // Create directory if it doesn't exist
        $directory = dirname($targetPath);
        if (!is_dir($directory)) {
            mkdir($directory, 0755, true);
        }

        if (!copy($path, $targetPath)) {
            throw new DatabaseException("Failed to restore SQLite database from {$path} to {$targetPath}");
        }

        return true;
    }

    /**
     * @inheritDoc
     */
    public function getExtension(): string
    {
        return 'sqlite';
    }
}
