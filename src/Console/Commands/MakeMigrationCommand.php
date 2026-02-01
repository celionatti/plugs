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
        $this->checkpoint('start');
        $this->title('Migration Generator');

        $name = $this->argument('0');

        if (!$name) {
            $name = $this->ask('Migration name', 'create_users_table');
        }

        $timestamp = date('Y_m_d_His');
        $className = $this->generateClassName($name);
        $filename = $timestamp . '_' . $name . '.php';
        $path = $this->getMigrationPath($filename);

        $this->section('Configuration Summary');
        $this->keyValue('Migration Name', $name);
        $this->keyValue('Class Name', $className);
        $this->keyValue('Target File', $filename);
        $this->newLine();

        if (Filesystem::exists($path) && !$this->isForce()) {
            if (!$this->confirm("Migration already exists at {$path}. Overwrite?", false)) {
                $this->warning('Migration generation cancelled.');
                return 0;
            }
        }

        $this->checkpoint('generating');

        $this->task('Generating migration file', function () use ($className, $name, $path) {
            $content = $this->generateMigration($className, $name);
            Filesystem::put($path, $content);
            usleep(200000);
        });

        $this->checkpoint('finished');

        $this->newLine(2);
        $this->box(
            "Migration created successfully!\n\n" .
            "Filename: {$filename}\n" .
            "Time: {$this->formatTime($this->elapsed())}",
            "✅ Success",
            "success"
        );

        if ($this->isVerbose()) {
            $this->displayTimings();
        }

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

return new class extends Migration
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
};
PHP;
    }

    private function getMigrationPath(string $filename): string
    {
        // Base directory for migrations
        $basePath = BASE_PATH . 'database/Migrations';

        // Ensure directory exists
        if (!is_dir($basePath)) {
            mkdir($basePath, 0755, true);
        }

        return $basePath . '/' . $filename;
    }
}
