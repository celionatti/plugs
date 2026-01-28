<?php

declare(strict_types=1);

namespace Plugs\Database;

use Plugs\Database\Seeders\PlugSeeder;
use Plugs\Console\Support\Output;

/**
 * SeederRunner
 * 
 * Utility to run database seeders.
 * 
 * @package Plugs\Database
 */
class SeederRunner
{
    /**
     * The database connection
     */
    protected Connection $connection;

    /**
     * The path to seeder files
     */
    protected string $seederPath;

    /**
     * The console output instance
     */
    protected ?Output $output = null;

    /**
     * Create a new seeder runner
     */
    public function __construct(Connection $connection, string $seederPath, ?Output $output = null)
    {
        $this->connection = $connection;
        $this->seederPath = $seederPath;
        $this->output = $output;
    }

    /**
     * Run the given seeder class
     */
    public function run(?string $class = null): array
    {
        $class = $class ?: 'DatabaseSeeder';

        // Full namespace if not provided
        if (!str_contains($class, '\\')) {
            $class = "Database\\Seeders\\{$class}";
        }

        if (!class_exists($class)) {
            // Try loading from file if not autoloaded
            $this->loadSeederFile($class);
        }

        if (!class_exists($class)) {
            throw new \RuntimeException("Seeder class [{$class}] not found.");
        }

        $seeder = new $class($this->connection, $this->output);

        $startTime = microtime(true);

        $seeder->run();

        $elapsed = microtime(true) - $startTime;

        return [
            'class' => $class,
            'time' => $elapsed,
            'status' => 'success'
        ];
    }

    /**
     * Load seeder file if it exists
     */
    protected function loadSeederFile(string $class): void
    {
        $parts = explode('\\', $class);
        $className = end($parts);
        $file = $this->seederPath . '/' . $className . '.php';

        if (file_exists($file)) {
            require_once $file;
        }
    }
}
