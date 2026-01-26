<?php

declare(strict_types=1);

namespace Plugs\Console\Commands;

/*
|--------------------------------------------------------------------------
| Migrate: Status Command
|--------------------------------------------------------------------------
*/

use Plugs\Console\Command;
use Plugs\Database\Connection;
use Plugs\Database\MigrationRunner;

class MigrateStatusCommand extends Command
{
    protected string $description = 'Show the status of each migration';

    public function handle(): int
    {
        try {
            $connection = Connection::getInstance();
            $migrationPath = getcwd() . '/database/migrations';

            $runner = new MigrationRunner($connection, $migrationPath);
            $status = $runner->status();

            if (empty($status)) {
                $this->note('No migrations found.');

                return 0;
            }

            $this->info("Migration Status:");
            $this->line(str_repeat('-', 85));
            $this->line(sprintf("%-45s | %-6s | %-5s | %-20s | %-8s", "Migration", "Ran?", "Batch", "Ran At", "Status"));
            $this->line(str_repeat('-', 85));

            foreach ($status as $item) {
                $ranLabel = $item['ran'] ? '<success>Yes</success>' : '<error>No</error>';
                $batchLabel = $item['batch'] ?? '-';
                $ranAt = $item['migrated_at'] ?? '-';
                $statusLabel = 'Pending';

                if ($item['ran']) {
                    $statusLabel = $item['modified'] ? '<warning>Modified</warning>' : '<success>Intact</success>';
                }

                $this->line(sprintf("%-45s | %-6s | %-5s | %-20s | %-8s", $item['migration'], $ranLabel, $batchLabel, $ranAt, $statusLabel));
            }

            $this->line(str_repeat('-', 85));
        } catch (\Exception $e) {
            $this->error($e->getMessage());

            return 1;
        }

        return 0;
    }
}
