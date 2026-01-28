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
        $this->checkpoint('start');
        $this->title('Migration Status');

        try {
            $connection = Connection::getInstance();
            $migrationPath = getcwd() . '/database/migrations';

            $runner = new MigrationRunner($connection, $migrationPath);
            $status = $runner->status();

            if (empty($status)) {
                $this->note('No migrations found in the database.');
                return 0;
            }

            $this->section('Status Summary');
            $this->keyValue('Database', $connection->getName());
            $this->keyValue('Path', str_replace(getcwd() . '/', '', $migrationPath));
            $this->newLine();

            $headers = ['Migration', 'Ran?', 'Batch', 'Ran At', 'Status'];
            $rows = [];

            foreach ($status as $item) {
                $ranLabel = $item['ran'] ? '✓ Yes' : '✗ No';
                $batchLabel = (string) ($item['batch'] ?? '-');
                $ranAt = $item['migrated_at'] ?? '-';

                $statusText = 'Pending';
                if ($item['ran']) {
                    $statusText = $item['modified'] ? 'Modified' : 'Intact';
                }

                $rows[] = [
                    $item['migration'],
                    $ranLabel,
                    $batchLabel,
                    $ranAt,
                    $statusText
                ];
            }

            $this->table($headers, $rows);

            $this->checkpoint('finished');
            $this->newLine();
            $this->info("Total migrations: " . count($status));

        } catch (\Exception $e) {
            $this->error("Failed to retrieve status: " . $e->getMessage());
            return 1;
        }

        return 0;
    }
}
