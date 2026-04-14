<?php

declare(strict_types=1);

namespace Plugs\Console\Commands;

use Plugs\Console\Command;
use Plugs\Console\Support\Filesystem;
use Plugs\Console\Support\Str;
use Plugs\Console\Traits\RegistersModules;

class MakeArticleModuleCommand extends Command
{
    use RegistersModules;
    protected string $description = 'Create a dedicated Article module with images and author relationships';

    protected function defineArguments(): array
    {
        return [
            'name' => 'The name of the module (default: Article)',
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
        $this->advancedHeader('Article Module Generator', 'Scaffolding full article management systems');

        $name = $this->argument('0') ?: 'Article';
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
                $basePath . '/Controllers/Admin',
                $basePath . '/Migrations',
                $basePath . '/Models',
                $basePath . '/Services',
                $basePath . '/Requests',
                $basePath . '/Routes',
                $basePath . '/Views/admin/articles',
            ];

            foreach ($directories as $dir) {
                Filesystem::ensureDir($dir);
            }
        });

        $stubsPath = __DIR__ . '/../Stubs/Article';

        $filesToGenerate = [
            'ArticleModule.php.stub' => $name . 'Module.php',
            'Models/Article.php.stub' => 'Models/Article.php',
            'Controllers/Admin/ArticleController.php.stub' => 'Controllers/Admin/ArticleController.php',
            'Requests/ArticleRequest.php.stub' => 'Requests/ArticleRequest.php',
            'Services/ArticleService.php.stub' => 'Services/ArticleService.php',
            'Migrations/create_article_tables.php.stub' => 'Migrations/' . date('Y_m_d_His') . '_create_' . $lowerName . '_tables.php',
            'Routes/web.php.stub' => 'Routes/web.php',
            'Views/admin/articles/index.plug.php.stub' => 'Views/admin/articles/index.plug.php',
            'Views/admin/articles/create.plug.php.stub' => 'Views/admin/articles/create.plug.php',
            'Views/admin/articles/edit.plug.php.stub' => 'Views/admin/articles/edit.plug.php',
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

        // Ensure the icon component exists (required by article views)
        $this->task('Ensuring icon component exists', function () {
            $iconPath = getcwd() . '/resources/views/components/icon.plug.php';
            if (!Filesystem::exists($iconPath)) {
                Filesystem::ensureDir(dirname($iconPath));
                $iconContent = <<<'ICON'
<?php
$name = $name ?? '';
$class = $class ?? 'w-5 h-5';
?>
<i class="bi bi-<?= $name ?> <?= $class ?>" <?= $attributes ?? '' ?>></i>
ICON;
                Filesystem::put($iconPath, $iconContent);
                $this->fileCreated($iconPath);
            } else {
                $this->note('Icon component already exists.');
            }
        });

        $this->newLine();
        $this->resultSummary([
            'Module' => $name,
            'Location' => "modules/{$name}/",
            'Namespace' => "Modules\\{$name}\\"
        ], $this->elapsed());

        $this->newLine();
        $this->note("The Article module has been automatically registered in config/modules.php.");

        // Register the module in the config file
        $this->registerModuleInConfig($name);

        // Run migrations
        $this->call('migrate', ['force' => true]);

        return 0;
    }
}
