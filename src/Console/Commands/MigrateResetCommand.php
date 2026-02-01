<?php

declare(strict_types=1);

namespace Plugs\Console\Commands;

/*
|--------------------------------------------------------------------------
| Migrate: Reset Command
|--------------------------------------------------------------------------
*/

use Plugs\Console\Command;
use Plugs\Database\Connection;
use Plugs\Database\MigrationRunner;

class MigrateResetCommand extends Command
{
    protected string $description = 'Rollback all database migrations';

    public function handle(): int
    {
        $this->checkpoint('start');
        $this->title('Migration Reset');

        $this->critical('CAUTION: This will rollback ALL migrations and delete ALL data!');
        if (!$this->confirm('Are you sure you want to proceed?', false)) {
            $this->warning('Operation cancelled.');
            return 0;
        }

        $this->info('Resetting database migrations...');

        try {
            $connection = Connection::getInstance();
            $migrationPath = BASE_PATH . 'database/Migrations';

            $runner = new MigrationRunner($connection, $migrationPath);
            $result = $runner->reset();

            $this->checkpoint('finished');

            if (empty($result['migrations'])) {
                $this->newLine();
                $this->note($result['message'] ?? 'No migrations found to reset.');
                return 0;
            }

            $this->newLine();
            $this->section('Rolled Back Files');
            foreach ($result['migrations'] as $migration) {
                $this->warning("  ✗ {$migration}");
            }

            $this->newLine();
            $this->box(
                "Database migrations reset successfully!\n\n" .
                "Rolled Back: " . count($result['migrations']) . "\n" .
                "Time: {$this->formatTime($this->elapsed())}",
                "✅ Reset Complete",
                "success"
            );

            return 0;
        } catch (\Exception $e) {
            $this->error("Reset failed: " . $e->getMessage());
            return 1;
        }
    }
}
