<?php

declare(strict_types=1);

namespace Plugs\Console\Commands;

use Plugs\Console\Command;
use Plugs\Console\Support\Filesystem;
use Plugs\Console\Support\Str;
use Plugs\Console\Traits\RegistersModules;

class MakeNewsletterModuleCommand extends Command
{
    use RegistersModules;
    protected string $description = 'Create a Newsletter module for managing subscribers and campaigns';

    protected function defineArguments(): array
    {
        return [
            'name' => 'The name of the module (default: Newsletter)',
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
        $this->advancedHeader('Newsletter Module Generator', 'Scaffolding subscriber & campaign systems');

        $name = $this->argument('0') ?: 'Newsletter';
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

        $stubsPath = __DIR__ . '/../Stubs/Newsletter';

        $filesToGenerate = [
            'NewsletterModule.php.stub' => $name . 'Module.php',
            'Controllers/AdminNewsletterController.php.stub' => 'Controllers/AdminNewsletterController.php',
            'Models/Subscriber.php.stub' => 'Models/Subscriber.php',
            'Models/Campaign.php.stub' => 'Models/Campaign.php',
            'Models/Newsletter.php.stub' => 'Models/Newsletter.php',
            'Repositories/NewsletterRepository.php.stub' => 'Repositories/NewsletterRepository.php',
            'Services/NewsletterService.php.stub' => 'Services/NewsletterService.php',
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
        $this->note("The Newsletter module has been automatically registered in config/modules.php.");

        // Register the module in the config file
        $this->registerModuleInConfig($name);

        // Run migrations
        $this->call('migrate', ['force' => true]);

        return 0;
    }
}
