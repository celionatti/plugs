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

class MakeModelCommand extends Command
{
    protected string $description = 'Create a new model class';

    public function handle(): int
    {
        $name = $this->argument('0') ?? $this->ask('Model name', 'User');
        
        $createMigration = $this->confirm('Create migration?', false);
        $createController = $this->confirm('Create controller?', false);
        
        $this->checkpoint('options_selected');
        
        $content = $this->generateModel($name);
        $path = $this->getModelPath($name);
        
        if (Filesystem::exists($path) && !$this->confirm('Model exists. Overwrite?', false)) {
            $this->warning('Operation cancelled');
            return 0;
        }
        
        Filesystem::put($path, $content);
        $this->success("Model created: {$path}");
        
        if ($createMigration) {
            $tableName = Str::snake(Str::pluralize($name));
            $this->call('make:migration', ['0' => "create_{$tableName}_table"]);
        }
        
        if ($createController) {
            $this->call('make:controller', ['0' => "{$name}Controller"]);
        }
        
        return 0;
    }

    private function generateModel(string $name): string
    {
        $tableName = Str::snake(Str::pluralize($name));
        
        return <<<PHP
        <?php

        declare(strict_types=1);

        namespace App\\Models;

        use Plugs\\Database\\Model;

        class {$name} extends Model
        {
            protected string \$table = '{$tableName}';
            
            protected array \$fillable = [];
            
            protected array \$hidden = [];
            
            protected array \$casts = [];
        }

        PHP;
    }

    private function getModelPath(string $name): string
    {
        return getcwd() . '/app/Models/' . $name . '.php';
    }
}