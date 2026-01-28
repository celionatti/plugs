<?php

declare(strict_types=1);

namespace Plugs\Database\Seeders;

use Plugs\Database\Connection;
use Plugs\Console\Support\Output;

/**
 * PlugSeeder
 * 
 * Base class for database seeders in Plugs.
 * 
 * @package Plugs\Database\Seeders
 */
abstract class PlugSeeder
{
    /**
     * The database connection
     */
    protected Connection $connection;

    /**
     * The console output instance
     */
    protected ?Output $output = null;

    /**
     * Create a new seeder instance
     */
    public function __construct(Connection $connection, ?Output $output = null)
    {
        $this->connection = $connection;
        $this->output = $output;
    }

    /**
     * Run the seeder logic
     */
    abstract public function run(): void;

    /**
     * Seed the given seeder class
     * 
     * @param string|array $class
     */
    public function call(string|array $class): void
    {
        $classes = is_array($class) ? $class : [$class];

        foreach ($classes as $class) {
            $this->log("Seeding: {$class}");

            $seeder = $this->resolve($class);
            $seeder->run();

            $this->log("Seeded:  {$class}");
        }
    }

    /**
     * Silently seed the given seeder class
     */
    public function callSilent(string|array $class): void
    {
        $currentOutput = $this->output;
        $this->output = null;

        $this->call($class);

        $this->output = $currentOutput;
    }

    /**
     * Resolve the given seeder class
     */
    protected function resolve(string $class): PlugSeeder
    {
        if (!class_exists($class)) {
            throw new \RuntimeException("Seeder class [{$class}] not found.");
        }

        return new $class($this->connection, $this->output);
    }

    /**
     * Log a message to the console
     */
    protected function log(string $message): void
    {
        if ($this->output) {
            $this->output->info($message);
        }
    }
}
