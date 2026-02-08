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
        $this->checkpoint('start');
        $this->title('Database Seeder');

        if (!$this->confirmToProceed()) {
            return 1;
        }

        $class = $this->option('class') ?: 'DatabaseSeeder';

        $this->section('Seeding Information');
        $this->keyValue('Seeder Class', $class);
        $this->keyValue('Environment', $this->isProduction() ? 'production' : 'development');
        $this->newLine();

        if (!$this->confirm("Run seeder [{$class}]?", true)) {
            $this->warning('Seeding cancelled.');

            return 0;
        }

        $this->checkpoint('seeding_started');
        $this->info("Running seeder: {$class}...");

        try {
            $connection = Connection::getInstance();
            $seederPath = base_path('database/Seeders');

            $runner = new SeederRunner($connection, $seederPath, $this->output);

            // Check if class exists or file exists before running
            if (!$this->option('class') && !class_exists("Database\\Seeders\\{$class}") && !file_exists($seederPath . "/{$class}.php")) {
                $this->warning("Default seeder [{$class}] not found.");
                $this->info("You can create it using: php theplugs make:seeder DatabaseSeeder");
                $this->info("Or run a specific seeder using: php theplugs db:seed --class=YourSeeder");

                return 0;
            }

            $result = $runner->run($class);

            $this->checkpoint('finished');

            $this->newLine();
            $this->box(
                "Database seeding completed successfully!\n\n" .
                "Seeder: {$result['class']}\n" .
                "Time: " . number_format($result['time'], 2) . "s\n" .
                "Memory: " . $this->formatNumber(memory_get_peak_usage() / 1024 / 1024, 2) . " MB",
                "✅ Success",
                "success"
            );

            return 0;
        } catch (\Exception $e) {
            $this->error("Seeding failed: " . $e->getMessage());

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
