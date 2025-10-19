<?php

declare(strict_types=1);

namespace Plugs\Console\Commands;

/*
|--------------------------------------------------------------------------
| Make: Model Command
|--------------------------------------------------------------------------
*/

use Plugs\Console\Command;
use Plugs\Console\Support\Str;
use Plugs\Console\Support\Filesystem;

class makemodel extends Command
{
    protected string $description = 'Create a new model class';

    public function handle(): int
    {
        $this->checkpoint('start');
        
        $name = $this->argument('0');
        
        if (!$name) {
            $name = $this->ask('What should we name the model?', 'User');
        }
        
        $name = Str::studly($name);
        
        $this->info("Creating model: {$name}");
        $this->line();
        
        $addTimestamps = $this->confirm('Add timestamps (created_at, updated_at)?', true);
        $addSoftDeletes = $this->confirm('Add soft deletes?', false);
        
        $tableName = $this->ask(
            'What is the table name?', 
            Str::snake(Str::pluralStudly($name))
        );
        
        $fillable = [];
        if ($this->confirm('Add fillable properties?', true)) {
            $fillableInput = $this->ask('Enter fillable fields (comma-separated)', 'name,email');
            $fillable = array_map('trim', explode(',', $fillableInput));
        }
        
        $this->checkpoint('options_collected');
        
        $this->line();
        $this->section('Generating Model');
        
        $content = $this->task('Building model structure', function() use ($name, $tableName, $fillable, $addTimestamps, $addSoftDeletes) {
            return $this->generateModel($name, $tableName, $fillable, $addTimestamps, $addSoftDeletes);
        });
        
        $path = $this->getModelPath($name);
        $directory = dirname($path);
        
        if (Filesystem::exists($path)) {
            $this->warning("Model already exists: {$path}");
            
            if (!$this->confirm('Do you want to overwrite it?', false)) {
                $this->info('Operation cancelled.');
                return 0;
            }
        }
        
        $this->task('Creating directory structure', function() use ($directory) {
            Filesystem::ensureDir($directory);
        });
        
        $this->task('Writing model file', function() use ($path, $content) {
            Filesystem::put($path, $content);
        });
        
        $this->line();
        $this->displayModelSummary($name, $path, $tableName, $fillable);
        
        if ($this->isVerbose()) {
            $this->displayTimings();
        }
        
        return 0;
    }

    private function generateModel(
        string $name,
        string $tableName,
        array $fillable,
        bool $addTimestamps,
        bool $addSoftDeletes
    ): string {
        $content = "<?php\n\ndeclare(strict_types=1);\n\n";
        $content .= "namespace App\\Models;\n\n";
        
        if ($addSoftDeletes) {
            $content .= "use Plugs\\Model;\n";
            $content .= "use Plugs\\Database\\SoftDeletes;\n\n";
        } else {
            $content .= "use Plugs\\Model;\n\n";
        }
        
        $content .= "/**\n";
        $content .= " * {$name} Model\n";
        $content .= " * \n";
        $content .= " * @table {$tableName}\n";
        $content .= " * @created " . date('Y-m-d H:i:s') . "\n";
        $content .= " */\n";
        $content .= "class {$name} extends Model\n{\n";
        
        if ($addSoftDeletes) {
            $content .= "    use SoftDeletes;\n\n";
        }
        
        $content .= "    protected string \$table = '{$tableName}';\n\n";
        
        if (!empty($fillable)) {
            $content .= "    protected array \$fillable = [\n";
            foreach ($fillable as $field) {
                $content .= "        '{$field}',\n";
            }
            $content .= "    ];\n\n";
        }
        
        $content .= "    protected bool \$timestamps = " . ($addTimestamps ? 'true' : 'false') . ";\n";
        
        $content .= "}\n";
        
        return $content;
    }

    private function getModelPath(string $name): string
    {
        $basePath = getcwd() . '/app/Models';
        return $basePath . '/' . $name . '.php';
    }

    private function displayModelSummary(string $name, string $path, string $tableName, array $fillable): void
    {
        $this->box(
            "Model created successfully!\n\n" .
            "Name: {$name}\n" .
            "Table: {$tableName}\n" .
            "Path: {$path}\n" .
            "Fillable Fields: " . (empty($fillable) ? 'None' : count($fillable)),
            "✅ Success",
            "success"
        );
        
        if (!empty($fillable)) {
            $this->line();
            $this->section('Fillable Properties');
            
            foreach ($fillable as $field) {
                $this->success("  • {$field}");
            }
        }
        
        $this->line();
        $this->note("Remember to create the corresponding database migration!");
    }
}