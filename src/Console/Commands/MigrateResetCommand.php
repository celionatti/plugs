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
        if (!$this->confirm('Are you sure you want to reset all migrations? This will drop ALL data!')) {
            return 0;
        }

        $this->info('Resetting database...');

        try {
            $connection = Connection::getInstance();
            $migrationPath = getcwd() . '/database/migrations';

            $runner = new MigrationRunner($connection, $migrationPath);
            $result = $runner->reset();

            if (empty($result['migrations'])) {
                $this->note($result['message']);
                return 0;
            }

            foreach ($result['migrations'] as $migration) {
                $this->warning("Rolled back: {$migration}");
            }

            $this->success($result['message']);
        } catch (\Exception $e) {
            $this->error($e->getMessage());
            return 1;
        }

        return 0;
    }
}
