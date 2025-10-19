<?php

declare(strict_types=1);

namespace Plugs\Console\Commands;

/*
|--------------------------------------------------------------------------
| Make: Migration Command
|--------------------------------------------------------------------------
*/

use Plugs\Console\Command;
use Plugs\Console\Support\Str;
use Plugs\Console\Support\Filesystem;

class MakeMigrationCommand extends Command
{
    protected string $description = 'Create a new database migration';

    public function handle(): int
    {
        $name = $this->argument('0') ?? $this->ask('Migration name', 'create_users_table');
        
        $timestamp = date('YmdHis');
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
        return <<<PHP
        <?php

        declare(strict_types=1);

        use Plugs\\Database\\Migration;
        use Plugs\\Database\\Schema\\Blueprint;
        use Plugs\\Database\\Schema\\Schema;

        return new class extends Migration
        {
            public function up(): void
            {
                Schema::create('table_name', function (Blueprint \$table) {
                    \$table->id();
                    \$table->timestamps();
                });
            }

            public function down(): void
            {
                Schema::dropIfExists('table_name');
            }
        };

        PHP;
    }

    private function getMigrationPath(string $filename): string
    {
        return getcwd() . '/database/migrations/' . $filename;
    }
}
