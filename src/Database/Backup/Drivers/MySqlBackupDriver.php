<?php

declare(strict_types=1);

namespace Plugs\Database\Backup\Drivers;

use Plugs\Database\Connection;
use Plugs\Exceptions\DatabaseException;

/**
 * Class MySqlBackupDriver
 * 
 * Handles MySQL database backup and restoration.
 */
class MySqlBackupDriver implements BackupDriverInterface
{
    /**
     * The database connection.
     */
    protected Connection $connection;

    /**
     * Create a new MySQL backup driver instance.
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

        $host = $config['host'];
        $port = $config['port'] ?? 3306;
        $user = $config['username'];
        $pass = $config['password'];
        $name = $config['database'];

        // Build mysqldump command
        $command = sprintf(
            'mysqldump --host=%s --port=%s --user=%s --password=%s %s > %s',
            escapeshellarg($host),
            escapeshellarg((string) $port),
            escapeshellarg($user),
            escapeshellarg($pass),
            escapeshellarg($name),
            escapeshellarg($path)
        );

        exec($command, $output, $returnVar);

        if ($returnVar !== 0) {
            throw new DatabaseException("MySQL backup failed with exit code: {$returnVar}. Ensure 'mysqldump' is installed and in your PATH.");
        }

        return true;
    }

    /**
     * @inheritDoc
     */
    public function restore(string $path, array $options = []): bool
    {
        $config = $this->connection->getConfig();

        $host = $config['host'];
        $port = $config['port'] ?? 3306;
        $user = $config['username'];
        $pass = $config['password'];
        $name = $config['database'];

        // Build mysql restore command
        $command = sprintf(
            'mysql --host=%s --port=%s --user=%s --password=%s %s < %s',
            escapeshellarg($host),
            escapeshellarg((string) $port),
            escapeshellarg($user),
            escapeshellarg($pass),
            escapeshellarg($name),
            escapeshellarg($path)
        );

        exec($command, $output, $returnVar);

        if ($returnVar !== 0) {
            throw new DatabaseException("MySQL restore failed with exit code: {$returnVar}. Ensure 'mysql' client is installed and in your PATH.");
        }

        return true;
    }

    /**
     * @inheritDoc
     */
    public function getExtension(): string
    {
        return 'sql';
    }
}
