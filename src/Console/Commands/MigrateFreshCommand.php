<?php

declare(strict_types=1);

namespace Plugs\Console\Commands;

/*
|--------------------------------------------------------------------------
| Migrate: Fresh Command
|--------------------------------------------------------------------------
*/

use Plugs\Console\Command;
use Plugs\Database\Connection;
use Plugs\Database\MigrationRunner;
use Plugs\Database\Schema;

class MigrateFreshCommand extends Command
{
    protected string $description = 'Drop all tables and re-run all migrations';

    public function handle(): int
    {
        $this->checkpoint('start');
        $this->title('Database Refresh');

        if ($this->isProduction()) {
            $this->critical('CAUTION: Application is in PRODUCTION mode!');
            if (!$this->confirm('Do you really wish to run this destructive command?', false)) {
                $this->warning('Operation cancelled.');
                return 1;
            }
        }

        $this->info('Initializing database refresh...');

        try {
            Schema::disableForeignKeyConstraints();

            $tables = Schema::getTables();

            if (!empty($tables)) {
                $this->section('Dropping Tables');
                foreach ($tables as $table) {
                    $this->task("Dropping table: {$table}", function () use ($table) {
                        Schema::drop($table);
                    });
                }
            } else {
                $this->note('No tables found in the database.');
            }

            Schema::enableForeignKeyConstraints();

            $this->newLine();
            $this->checkpoint('tables_dropped');

            $this->info('Re-running all migrations...');

            $connection = Connection::getInstance();
            $migrationPath = getcwd() . '/database/migrations';

            $runner = new MigrationRunner($connection, $migrationPath);
            $result = $runner->run();

            $this->checkpoint('finished');

            if (!empty($result['migrations'])) {
                $this->section('Migrated Files');
                foreach ($result['migrations'] as $migration) {
                    $this->success("  ✓ {$migration}");
                }
            }

            $this->newLine();
            $this->box(
                "Database fresh migration completed successfully!\n\n" .
                "Dropped: " . count($tables) . " tables\n" .
                "Migrated: " . count($result['migrations'] ?? []) . " files\n" .
                "Batch: " . ($result['batch'] ?? 'N/A') . "\n" .
                "Time: {$this->formatTime($this->elapsed())}",
                "✅ Success",
                "success"
            );

            return 0;
        } catch (\Exception $e) {
            $this->error("Fresh migration failed: " . $e->getMessage());
            return 1;
        }
    }

    protected function isProduction(): bool
    {
        return env('APP_ENV') === 'production';
    }
}
