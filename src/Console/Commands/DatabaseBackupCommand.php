<?php

declare(strict_types=1);

namespace Plugs\Console\Commands;

use Plugs\Console\Command;
use Plugs\Database\Backup\BackupManager;
use Plugs\Database\Connection;

/**
 * Class DatabaseBackupCommand
 * 
 * Console command for backing up the database.
 */
class DatabaseBackupCommand extends Command
{
    /**
     * The command name and signature.
     */
    protected string $name = 'db:backup';

    /**
     * The command description.
     */
    protected string $description = 'Backup the database';

    /**
     * Handle the command execution.
     *
     * @return int
     */
    public function handle(): int
    {
        $this->checkpoint('start');
        $this->info("Initializing database backup...");

        try {
            $connection = Connection::getInstance();
            $manager = new BackupManager($connection);

            $config = $connection->getConfig();
            $driver = $config['driver'] ?? 'mysql';
            $extension = ($driver === 'sqlite') ? 'sqlite' : 'sql';

            $filename = $this->argument('filename') ?: 'backup-' . date('Y-m-d-H-i-s') . '.' . $extension;
            $directory = $this->option('path') ?: storage_path('backups');
            $path = $directory . DIRECTORY_SEPARATOR . $filename;

            $this->section('Backup Details');
            $this->keyValue('Database', $config['database']);
            $this->keyValue('Driver', $driver);
            $this->keyValue('Destination', $path);
            $this->newLine();

            if (!$this->confirm('Proceed with backup?', true)) {
                $this->warning("Backup cancelled.");
                return 0;
            }

            $success = $this->loading("Exporting database", function () use ($manager, $path) {
                return $manager->backup($path);
            });

            if ($success) {
                $this->newLine();
                $this->success("Database backup completed successfully!");
                $this->box("Backup saved to: \n" . $path, "✅ Success", "success");
            } else {
                $this->error("Database backup failed.");
                return 1;
            }

            $this->metrics($this->elapsed(), memory_get_peak_usage());
            return 0;
        } catch (\Exception $e) {
            $this->error("Backup failed: " . $e->getMessage());
            return 1;
        }
    }
}
