<?php

declare(strict_types=1);

namespace Plugs\Console\Commands;

/*
|--------------------------------------------------------------------------
| Make: AI Migration Command
|--------------------------------------------------------------------------
*/

use Plugs\Console\Command;
use Plugs\Console\Support\Filesystem;
use Plugs\Console\Support\Str;
use Plugs\AI\AIManager;
use Plugs\Container\Container;

class MakeAiMigrationCommand extends Command
{
    protected string $signature = 'make:ai-migration {prompt? : The description of the migration}';
    protected string $description = 'Create a new database migration using AI';

    public function handle(): int
    {
        $this->title('AI Migration Generator');

        $promptInput = $this->argument('prompt');

        if (!$promptInput) {
            $promptInput = $this->ask('Describe the database table or changes you want to make', 'create a users table with name, email and bio');
        }

        $this->info("Consulting AI for migration structure...");

        $aiPrompt = <<<PROMPT
You are a database expert for the Plugs PHP Framework.
Generate a PHP migration content based on this description: "{$promptInput}".

Guidelines:
1. Use the Plugs Database Blueprint syntax.
2. The response MUST be ONLY the PHP code within the 'up' method's Closure.
3. Include the table name inference if possible.
4. Output should look like:
\$table->id();
\$table->string('name');
...
\$table->timestamps();

Infer a suitable table name and return it as a comment at the top like: // Table: table_name
PROMPT;

        try {
            $aiManager = Container::getInstance()->make(AIManager::class);
            $response = $aiManager->prompt($aiPrompt);

            // Extract table name
            preg_match('/\/\/ Table: (\w+)/', $response, $matches);
            $tableName = $matches[1] ?? 'table_name';

            // Clean response (remove the comment and any triple backticks)
            $code = preg_replace('/\/\/ Table: \w+/', '', $response);
            $code = str_replace(['```php', '```'], '', $code);
            $code = trim($code);

            $this->success("AI suggested table: {$tableName}");

            $migrationName = "create_{$tableName}_table";
            $timestamp = date('Y_m_d_His');
            $className = Str::studly($migrationName);
            $filename = $timestamp . '_' . $migrationName . '.php';
            $path = $this->getMigrationPath($filename);

            $this->task('Generating migration file', function () use ($className, $tableName, $code, $path) {
                $content = $this->wrapInMigrationTemplate($className, $tableName, $code);
                Filesystem::put($path, $content);
            });

            $this->box(
                "AI Migration created successfully!\n\n" .
                "Filename: {$filename}\n" .
                "Table: {$tableName}",
                "✅ AI Success",
                "success"
            );

            return 0;

        } catch (\Exception $e) {
            $this->error("AI Error: " . $e->getMessage());
            return 1;
        }
    }

    private function wrapInMigrationTemplate(string $className, string $tableName, string $code): string
    {
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
            {$code}
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
        $basePath = base_path('database/Migrations');

        if (!is_dir($basePath)) {
            mkdir($basePath, 0755, true);
        }

        return $basePath . '/' . $filename;
    }
}
