<?php

declare(strict_types=1);

namespace Plugs\Console\Commands;

use Plugs\Console\Command;
use Plugs\Console\Support\Filesystem;
use Plugs\Console\Support\Str;
use Plugs\Console\Traits\RegistersModules;

class MakeAIAssistantModuleCommand extends Command
{
    use RegistersModules;
    protected string $description = 'Create a dedicated AI Assistant module for content generation';

    protected function defineArguments(): array
    {
        return [
            'name' => 'The name of the module (default: AIAssistant)',
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
        $this->advancedHeader('AI Assistant Module Generator', 'Scaffolding AI-powered assistance systems');

        $name = $this->argument('0') ?: 'AIAssistant';
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
                $basePath . '/Services',
                $basePath . '/Requests',
                $basePath . '/Routes',
                $basePath . '/Views/admin',
            ];

            foreach ($directories as $dir) {
                Filesystem::ensureDir($dir);
            }
        });

        $stubsPath = __DIR__ . '/../Stubs/AIAssistant';

        $filesToGenerate = [
            'AIAssistantModule.php.stub' => $name . 'Module.php',
            'Controllers/AdminAIAssistantController.php.stub' => 'Controllers/AdminAIAssistantController.php',
            'Services/AIHelperService.php.stub' => 'Services/AIHelperService.php',
            'Routes/web.php.stub' => 'Routes/web.php',
            'Views/admin/playground.plug.php.stub' => 'Views/admin/playground.plug.php',
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
        $this->note("The AI Assistant module has been automatically registered in config/modules.php.");

        // Register the module in the config file
        $this->registerModuleInConfig($name);

        return 0;
    }
}
