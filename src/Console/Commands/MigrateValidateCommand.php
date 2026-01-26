<?php

declare(strict_types=1);

namespace Plugs\Console\Commands;

/*
|--------------------------------------------------------------------------
| Migrate: Validate Command
|--------------------------------------------------------------------------
*/

use Plugs\Console\Command;
use Plugs\Database\Connection;
use Plugs\Database\MigrationRunner;

class MigrateValidateCommand extends Command
{
    protected string $description = 'Validate the integrity of ran migrations';

    public function handle(): int
    {
        $this->info('Validating migrations integrity...');

        try {
            $connection = Connection::getInstance();
            $migrationPath = getcwd() . '/database/migrations';

            $runner = new MigrationRunner($connection, $migrationPath);
            $status = $runner->status();

            $modified = array_filter($status, fn($item) => $item['modified']);

            if (empty($modified)) {
                $this->success('All ran migrations are intact.');
                return 0;
            }

            $this->warning('The following ran migrations have been modified:');
            foreach ($modified as $item) {
                $this->line(" - <error>{$item['migration']}</error> (Modified after execution)");
            }

            $this->note("\nModifying migrations after they have been run can lead to inconsistent database states.");
            $this->note("Consider creating a new migration for changes instead.");

            return 1;
        } catch (\Exception $e) {
            $this->error($e->getMessage());
            return 1;
        }
    }
}
