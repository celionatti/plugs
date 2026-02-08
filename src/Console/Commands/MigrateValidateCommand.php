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
        $this->checkpoint('start');
        $this->title('Migration Integrity Check');

        $this->info('Validating database migrations integrity...');

        try {
            $connection = Connection::getInstance();
            $migrationPath = base_path('database/Migrations');

            $runner = new MigrationRunner($connection, $migrationPath);
            $status = $runner->status();

            $modified = array_filter($status, fn ($item) => $item['modified']);

            $this->checkpoint('finished');

            if (empty($modified)) {
                $this->newLine();
                $this->success('All ran migrations are intact and consistent with their source files.');

                $this->box(
                    "Integrity check passed!\n\n" .
                    "Total Checked: " . count($status) . "\n" .
                    "Issues Found: 0\n" .
                    "Time: {$this->formatTime($this->elapsed())}",
                    "✅ System Healthy",
                    "success"
                );

                return 0;
            }

            $this->newLine();
            $this->critical('INTEGRITY FAILURE: The following migrations have been modified!');

            foreach ($modified as $item) {
                $this->error("  ✗ {$item['migration']}");
            }

            $this->newLine();
            $this->warning('Why is this a problem?');
            $this->note('Modifying migrations after they have been run can lead to inconsistent database states.');
            $this->note('Consider creating a new migration for changes instead.');

            return 1;
        } catch (\Exception $e) {
            $this->error("Integrity check failed: " . $e->getMessage());

            return 1;
        }
    }
}
