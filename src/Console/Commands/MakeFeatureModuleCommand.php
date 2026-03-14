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
}
