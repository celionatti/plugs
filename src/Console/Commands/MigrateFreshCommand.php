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
        if ($this->isProduction() && !$this->confirm('Application In Production! Do you really wish to run this command?')) {
            return 1;
        }

        $this->info('Dropping all tables...');

        try {
            Schema::disableForeignKeyConstraints();

            $tables = Schema::getTables();
            foreach ($tables as $table) {
                Schema::drop($table);
                $this->note("Dropped: {$table}");
            }

            Schema::enableForeignKeyConstraints();

            $this->success('All tables dropped successfully.');

            $this->info('Running migrations...');

            $connection = Connection::getInstance();
            $migrationPath = getcwd() . '/database/migrations';

            $runner = new MigrationRunner($connection, $migrationPath);
            $result = $runner->run();

            if (empty($result['migrations'])) {
                $this->note($result['message'] ?? 'Nothing to migrate.');
                return 0;
            }

            foreach ($result['migrations'] as $migration) {
                $this->success("Migrated: {$migration}");
            }

            $this->info('Database fresh migration completed successfully.');
        } catch (\Exception $e) {
            $this->error($e->getMessage());
            return 1;
        }

        return 0;
    }

    protected function isProduction(): bool
    {
        return env('APP_ENV') === 'production';
    }
}
