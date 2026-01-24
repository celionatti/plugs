<?php

declare(strict_types=1);

namespace Plugs\Console\Commands;

/*
|--------------------------------------------------------------------------
| Make: Migration Command
|--------------------------------------------------------------------------
*/

use Plugs\Console\Command;
use Plugs\Console\Support\Filesystem;
use Plugs\Console\Support\Str;

class MakeMigrationCommand extends Command
{
    protected string $description = 'Create a new database migration';

    public function handle(): int
    {
        $name = $this->argument('0') ?? $this->ask('Migration name', 'create_users_table');

        $timestamp = date('Y_m_d_His');
        $className = $this->generateClassName($name);

        $content = $this->generateMigration($className, $name);
        $filename = $timestamp . '_' . $name . '.php';
        $path = $this->getMigrationPath($filename);

        Filesystem::put($path, $content);

        $this->success("Migration created: {$filename}");

        return 0;
    }

    private function generateClassName(string $name): string
    {
        return Str::studly($name);
    }

    private function generateMigration(string $className, string $name): string
    {
        // Try to determine table name from migration name (e.g. create_users_table => users)
        $tableName = 'table_name';
        if (strpos($name, 'create_') === 0) {
            $tableName = str_replace(['create_', '_table'], '', $name);
        }

        return <<<PHP
<?php

declare(strict_types=1);

use Plugs\Database\Migration;
use Plugs\Database\Blueprint;
use Plugs\Database\Schema;

class {$className} extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('{$tableName}', function (Blueprint \$table) {
            \$table->id();
            \$table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('{$tableName}');
    }
}
PHP;
    }

    private function getMigrationPath(string $filename): string
    {
        // Base directory for migrations
        $basePath = getcwd() . '/database/migrations';

        // Ensure directory exists
        if (!is_dir($basePath)) {
            mkdir($basePath, 0755, true);
        }

        return $basePath . '/' . $filename;
    }
}
