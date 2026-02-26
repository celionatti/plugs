<?php

declare(strict_types=1);

namespace Plugs\Database\Backup;

use Plugs\Database\Connection;
use Plugs\Database\Backup\Drivers\BackupDriverInterface;
use Plugs\Database\Backup\Drivers\MySqlBackupDriver;
use Plugs\Database\Backup\Drivers\SqliteBackupDriver;
use Plugs\Exceptions\DatabaseException;

/**
 * Class BackupManager
 * 
 * Coordinates the backup and restoration of database data.
 */
class BackupManager
{
    /**
     * The database connection instance.
     */
    protected Connection $connection;

    /**
     * Create a new backup manager instance.
     *
     * @param Connection|null $connection
     */
    public function __construct(?Connection $connection = null)
    {
        $this->connection = $connection ?: Connection::getInstance();
    }

    /**
     * Perform a database backup.
     *
     * @param string $path
     * @param array $options
     * @return bool
     */
    public function backup(string $path, array $options = []): bool
    {
        $driver = $this->resolveDriver();

        // Ensure directory exists
        $directory = dirname($path);
        if (!is_dir($directory)) {
            mkdir($directory, 0755, true);
        }

        return $driver->backup($path, $options);
    }

    /**
     * Restore a database from backup.
     *
     * @param string $path
     * @param array $options
     * @return bool
     */
    public function restore(string $path, array $options = []): bool
    {
        if (!file_exists($path)) {
            throw new DatabaseException("Backup file not found at path: {$path}");
        }

        return $this->resolveDriver()->restore($path, $options);
    }

    /**
     * Resolve the appropriate backup driver for the current connection.
     *
     * @return BackupDriverInterface
     * @throws DatabaseException
     */
    protected function resolveDriver(): BackupDriverInterface
    {
        $config = $this->connection->getConfig();
        $driverName = $config['driver'] ?? 'mysql';

        return match ($driverName) {
            'mysql' => new MySqlBackupDriver($this->connection),
            'sqlite' => new SqliteBackupDriver($this->connection),
            default => throw new DatabaseException("Backup driver not supported for: {$driverName}"),
        };
    }
}
