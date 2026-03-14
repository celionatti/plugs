<?php

declare(strict_types=1);

namespace Plugs\Console\Commands;

use Plugs\Console\Command;
use Plugs\Console\Support\Filesystem;
use Plugs\Console\Support\Str;

class MakeFeatureModuleCommand extends Command
{
    protected string $description = 'Create a new feature module (mini-app with Controllers, Models, Routes, Migrations)';

    protected function defineArguments(): array
    {
        return [
            'name' => 'The name of the feature module (e.g., Auth, Store, Blog)',
        ];
    }

    protected function defineOptions(): array
    {
        return [
            '--force' => 'Overwrite existing module files',
            '--no-routes' => 'Skip creating route files',
            '--no-migrations' => 'Skip creating Migrations directory',
            '--prefix' => 'Custom URL prefix for the module routes (default: lowercase module name)',
        ];
    }

    public function handle(): int
    {
        $this->advancedHeader('Feature Module Generator', 'Scaffolding self-contained application modules');

        $name = $this->argument('0');

        if (!$name) {
            $name = $this->ask('Module name (e.g., Auth, Store, Blog)', 'Feature');
        }

        $name = Str::studly($name);
        $basePath = getcwd() . '/modules/' . $name;

        if (Filesystem::isDirectory($basePath) && !$this->hasOption('force')) {
            $this->error("Feature module '{$name}' already exists at modules/{$name}/");
            $this->note("Use --force to overwrite existing files.");

            return 1;
        }

        // Create directory structure
        $directories = [
            $basePath,
            $basePath . '/Controllers',
            $basePath . '/Models',
            $basePath . '/Views', // Added Views directory
        ];

        if (!$this->hasOption('no-routes')) {
            $directories[] = $basePath . '/Routes';
        }

        if (!$this->hasOption('no-migrations')) {
            $directories[] = $basePath . '/Migrations';
        }

        foreach ($directories as $dir) {
            Filesystem::ensureDir($dir);
        }

        // Create module service provider
        $moduleContent = $this->generateModuleClass($name);
        Filesystem::put($basePath . '/' . $name . 'Module.php', $moduleContent);
        $this->success("  ✓ {$name}Module.php");

        // Create .gitkeep files for empty directories
        foreach (['Controllers', 'Models'] as $dir) {
            $gitkeep = $basePath . '/' . $dir . '/.gitkeep';
            if (!Filesystem::exists($gitkeep)) {
                Filesystem::put($gitkeep, '');
            }
            $this->info("  ↳ {$dir}/.gitkeep");
        }

        if (!$this->hasOption('no-migrations')) {
            $gitkeep = $basePath . '/Migrations/.gitkeep';
            if (!Filesystem::exists($gitkeep)) {
                Filesystem::put($gitkeep, '');
            }
            $this->info("  ↳ Migrations/.gitkeep");
        }

        // Create route files
        if (!$this->hasOption('no-routes')) {
            $webRouteContent = $this->generateWebRouteFile($name);
            Filesystem::put($basePath . '/Routes/web.php', $webRouteContent);
            $this->success("  ✓ Routes/web.php");

            $apiRouteContent = $this->generateApiRouteFile($name);
            Filesystem::put($basePath . '/Routes/api.php', $apiRouteContent);
            $this->success("  ✓ Routes/api.php");
        }

        // Create sample view template
        $viewContent = $this->generateViewFile($name);
        Filesystem::put($basePath . '/Views/index.plug.php', $viewContent);
        $this->success("  ✓ Views/index.plug.php");

        $this->newLine();
        $this->box(
            "Feature module '{$name}' created successfully!\n\n" .
            "Location: modules/{$name}/\n" .
            "Namespace: Modules\\{$name}\\",
            "✅ Module Created",
            "success"
        );

        $prefix = $this->option('prefix') ?: strtolower($name);

        $this->newLine();
        $this->section('Module Structure');
        $this->info("  modules/{$name}/");
        $this->info("  ├── {$name}Module.php          (Service Provider)");
        $this->info("  ├── Controllers/               (Your controllers)");
        $this->info("  ├── Models/                    (Your models)");
        if (!$this->hasOption('no-routes')) {
            $this->info("  ├── Routes/");
            $this->info("  │   ├── web.php                (Web routes → /{$prefix}/...)");
            $this->info("  │   └── api.php                (API routes → /api/{$prefix}/...)");
        }
        $this->info("  ├── Views/");
        $this->info("  │   └── index.plug.php         (Sample view → view('{$prefix}::index'))");
        if (!$this->hasOption('no-migrations')) {
            $this->info("  └── Migrations/                (Database migrations)");
        }

        $this->newLine();
        $this->section('Next Steps');
        $this->numberedList([
            "Add controllers to modules/{$name}/Controllers/",
            "Add models to modules/{$name}/Models/",
            "Define routes in modules/{$name}/Routes/web.php and api.php",
            "Add migrations to modules/{$name}/Migrations/",
            "Run `composer dump-autoload` to update autoloading",
        ]);

        $this->newLine();
        $this->note("The module will be auto-discovered on next request. No manual registration needed!");

        return 0;
    }

