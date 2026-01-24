<?php

declare(strict_types=1);

namespace Plugs\Console\Commands;

/*
|--------------------------------------------------------------------------
| Migrate: Rollback Command
|--------------------------------------------------------------------------
*/

use Plugs\Console\Command;
use Plugs\Database\Connection;
use Plugs\Database\MigrationRunner;

class MigrateRollbackCommand extends Command
{
    protected string $description = 'Rollback the last database migration batch';

    public function handle(): int
    {
        $this->info('Rolling back migrations...');

        try {
            $connection = Connection::getInstance();
            $migrationPath = getcwd() . '/database/migrations';

            $runner = new MigrationRunner($connection, $migrationPath);

            $steps = $this->option('step');
            $result = $runner->rollback($steps ? (int) $steps : null);

            if (empty($result['migrations'])) {
                $this->note($result['message']);

                return 0;
            }

            foreach ($result['migrations'] as $migration) {
                $this->warning("Rolled back: {$migration}");
            }

            $this->info($result['message']);
        } catch (\Exception $e) {
            $this->error($e->getMessage());

            return 1;
        }

        return 0;
    }
}
