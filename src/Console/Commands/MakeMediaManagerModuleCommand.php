<?php

declare(strict_types=1);

namespace Plugs\Console\Commands;

use Plugs\Console\Command;
use Plugs\Console\Support\Filesystem;
use Plugs\Console\Support\Str;
use Plugs\Console\Traits\RegistersModules;

class MakeMediaManagerModuleCommand extends Command
{
    use RegistersModules;
    protected string $description = 'Create a Media Manager module for file uploads and library management';

    protected function defineArguments(): array
    {
        return [
            'name' => 'The name of the module (default: MediaManager)',
        ];
    }

    protected function defineOptions(): array
    {
        return [
            '--force' => 'Overwrite existing module files',
        ];
    }

    public function handle(): int
    {
        $this->advancedHeader('Media Manager Module Generator', 'Scaffolding centralized media libraries');

        $name = $this->argument('0') ?: 'MediaManager';
        $lowerName = strtolower($name);
        $basePath = getcwd() . '/modules/' . $name;

        if (Filesystem::isDirectory($basePath) && !$this->hasOption('force')) {
            $this->error("Module '{$name}' already exists at modules/{$name}/");
            $this->note("Use --force to overwrite existing files.");
            return 1;
        }

        $this->task('Creating directory structure', function () use ($basePath) {
            $directories = [
                $basePath,
                $basePath . '/Controllers',
                $basePath . '/Migrations',
                $basePath . '/Models',
                $basePath . '/Repositories',
                $basePath . '/Services',
                $basePath . '/Requests',
                $basePath . '/Routes',
                $basePath . '/Views/admin',
            ];

            foreach ($directories as $dir) {
                Filesystem::ensureDir($dir);
            }
        });

        $stubsPath = __DIR__ . '/../Stubs/MediaManager';

        $filesToGenerate = [
            'MediaManagerModule.php.stub' => $name . 'Module.php',
            'Controllers/AdminMediaManagerController.php.stub' => 'Controllers/AdminMediaManagerController.php',
            'Models/Media.php.stub' => 'Models/Media.php',
            'Repositories/MediaManagerRepository.php.stub' => 'Repositories/MediaManagerRepository.php',
            'Services/MediaManagerService.php.stub' => 'Services/MediaManagerService.php',
            'Routes/web.php.stub' => 'Routes/web.php',
            'module.json.stub' => 'module.json',
        ];

        $this->output->section('Generating Module Files');

        foreach ($filesToGenerate as $stub => $destination) {
            $stubFile = $stubsPath . '/' . $stub;
            if (!Filesystem::exists($stubFile)) {
                $this->warning("Stub not found: {$stub}");
                continue;
            }

            $content = Filesystem::get($stubFile);
            $content = str_replace(
                ['{{name}}', '{{lowerName}}'],
                [$name, $lowerName],
                $content
            );
            $fullPath = $basePath . '/' . $destination;
            Filesystem::put($fullPath, $content);
            $this->fileCreated($fullPath);
        }

        $this->newLine();
        $this->resultSummary([
            'Module' => $name,
            'Location' => "modules/{$name}/",
            'Namespace' => "Modules\\{$name}\\"
        ], $this->elapsed());

        $this->newLine();
        $this->note("The Media Manager module has been automatically registered in config/modules.php.");

        // Register the module in the config file
        $this->registerModuleInConfig($name);

        return 0;
    }
}