    private function generateModuleClass(string $name): string
    {
        $prefix = strtolower($name);

        return <<<PHP
<?php

declare(strict_types=1);

namespace Modules\\{$name};

use Plugs\\FeatureModule\\AbstractFeatureModule;
use Plugs\\Container\\Container;
use Plugs\\Plugs;

class {$name}Module extends AbstractFeatureModule
{
    /**
     * Get the unique name for this module.
     */
    public function getName(): string
    {
        return '{$name}';
    }

    /**
     * Get the URL prefix for this module's routes.
     * Return '' for no prefix (routes at root level).
     */
    public function getRoutePrefix(): string
    {
        return '{$prefix}';
    }

    /**
     * Get middleware to apply to all routes in this module.
     *
     * @return string[]
     */
    public function getMiddleware(): array
    {
        return [];
    }

    /**
     * Register services in the container.
     */
    public function register(Container \$container): void
    {
        // Example: \$container->singleton('{$name}Service', fn() => new Services\\{$name}Service());
    }

    /**
     * Boot the module after all modules have been registered.
     */
    public function boot(Plugs \$app): void
    {
        // Any boot-time logic (event listeners, etc.)
    }
}
PHP;
    }

    private function generateWebRouteFile(string $name): string
    {
        $prefix = strtolower($name);

        return <<<PHP
<?php

declare(strict_types=1);

/*
|--------------------------------------------------------------------------
| {$name} Module — Web Routes
|--------------------------------------------------------------------------
|
| Routes defined here are automatically prefixed with '/{$prefix}'
| and namespaced to Modules\\{$name}\\Controllers.
|
| Example:
|   Route::get('/', [DashboardController::class, 'index']);
|   → Accessible at: /{$prefix}/
|
*/

use Plugs\\Facades\\Route;

// Define your web routes here

PHP;
    }

    private function generateApiRouteFile(string $name): string
    {
        $prefix = strtolower($name);

        return <<<PHP
<?php

declare(strict_types=1);

/*
|--------------------------------------------------------------------------
| {$name} Module — API Routes
|--------------------------------------------------------------------------
|
| Routes defined here are automatically prefixed with '/api/{$prefix}'
| and namespaced to Modules\\{$name}\\Controllers.
|
| Example:
|   Route::get('/items', [ItemController::class, 'index']);
|   → Accessible at: /api/{$prefix}/items
|
*/

use Plugs\\Facades\\Route;

// Define your API routes here

PHP;
    }

    private function generateViewFile(string $name): string
    {
        $namespace = strtolower($name);

        return <<<PHP
@extends('layouts.app')

@section('title', '{$name} Module')

@section('content')
<div class="container py-8">
    <div class="bg-white dark:bg-gray-800 rounded-lg shadow-sm border border-gray-200 dark:border-gray-700 p-8">
        <div class="flex items-center gap-4 mb-6">
            <div class="w-12 h-12 bg-blue-500 rounded-lg flex items-center justify-center text-white shadow-lg shadow-blue-500/20">
                <svg xmlns="http://www.w3.org/2000/svg" class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 11H5m14 0a2 2 0 012 2v6a2 2 0 01-2 2H5a2 2 0 01-2-2v-6a2 2 0 012-2m14 0V9a2 2 0 00-2-2M5 11V9a2 2 0 002-2m0 0V5a2 2 0 012-2h6a2 2 0 012 2v2M7 7h10" />
                </svg>
            </div>
            <h1 class="text-3xl font-extrabold text-gray-900 dark:text-white">Welcome to the {$name} Module</h1>
        </div>

        <p class="text-lg text-gray-600 dark:text-gray-400 mb-8">
            Modular architecture allows you to build self-contained features that act like mini-applications.
            This view is located at <span class="px-2 py-1 bg-gray-100 dark:bg-gray-900 rounded font-mono text-sm">modules/{$name}/Views/index.plug.php</span>.
        </p>

        <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
            <div class="p-6 bg-gradient-to-br from-blue-50 to-indigo-50 dark:from-blue-900/10 dark:to-indigo-900/10 rounded-xl border border-blue-100 dark:border-blue-800">
                <h3 class="font-bold text-blue-900 dark:text-blue-200 mb-2">Rendering this view</h3>
                <p class="text-sm text-blue-800/80 dark:text-blue-300/80 mb-4">You can return this view from your controller using the module namespace:</p>
                <code class="block p-3 bg-white dark:bg-gray-900 rounded border border-blue-200 dark:border-blue-800 text-blue-600 dark:text-blue-400 text-sm">
                    return view('{$namespace}::index');
                </code>
            </div>

            <div class="p-6 bg-gradient-to-br from-purple-50 to-pink-50 dark:from-purple-900/10 dark:to-pink-900/10 rounded-xl border border-purple-100 dark:border-purple-800">
                <h3 class="font-bold text-purple-900 dark:text-purple-200 mb-2">Next Steps</h3>
                <ul class="text-sm text-purple-800/80 dark:text-purple-300/80 space-y-2">
                    <li class="flex items-center gap-2">
                        <span class="w-1.5 h-1.5 bg-purple-400 rounded-full"></span>
                        Define routes in <code>Routes/web.php</code>
                    </li>
                    <li class="flex items-center gap-2">
                        <span class="w-1.5 h-1.5 bg-purple-400 rounded-full"></span>
                        Create a controller in <code>Controllers/</code>
                    </li>
                    <li class="flex items-center gap-2">
                        <span class="w-1.5 h-1.5 bg-purple-400 rounded-full"></span>
                        Build your feature!
                    </li>
                </ul>
            </div>
        </div>
    </div>
</div>
@endsection
PHP;
    }
}
