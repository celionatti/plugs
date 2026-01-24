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
        $this->info('Running migrations...');

        try {
            $connection = Connection::getInstance();
            $migrationPath = getcwd() . '/database/migrations';

            $runner = new MigrationRunner($connection, $migrationPath);

            $steps = $this->option('step');
            $result = $runner->run($steps ? (int) $steps : null);

            if (empty($result['migrations'])) {
                $this->note($result['message']);

                return 0;
            }

            foreach ($result['migrations'] as $migration) {
                $this->success("Migrated: {$migration}");
            }

            $this->info($result['message'] . " (Batch: {$result['batch']})");
        } catch (\Exception $e) {
            $this->error($e->getMessage());

            return 1;
        }

        return 0;
    }
}
