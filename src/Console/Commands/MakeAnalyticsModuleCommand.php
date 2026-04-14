<?php

declare(strict_types=1);

namespace Plugs\Console\Commands;

use Plugs\Console\Command;
use Plugs\Console\Support\Filesystem;
use Plugs\Console\Support\Str;
use Plugs\Console\Traits\RegistersModules;

class MakeAnalyticsModuleCommand extends Command
{
    use RegistersModules;
    protected string $description = 'Create a comprehensive Analytics module for activity monitoring';

    protected function defineArguments(): array
    {
        return [
            'name' => 'The name of the module (default: Analytics)',
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
        $this->advancedHeader('Analytics Module Generator', 'Scaffolding real-time activity monitoring systems');

        $name = $this->argument('0') ?: 'Analytics';
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

        $stubsPath = __DIR__ . '/../Stubs/Analytics';

        $filesToGenerate = [
            'AnalyticsModule.php.stub' => $name . 'Module.php',
            'Controllers/AdminAnalyticsController.php.stub' => 'Controllers/AdminAnalyticsController.php',
            'Services/AnalyticsService.php.stub' => 'Services/AnalyticsService.php',
            'Routes/web.php.stub' => 'Routes/web.php',
            'Views/admin/dashboard.plug.php.stub' => 'Views/admin/dashboard.plug.php',
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
        $this->note("The Analytics module has been automatically registered in config/modules.php.");

        // Register the module in the config file
        $this->registerModuleInConfig($name);

        // Run migrations
        $this->call('migrate', ['force' => true]);

        return 0;
    }
}
