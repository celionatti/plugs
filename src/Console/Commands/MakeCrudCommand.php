<?php

declare(strict_types=1);

namespace Plugs\Console\Commands;

/*
|--------------------------------------------------------------------------
| Make: CRUD Command
|--------------------------------------------------------------------------
*/

use Plugs\Console\Command;
use Plugs\Console\Support\Filesystem;
use Plugs\Console\Support\Str;

class MakeCrudCommand extends Command
{
    protected string $description = 'Scaffold a full CRUD (Model, Migration, Controller, Views)';

    protected function defineArguments(): array
    {
        return [
            'name' => 'The name of the resource (e.g., Post)',
        ];
    }

    protected function defineOptions(): array
    {
        return [
            '--force' => 'Overwrite existing files',
        ];
    }

    public function handle(): int
    {
        $name = $this->argument('name');

        if (!$name) {
            $name = $this->ask('Resource name (e.g., Post)');
        }

        $name = ucfirst(Str::studly($name));
        $tableName = Str::snake(Str::pluralize($name));

        $this->advancedHeader('CRUD Scaffolder', "Generating CRUD for {$name}");

        // 1. Generate Model & Migration
        $this->step(1, 4, "Generating Model and Migration for {$name}...");
        $this->call('make:model', [$name, '--migration']);

        // 2. Generate Resource Controller
        $this->step(2, 4, "Generating Resource Controller...");
        $this->call('make:controller', ["{$name}Controller", "--resource", "--model={$name}"]);

        // 3. Generate Views
        $this->step(3, 4, "Generating Views...");
        $this->generateViews($name, $tableName);

        // 4. Finalize
        $this->step(4, 4, "Finalizing CRUD...");
        
        $this->newLine();
        $this->box(
            "CRUD for '{$name}' generated successfully!\n\n" .
            "Model: App\\Models\\{$name}\n" .
            "Controller: App\\Http\\Controllers\\{$name}Controller\n" .
            "Views: resources/views/{$tableName}/*.plug.php",
            "✅ Success",
            "success"
        );

        $this->section('Next Steps');
        $this->numberedList([
            "Update the migration file in database/migrations/",
            "Run 'php theplugs migrate' to update the database",
            "Register the resource route: Route::resource('{$tableName}', {$name}Controller::class)",
        ]);

        return self::SUCCESS;
    }

    protected function generateViews(string $name, string $tableName): void
    {
        $viewPath = BASE_PATH . "resources/views/{$tableName}";
        Filesystem::ensureDir($viewPath);

        $stubs = ['index', 'create', 'edit', 'show'];
        $stubPath = BASE_PATH . 'stubs/crud';

        foreach ($stubs as $stub) {
            $content = $this->loadStub($stubPath . "/{$stub}.stub");
            $content = $this->replacePlaceholders($content, $name, $tableName);

            $filePath = "{$viewPath}/{$stub}.plug.php";
            Filesystem::put($filePath, $content);
            $this->success("  ✓ View created: {$tableName}/{$stub}.plug.php");
        }
    }

    protected function loadStub(string $path): string
    {
        if (Filesystem::exists($path)) {
            return Filesystem::get($path);
        }

        // Fallback or throw error? For now, we expect stubs to exist.
        return "";
    }

    protected function replacePlaceholders(string $content, string $name, string $tableName): string
    {
        $replacements = [
            '{{ $title }}' => Str::pluralize($name),
            '{{ $singularTitle }}' => $name,
            '{{ $routePrefix }}' => $tableName,
            '{{ $tableName }}' => $tableName,
        ];

        return str_replace(array_keys($replacements), array_values($replacements), $content);
    }
}
