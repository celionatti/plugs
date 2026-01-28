<?php

declare(strict_types=1);

namespace Plugs\Console\Commands;

/*
|--------------------------------------------------------------------------
| Migrate Command
|--------------------------------------------------------------------------
*/

use Plugs\Console\Command;
use Plugs\Database\Connection;
use Plugs\Database\MigrationRunner;

class MigrateCommand extends Command
{
    protected string $description = 'Run the database migrations';

    public function handle(): int
    {
        $this->checkpoint('start');
        $this->title('Database Migrator');

        $this->info('Initializing migration runner...');

        try {
            $connection = Connection::getInstance();
            $migrationPath = getcwd() . '/database/migrations';

            $runner = new MigrationRunner($connection, $migrationPath);

            $this->section('Migration Summary');
            $this->keyValue('Database', $connection->getName());
            $this->keyValue('Path', str_replace(getcwd() . '/', '', $migrationPath));
            $this->newLine();

            if (!$this->confirm('Run pending migrations?', true)) {
                $this->warning('Migration execution cancelled.');
                return 0;
            }

            $this->checkpoint('migrating');

            $steps = $this->option('step');
            $result = $runner->run($steps ? (int) $steps : null);

            $this->checkpoint('finished');

            if (empty($result['migrations'])) {
                $this->newLine();
                $this->note($result['message']);
                return 0;
            }

            $this->newLine();
            $this->section('Migrated Files');
            foreach ($result['migrations'] as $migration) {
                $this->success("  ✓ {$migration}");
            }

            $this->newLine();
            $this->box(
                "Database migrations completed successfully!\n\n" .
                "Batch: {$result['batch']}\n" .
                "Migrated: " . count($result['migrations']) . "\n" .
                "Time: {$this->formatTime($this->elapsed())}",
                "✅ Success",
                "success"
            );

            return 0;
        } catch (\Exception $e) {
            $this->error("Migration failed: " . $e->getMessage());
            return 1;
        }
    }
}
