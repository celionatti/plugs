<?php

declare(strict_types=1);

namespace Plugs\Database\Backup\Drivers;

/**
 * Interface BackupDriverInterface
 * 
 * Defines the contract for database backup drivers.
 */
interface BackupDriverInterface
{
    /**
     * Backup the database to the specified path.
     *
     * @param string $path
     * @param array $options
     * @return bool
     */
    public function backup(string $path, array $options = []): bool;

    /**
     * Restore the database from the specified path.
     *
     * @param string $path
     * @param array $options
     * @return bool
     */
    public function restore(string $path, array $options = []): bool;

    /**
     * Get the extension for the backup file.
     *
     * @return string
     */
    public function getExtension(): string;
}
