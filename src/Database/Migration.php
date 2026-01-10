<?php

declare(strict_types=1);

namespace Plugs\Database;

/*
|--------------------------------------------------------------------------
| Migration Base Class
|--------------------------------------------------------------------------
|
| This abstract class provides the foundation for database migrations.
| Extend this class to create migration files that can be run and rolled
| back using the MigrationRunner.
*/

abstract class Migration
{
    protected $connection;

    public function __construct(Connection $connection)
    {
        $this->connection = $connection;
        Schema::setConnection($connection);
    }

    /**
     * Run the migrations
     */
    abstract public function up(): void;

    /**
     * Reverse the migrations
     */
    abstract public function down(): void;

    /**
     * Get the connection instance
     */
    protected function getConnection(): Connection
    {
        return $this->connection;
    }
}
