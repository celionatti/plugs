<?php

declare(strict_types=1);

namespace Plugs\Console\Commands;

use Plugs\Console\Command;
use Plugs\Database\Backup\BackupManager;
use Plugs\Database\Connection;

/**
 * Class DatabaseRestoreCommand
 * 
 * Console command for restoring the database from backup.
 */
class DatabaseRestoreCommand extends Command
{
    /**
     * The command name and signature.
     */
    protected string $name = 'db:restore';

    /**
     * The command description.
     */
    protected string $description = 'Restore the database from a backup file';

    /**
     * Handle the command execution.
     *
     * @return int
     */
    public function handle(): int
    {
        $this->checkpoint('start');
        $this->info("Initializing database restoration...");

        try {
            $path = $this->argument('path');

            if (!$path) {
                $this->error("Backup file path is required.");
                return 1;
            }

            if (!file_exists($path)) {
                $this->error("Backup file not found at: {$path}");
                return 1;
            }

            $connection = Connection::getInstance();
            $manager = new BackupManager($connection);
            $config = $connection->getConfig();

            $this->section('Restoration Details');
            $this->keyValue('Database', $config['database']);
            $this->keyValue('Source', $path);
            $this->newLine();

            $this->warning("CAUTION: This will overwrite your current database data.");
            if (!$this->confirm('Proceed with restoration?', false)) {
                $this->warning("Restoration cancelled.");
                return 0;
            }

            $success = $this->loading("Importing database", function () use ($manager, $path) {
                return $manager->restore($path);
            });

            if ($success) {
                $this->newLine();
                $this->success("Database restoration completed successfully!");
            } else {
                $this->error("Database restoration failed.");
                return 1;
            }

            $this->metrics($this->elapsed(), memory_get_peak_usage());
            return 0;
        } catch (\Exception $e) {
            $this->error("Restoration failed: " . $e->getMessage());
            return 1;
        }
    }
}
