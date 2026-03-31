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
            '--no-services' => 'Skip creating Services directory',
            '--no-repositories' => 'Skip creating Repositories directory',
            '--no-requests' => 'Skip creating Requests directory',
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
            $basePath . '/Views',
        ];

        if (!$this->hasOption('no-routes')) {
            $directories[] = $basePath . '/Routes';
        }

        if (!$this->hasOption('no-migrations')) {
            $directories[] = $basePath . '/Migrations';
        }

        if (!$this->hasOption('no-services')) {
            $directories[] = $basePath . '/Services';
        }

        if (!$this->hasOption('no-repositories')) {
            $directories[] = $basePath . '/Repositories';
        }

        if (!$this->hasOption('no-requests')) {
            $directories[] = $basePath . '/Requests';
        }

        foreach ($directories as $dir) {
            Filesystem::ensureDir($dir);
        }

        // Create Controllers
        $controllerContent = $this->generateControllerClass($name);
        Filesystem::put($basePath . '/Controllers/' . $name . 'Controller.php', $controllerContent);
        $this->success("  ✓ Controllers/{$name}Controller.php");

        // Create Models
        $modelContent = $this->generateModelClass($name);
        Filesystem::put($basePath . '/Models/' . $name . '.php', $modelContent);
        $this->success("  ✓ Models/{$name}.php");

        // Create Services
        if (!$this->hasOption('no-services')) {
            $serviceContent = $this->generateServiceClass($name);
            Filesystem::put($basePath . '/Services/' . $name . 'Service.php', $serviceContent);
            $this->success("  ✓ Services/{$name}Service.php");
        }

        // Create Repositories
        if (!$this->hasOption('no-repositories')) {
            $repoContent = $this->generateRepositoryClass($name);
            Filesystem::put($basePath . '/Repositories/' . $name . 'Repository.php', $repoContent);
            $this->success("  ✓ Repositories/{$name}Repository.php");
        }

        // Create Requests
        if (!$this->hasOption('no-requests')) {
            $requestContent = $this->generateRequestClass($name);
            Filesystem::put($basePath . '/Requests/' . $name . 'Request.php', $requestContent);
            $this->success("  ✓ Requests/{$name}Request.php");
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

        // Create the module class (Service Provider)
        $moduleContent = $this->generateModuleClass($name);
        Filesystem::put($basePath . '/' . $name . 'Module.php', $moduleContent);
        $this->success("  ✓ {$name}Module.php");

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
        $this->info("  ├── Controllers/");
        $this->info("  │   └── {$name}Controller.php  (Sample Controller)");
        $this->info("  ├── Models/");
        $this->info("  │   └── {$name}.php            (Sample Model)");
        
        if (!$this->hasOption('no-services')) {
            $this->info("  ├── Services/");
            $this->info("  │   └── {$name}Service.php     (Business Logic)");
        }
        
        if (!$this->hasOption('no-repositories')) {
            $this->info("  ├── Repositories/");
            $this->info("  │   └── {$name}Repository.php  (Data Access)");
        }
        
        if (!$this->hasOption('no-requests')) {
            $this->info("  ├── Requests/");
            $this->info("  │   └── {$name}Request.php     (Input Validation)");
        }

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
            "Update your business logic in modules/{$name}/Services/",
            "Implement data queries in modules/{$name}/Repositories/",
            "Define validation rules in modules/{$name}/Requests/",
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
        return ['web'];
    }

    /**
     * Register services in the container.
     */
    public function register(Container \$container): void
    {
        \$container->singleton('{$name}Repository', fn() => new Repositories\\{$name}Repository());
        \$container->singleton('{$name}Service', fn() => new Services\\{$name}Service(\$container->get('{$name}Repository')));
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

    private function generateControllerClass(string $name): string
    {
        $viewName = strtolower($name);

        return <<<PHP
<?php

declare(strict_types=1);

namespace Modules\\{$name}\\Controllers;

use Modules\\{$name}\\Services\\{$name}Service;
use Modules\\{$name}\\Requests\\{$name}Request;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;

class {$name}Controller
{
    public function __construct(
        protected {$name}Service \$service = new {$name}Service()
    ) {}

    /**
     * Display the module index page.
     */
    public function index(ServerRequestInterface \$request): ResponseInterface
    {
        \$items = \$this->service->getAll();
        // Render view using module namespace
        return view('{$viewName}::index', ['items' => \$items]);
    }

    /**
     * Handle a sample request.
     */
    public function store(ServerRequestInterface \$request): ResponseInterface
    {
        \$validated = new {$name}Request(\$request);

        if (!\$validated->isValid()) {
            // Handle validation failure
            return back()->withError('Validation failed');
        }

        \$this->service->create(\$request->getParsedBody());

        return back()->with('success', '{$name} created successfully!');
    }
}
PHP;
    }

    private function generateModelClass(string $name): string
    {
        $table = Str::snake(Str::pluralize($name));

        return <<<PHP
<?php

declare(strict_types=1);

namespace Modules\\{$name}\\Models;

use Plugs\\Base\\Model\\PlugModel;

class {$name} extends PlugModel
{
    /**
     * The table associated with the model.
     */
    protected string \$table = '{$table}';

    /**
     * The attributes that are mass assignable.
     */
    protected array \$fillable = [];
}
PHP;
    }

    private function generateServiceClass(string $name): string
    {
        return <<<PHP
<?php

declare(strict_types=1);

namespace Modules\\{$name}\\Services;

use Modules\\{$name}\\Repositories\\{$name}Repository;

class {$name}Service
{
    public function __construct(
        protected {$name}Repository \$repository = new {$name}Repository()
    ) {}

    /**
     * Get all items.
     */
    public function getAll(): array
    {
        return \$this->repository->all();
    }

    /**
     * Create a new item.
     */
    public function create(array \$data): mixed
    {
        return \$this->repository->create(\$data);
    }
}
PHP;
    }

    private function generateRepositoryClass(string $name): string
    {
        return <<<PHP
<?php

declare(strict_types=1);

namespace Modules\\{$name}\\Repositories;

use Modules\\{$name}\\Models\\{$name};

class {$name}Repository
{
    /**
     * Get all records.
     */
    public function all(): array
    {
        return {$name}::all();
    }

    /**
     * Create a new record.
     */
    public function create(array \$data): {$name}
    {
        return {$name}::create(\$data);
    }

    /**
     * Find a record by ID.
     */
    public function find(int \$id): ?{$name}
    {
        return {$name}::find(\$id);
    }
}
PHP;
    }

    private function generateRequestClass(string $name): string
    {
        return <<<PHP
<?php

declare(strict_types=1);

namespace Modules\\{$name}\\Requests;

use Psr\Http\Message\ServerRequestInterface;

class {$name}Request
{
    public array \$errors = [];
    protected array \$data = [];

    public function __construct(ServerRequestInterface \$request)
    {
        \$this->data = \$request->getParsedBody() ?? [];
        \$this->validate();
    }

    /**
     * Validate the request data.
     */
    protected function validate(): void
    {
        // Example validation
        if (empty(\$this->data['name'] ?? '')) {
            \$this->errors['name'] = 'The name field is required.';
        }
    }

    /**
     * Check if the request is valid.
     */
    public function isValid(): bool
    {
        return empty(\$this->errors);
    }

    /**
     * Get the validated data.
     */
    public function all(): array
    {
        return \$this->data;
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
*/

use Plugs\\Facades\\Route;
use Modules\\{$name}\\Controllers\\{$name}Controller;

Route::get('/', [{$name}Controller::class, 'index'])->name('index');
Route::post('/', [{$name}Controller::class, 'store'])->name('store');
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
*/

use Plugs\\Facades\\Route;
use Modules\\{$name}\\Controllers\\{$name}Controller;

Route::get('/', [{$name}Controller::class, 'index']);
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
