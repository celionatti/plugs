<?php

declare(strict_types=1);

namespace Plugs\Console\Commands;

use Plugs\Console\Command;
use Plugs\Database\Connection;
use Plugs\Database\SeederRunner;

/**
 * SeedCommand
 * 
 * Seed the database with records.
 * 
 * @package Plugs\Console\Commands
 */
class SeedCommand extends Command
{
    protected string $description = 'Seed the database with records';

    public function name(): string
    {
        return 'db:seed';
    }

    protected function defineOptions(): array
    {
        return [
            '--class=CLASS' => 'The class name of the root seeder',
            '--force' => 'Force the operation to run when in production',
        ];
    }

    public function handle(): int
    {
        if (!$this->confirmToProceed()) {
            return 1;
        }

        $this->info('Seeding database...');

        try {
            $connection = Connection::getInstance();
            $seederPath = getcwd() . '/database/seeders';

            $runner = new SeederRunner($connection, $seederPath, $this->output);

            $class = $this->option('class') ?: 'DatabaseSeeder';

            $result = $runner->run($class);

            $this->success("Database seeding completed successfully.");
            if ($this->isVerbose()) {
                $this->note("Seeder: {$result['class']}");
                $this->note("Time: " . number_format($result['time'], 2) . "s");
            }

            return 0;
        } catch (\Exception $e) {
            $this->error($e->getMessage());
            return 1;
        }
    }

    /**
     * Confirm before proceeding in production
     */
    protected function confirmToProceed(): bool
    {
        // Simple production check for demo purposes
        if ($this->hasOption('force')) {
            return true;
        }

        // Add proper production environment check here if framework supports it
        return true;
    }
}
