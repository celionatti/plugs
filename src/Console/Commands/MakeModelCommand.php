<?php

declare(strict_types=1);

namespace Plugs\Console\Commands;

use Plugs\Console\Command;
use Plugs\Console\Support\Filesystem;
use Plugs\Console\Support\Str;

/*
|--------------------------------------------------------------------------
| MakeModelCommand Class
|--------------------------------------------------------------------------
| Create new Eloquent model classes with various options
*/

class MakeModelCommand extends Command
{
    protected string $description = 'Create a new model class with advanced features';

    private string $templatePath;

    public function __construct(string $name)
    {
        parent::__construct($name);
        $this->templatePath = getcwd() . '/stubs';
    }

    protected function defineArguments(): array
    {
        return [
            'name' => 'The name of the model class',
        ];
    }

    protected function defineOptions(): array
    {
        return [
            '--migration, -m' => 'Create a migration file for the model',
            '--controller, -c' => 'Create a controller for the model',
            '--resource, -r' => 'Create a resource controller',
            '--api' => 'Create an API controller',
            '--factory, -f' => 'Create a factory for the model',
            '--seed, -s' => 'Create a seeder for the model',
            '--all, -a' => 'Create migration, factory, seeder, and controller',
            '--force' => 'Overwrite existing files',
            '--pivot' => 'Create a pivot model',
            '--soft-deletes' => 'Add soft deletes support',
            '--timestamps' => 'Add timestamps (enabled by default)',
            '--no-timestamps' => 'Disable timestamps',
            '--fillable=FIELDS' => 'Comma-separated fillable fields',
            '--hidden=FIELDS' => 'Comma-separated hidden fields',
            '--casts=FIELDS' => 'Comma-separated casts (field:type)',
            '--table=NAME' => 'Specify custom table name',
            '--connection=NAME' => 'Specify database connection',
        ];
    }

    public function handle(): int
    {
        $this->checkpoint('start');

        $this->title('Model Generator');

        // Get model name
        $name = $this->argument('0');

        if (!$name) {
            $name = $this->ask('Model name', 'User');
        }

        // Clean model name
        $name = Str::studly($name);

        $this->checkpoint('name_collected');

        // Interactive mode if no options provided
        $options = $this->gatherOptions($name);

        $this->checkpoint('options_collected');

        // Display summary
        $this->displaySummary($name, $options);

        if (!$this->confirm('Proceed with generation?', true)) {
            $this->warning('Model generation cancelled.');

            return 0;
        }

        $this->newLine();
        $this->section('Generating Files');

        // Generate model
        $modelPath = $this->generateModel($name, $options);

        $this->checkpoint('model_generated');

        $filesCreated = [$modelPath];

        // Generate related files
        if ($options['migration']) {
            $migrationPath = $this->generateMigration($name, $options);
            $filesCreated[] = $migrationPath;
        }

        if ($options['controller']) {
            $controllerPath = $this->generateController($name, $options);
            $filesCreated[] = $controllerPath;
        }

        if ($options['factory']) {
            $factoryPath = $this->generateFactory($name, $options);
            $filesCreated[] = $factoryPath;
        }

        if ($options['seeder']) {
            $seederPath = $this->generateSeeder($name, $options);
            $filesCreated[] = $seederPath;
        }

        if ($options['resource']) {
            $resourcePaths = $this->generateResource($name, $options);
            $filesCreated = array_merge($filesCreated, $resourcePaths);
        }

        $this->checkpoint('all_generated');

        // Display results
        $this->displayResults($name, $filesCreated, $options);

        if ($this->isVerbose()) {
            $this->displayTimings();
        }

        return 0;
    }

    private function gatherOptions(string $name): array
    {
        $options = [
            'migration' => $this->hasOption('migration') || $this->hasOption('m') || $this->hasOption('all') || $this->hasOption('a'),
            'controller' => $this->hasOption('controller') || $this->hasOption('c') || $this->hasOption('all') || $this->hasOption('a'),
            'factory' => $this->hasOption('factory') || $this->hasOption('f') || $this->hasOption('all') || $this->hasOption('a'),
            'seeder' => $this->hasOption('seed') || $this->hasOption('s') || $this->hasOption('all') || $this->hasOption('a'),
            'resource' => $this->hasOption('resource') || $this->hasOption('r') || $this->hasOption('all') || $this->hasOption('a'),
            'api' => $this->hasOption('api'),
            'pivot' => $this->hasOption('pivot'),
            'soft_deletes' => $this->hasOption('soft-deletes'),
            'timestamps' => !$this->hasOption('no-timestamps'),
            'force' => $this->isForce(),
            'fillable' => $this->option('fillable'),
            'hidden' => $this->option('hidden'),
            'casts' => $this->option('casts'),
            'table' => $this->option('table'),
            'connection' => $this->option('connection'),
        ];

        // Interactive mode if no CLI options
        if (!$this->hasAnyOption()) {
            $this->section('Configuration');

            $options['migration'] = $this->confirm('Create migration?', true);
            $options['controller'] = $this->confirm('Create controller?', false);

            if ($options['controller']) {
                $controllerType = $this->choice(
                    'Controller type?',
                    ['Basic', 'Resource', 'API'],
                    'Resource'
                );

                $options['resource'] = $controllerType === 'Resource';
                $options['api'] = $controllerType === 'API';
            }

            $options['factory'] = $this->confirm('Create factory?', false);
            $options['seeder'] = $this->confirm('Create seeder?', false);
            $options['soft_deletes'] = $this->confirm('Add soft deletes?', false);

            // Fields configuration
            if ($this->confirm('Configure fillable fields?', false)) {
                $fields = $this->ask('Enter fillable fields (comma-separated)', 'name,email');
                $options['fillable'] = $fields;
            }

            if ($this->confirm('Configure hidden fields?', false)) {
                $fields = $this->ask('Enter hidden fields (comma-separated)', 'password');
                $options['hidden'] = $fields;
            }

            if ($this->confirm('Configure casts?', false)) {
                $this->info('Enter casts in format: field:type (e.g., is_active:boolean,data:array)');
                $casts = $this->ask('Enter casts (comma-separated)');
                $options['casts'] = $casts;
            }
        }

        return $options;
    }

    private function generateModel(string $name, array $options): string
    {
        $this->task('Generating model class', function () {
            usleep(300000);
        });

        $path = $this->getModelPath($name);

        if (Filesystem::exists($path) && !$options['force']) {
            if (!$this->confirm("Model {$name} already exists. Overwrite?", false)) {
                $this->warning('Model generation skipped.');
                exit(0);
            }
        }

        // Load template
        $template = $this->loadTemplate('model.stub');

        // Replace placeholders
        $content = $this->populateTemplate($template, $name, $options);

        Filesystem::put($path, $content);

        $this->success("Model created: {$path}");

        return $path;
    }

    private function generateMigration(string $name, array $options): string
    {
        $this->task('Generating migration', function () {
            usleep(200000);
        });

        $tableName = $options['table'] ?? Str::snake(Str::pluralize($name));
        $timestamp = date('Y_m_d_His');
        $migrationName = "create_{$tableName}_table";
        $className = Str::studly($migrationName);

        $filename = "{$timestamp}_{$migrationName}.php";
        $path = $this->getMigrationPath($filename);

        // Load migration template
        $template = $this->loadTemplate($options['pivot'] ? 'migration.pivot.stub' : 'migration.stub');

        // Build fields
        $fields = $this->buildMigrationFields($options);

        $replacements = [
            '{{class}}' => $className,
            '{{table}}' => $tableName,
            '{{fields}}' => $fields,
        ];

        $content = str_replace(array_keys($replacements), array_values($replacements), $template);

        Filesystem::put($path, $content);

        $this->success("Migration created: {$filename}");

        return $path;
    }

    private function generateController(string $name, array $options): string
    {
        $this->task('Generating controller', function () {
            usleep(200000);
        });

        $controllerName = "{$name}Controller";
        $path = $this->getControllerPath($controllerName);

        // Determine template type
        $templateName = 'controller.stub';
        if ($options['api']) {
            $templateName = 'controller.api.stub';
        } elseif ($options['resource']) {
            $templateName = 'controller.resource.stub';
        }

        $template = $this->loadTemplate($templateName);

        $replacements = [
            '{{class}}' => $controllerName,
            '{{model}}' => $name,
            '{{modelVariable}}' => Str::camel($name),
            '{{modelNamespace}}' => 'App\\Models\\' . $name,
        ];

        $content = str_replace(array_keys($replacements), array_values($replacements), $template);

        Filesystem::put($path, $content);

        $this->success("Controller created: {$controllerName}");

        return $path;
    }

    private function generateFactory(string $name, array $options): string
    {
        $this->task('Generating factory', function () {
            usleep(200000);
        });

        $factoryName = "{$name}Factory";
        $path = $this->getFactoryPath($factoryName);

        $template = $this->loadTemplate('factory.stub');

        $replacements = [
            '{{class}}' => $factoryName,
            '{{model}}' => $name,
            '{{modelNamespace}}' => 'App\\Models\\' . $name,
            '{{definition}}' => $this->buildFactoryDefinition($options),
        ];

        $content = str_replace(array_keys($replacements), array_values($replacements), $template);

        Filesystem::put($path, $content);

        $this->success("Factory created: {$factoryName}");

        return $path;
    }

    private function generateSeeder(string $name, array $options): string
    {
        $this->task('Generating seeder', function () {
            usleep(200000);
        });

        $seederName = "{$name}Seeder";
        $path = $this->getSeederPath($seederName);

        $template = $this->loadTemplate('seeder.stub');

        $replacements = [
            '{{class}}' => $seederName,
            '{{model}}' => $name,
            '{{modelNamespace}}' => 'App\\Models\\' . $name,
        ];

        $content = str_replace(array_keys($replacements), array_values($replacements), $template);

        Filesystem::put($path, $content);

        $this->success("Seeder created: {$seederName}");

        return $path;
    }

    private function generateResource(string $name, array $options): array
    {
        $this->task('Generating API resource and collection', function () {
            usleep(300000);
        });

        $resourceName = "{$name}Resource";
        $collectionName = "{$name}Collection";

        $resourcePath = getcwd() . '/app/Http/Resources/' . $resourceName . '.php';
        $collectionPath = getcwd() . '/app/Http/Resources/' . $collectionName . '.php';

        $files = [];

        // Generate Resource
        $resourceTemplate = <<<PHP
<?php

declare(strict_types=1);

namespace App\Http\Resources;

use Plugs\Http\Resources\PlugResource;

/**
 * {$resourceName}
 *
 * API Resource for transforming {$name} data.
 * Generated by ThePlugs Console
 */
class {$resourceName} extends PlugResource
{
    /**
     * Transform the resource into an array.
     */
    public function toArray(): array
    {
        return [
            'id' => \$this->resource->id,
            // Add more fields...
            'created_at' => \$this->resource->created_at,
            'updated_at' => \$this->resource->updated_at,
        ];
    }
}
PHP;
        Filesystem::put($resourcePath, $resourceTemplate);
        $this->success("Resource created: {$resourceName}");
        $files[] = $resourcePath;

        // Generate Collection
        $collectionTemplate = <<<PHP
<?php

declare(strict_types=1);

namespace App\Http\Resources;

use Plugs\Http\Resources\PlugResourceCollection;

/**
 * {$collectionName}
 *
 * API Resource Collection for transforming multiple {$name} items.
 * Generated by ThePlugs Console
 */
class {$collectionName} extends PlugResourceCollection
{
    /**
     * The resource class this collection uses
     */
    protected string \$collects = {$resourceName}::class;
}
PHP;
        Filesystem::put($collectionPath, $collectionTemplate);
        $this->success("Collection created: {$collectionName}");
        $files[] = $collectionPath;

        return $files;
    }

    private function loadTemplate(string $templateName): string
    {
        $templatePath = $this->templatePath . '/' . $templateName;

        if (!Filesystem::exists($templatePath)) {
            // Use default inline template
            return $this->getDefaultTemplate($templateName);
        }

        return Filesystem::get($templatePath);
    }

    private function populateTemplate(string $template, string $name, array $options): string
    {
        $tableName = $options['table'] ?? Str::snake(Str::pluralize($name));
        $connection = $options['connection'] ?? 'default';

        // Build property arrays
        $fillable = $this->buildArrayProperty($options['fillable'] ?? '');
        $hidden = $this->buildArrayProperty($options['hidden'] ?? '');
        $casts = $this->buildCastsProperty($options['casts'] ?? '');

        // Build traits and imports
        $traits = [];
        $imports = [];

        if ($options['soft_deletes']) {
            $traits[] = 'SoftDeletes';
            $imports[] = 'use Plugs\Database\Traits\SoftDeletes;';
        }

        $traitsString = !empty($traits) ? 'use ' . implode(', ', $traits) . ';' : '';
        $importsString = !empty($imports) ? implode("\n", $imports) . "\n" : '';

        $replacements = [
            '{{class}}' => $name,
            '{{table}}' => $tableName,
            '{{connection}}' => $connection,
            '{{fillable}}' => $fillable,
            '{{hidden}}' => $hidden,
            '{{casts}}' => $casts,
            '{{traits}}' => $traitsString,
            '{{imports}}' => $importsString,
            '{{timestamps}}' => $options['timestamps'] ? 'true' : 'false',
        ];

        return str_replace(array_keys($replacements), array_values($replacements), $template);
    }

    private function buildMigrationFields(array $options): string
    {
        $fields = ["            \$table->id();"];

        // Add fillable fields as string columns
        if ($fillable = $options['fillable'] ?? '') {
            $fieldNames = array_map('trim', explode(',', $fillable));
            foreach ($fieldNames as $field) {
                $fields[] = "            \$table->string('{$field}');";
            }
        }

        // Add timestamps
        if ($options['timestamps']) {
            $fields[] = "            \$table->timestamps();";
        }

        // Add soft deletes
        if ($options['soft_deletes']) {
            $fields[] = "            \$table->softDeletes();";
        }

        return implode("\n", $fields);
    }

    private function buildArrayProperty(?string $fields): string
    {
        if (!$fields) {
            return '[]';
        }

        $items = array_map(function ($field) {
            return "'{$field}'";
        }, array_map('trim', explode(',', $fields)));

        if (count($items) <= 3) {
            return '[' . implode(', ', $items) . ']';
        }

        return "[\n        " . implode(",\n        ", $items) . ",\n    ]";
    }

    private function buildCastsProperty(?string $casts): string
    {
        if (!$casts) {
            return '[]';
        }

        $items = [];
        foreach (explode(',', $casts) as $cast) {
            [$field, $type] = array_map('trim', explode(':', $cast));
            $items[] = "'{$field}' => '{$type}'";
        }

        if (count($items) <= 2) {
            return '[' . implode(', ', $items) . ']';
        }

        return "[\n        " . implode(",\n        ", $items) . ",\n    ]";
    }

    private function buildFactoryDefinition(array $options): string
    {
        $fields = [];

        if ($fillable = $options['fillable'] ?? '') {
            $fieldNames = array_map('trim', explode(',', $fillable));
            foreach ($fieldNames as $field) {
                $fields[] = "            '{$field}' => \$this->faker->word(),";
            }
        }

        return !empty($fields) ? implode("\n", $fields) : "            // 'name' => \$this->faker->name(),";
    }

    private function displaySummary(string $name, array $options): void
    {
        $this->newLine();
        $this->section('Generation Summary');

        $this->keyValue('Model Name', $name);
        $this->keyValue('Table Name', $options['table'] ?? Str::snake(Str::pluralize($name)));

        if ($options['connection']) {
            $this->keyValue('Connection', $options['connection']);
        }

        $this->newLine();
        $this->info('Files to generate:');

        $files = ['Model'];
        if ($options['migration']) {
            $files[] = 'Migration';
        }
        if ($options['controller']) {
            if ($options['api']) {
                $files[] = 'API Controller';
            } elseif ($options['resource']) {
                $files[] = 'Resource Controller';
            } else {
                $files[] = 'Controller';
            }
        }
        if ($options['factory']) {
            $files[] = 'Factory';
        }
        if ($options['seeder']) {
            $files[] = 'Seeder';
        }

        $this->bulletList($files);

        if ($options['fillable']) {
            $this->newLine();
            $this->keyValue('Fillable Fields', $options['fillable']);
        }

        if ($options['soft_deletes']) {
            $this->info('✓ Soft deletes enabled');
        }

        $this->newLine();
    }

    private function displayResults(string $name, array $filesCreated, array $options): void
    {
        $this->newLine(2);

        $this->box(
            "Model '{$name}' generated successfully!\n\n" .
            "Files created: " . count($filesCreated) . "\n" .
            "Execution time: {$this->formatTime($this->elapsed())}",
            "✅ Success",
            "success"
        );

        $this->newLine();
        $this->section('Generated Files');

        foreach ($filesCreated as $file) {
            $relativePath = str_replace(getcwd() . '/', '', $file);
            $this->success("  ✓ {$relativePath}");
        }

        $this->newLine();
        $this->section('Next Steps');

        $steps = [
            "Edit the model: app/Models/{$name}.php",
        ];

        if ($options['migration']) {
            $steps[] = "Run migrations: php theplugs migrate";
        }

        if ($options['controller']) {
            $steps[] = "Register routes for {$name}Controller";
        }

        $this->numberedList($steps);
        $this->newLine();
    }

    private function hasAnyOption(): bool
    {
        $checkOptions = ['migration', 'm', 'controller', 'c', 'all', 'a', 'factory', 'f', 'seed', 's', 'resource', 'r', 'api'];

        foreach ($checkOptions as $option) {
            if ($this->hasOption($option)) {
                return true;
            }
        }

        return false;
    }

    private function getModelPath(string $name): string
    {
        return getcwd() . '/app/Models/' . $name . '.php';
    }

    private function getMigrationPath(string $filename): string
    {
        return base_path('database/Migrations/' . $filename);
    }

    private function getControllerPath(string $name): string
    {
        return getcwd() . '/app/Controllers/' . $name . '.php';
    }

    private function getFactoryPath(string $name): string
    {
        return base_path('database/Factories/' . $name . '.php');
    }

    private function getSeederPath(string $name): string
    {
        return base_path('database/Seeders/' . $name . '.php');
    }

    private function getDefaultTemplate(string $templateName): string
    {
        return match ($templateName) {
            'model.stub' => $this->getModelTemplate(),
            'migration.stub' => $this->getMigrationTemplate(),
            'migration.pivot.stub' => $this->getPivotMigrationTemplate(),
            'controller.stub' => $this->getControllerTemplate(),
            'controller.resource.stub' => $this->getResourceControllerTemplate(),
            'controller.api.stub' => $this->getApiControllerTemplate(),
            'factory.stub' => $this->getFactoryTemplate(),
            'seeder.stub' => $this->getSeederTemplate(),
            default => '',
        };
    }

    private function getModelTemplate(): string
    {
        return <<<'STUB'
<?php

declare(strict_types=1);

namespace App\Models;

{{imports}}
use Plugs\Base\Model\PlugModel;

class {{class}} extends PlugModel
{
    {{traits}}
    
    /**
     * The table associated with the model.
     *
     * @var string
     */
    protected $table = '{{table}}';

    /**
     * The primary key associated with the table.
     *
     * @var string
     */
    protected $primaryKey = 'id';

    /**
     * The connection name for the model.
     *
     * @var string
     */
    protected $connection = '{{connection}}';

    /**
     * Indicates if the model should be timestamped.
     *
     * @var bool
     */
    public $timestamps = {{timestamps}};

    /**
     * The attributes that are mass assignable.
     *
     * @var array
     */
    protected $fillable = {{fillable}};

    /**
     * The attributes that should be hidden for arrays.
     *
     * @var array
     */
    protected $hidden = {{hidden}};

    /**
     * The attributes that should be cast to native types.
     *
     * @var array
     */
    protected $casts = {{casts}};
}
STUB;
    }

    private function getMigrationTemplate(): string
    {
        return <<<'STUB'
<?php

declare(strict_types=1);

use Plugs\Database\Migration;
use Plugs\Database\Blueprint;
use Plugs\Database\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('{{table}}', function (Blueprint $table) {
{{fields}}
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('{{table}}');
    }
};
STUB;
    }

    private function getPivotMigrationTemplate(): string
    {
        return <<<'STUB'
<?php

declare(strict_types=1);

use Plugs\Database\Migration;
use Plugs\Database\Blueprint;
use Plugs\Database\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('{{table}}', function (Blueprint $table) {
            $table->foreignId('first_id')->constrained()->onDelete('cascade');
            $table->foreignId('second_id')->constrained()->onDelete('cascade');
            $table->timestamps();
            
            $table->primary(['first_id', 'second_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('{{table}}');
    }
};
STUB;
    }

    private function getControllerTemplate(): string
    {
        return <<<'STUB'
        <?php

        declare(strict_types=1);

        namespace App\Controllers;

        use {{modelNamespace}};
        use Plugs\Controller\Controller;

        class {{class}} extends Controller
        {
            public function index()
            {
                // List all {{model}}
            }
            
            public function show(int $id)
            {
                // Show single {{model}}
            }
        }

        STUB;
    }

    private function getResourceControllerTemplate(): string
    {
        return <<<'STUB'
        <?php

        declare(strict_types=1);

        namespace App\Controllers;

        use {{modelNamespace}};
        use Plugs\Controller\Controller;

        class {{class}} extends Controller
        {
            public function index()
            {
                // TODO: List all {{model}}s
            }
            
            public function create()
            {
                // TODO: Show create form
            }
            
            public function store()
            {
                // TODO: Store new {{model}}
            }
            
            public function show(int $id)
            {
                // TODO: Show single {{model}}
            }
            
            public function edit(int $id)
            {
                // TODO: Show edit form
            }
            
            public function update(int $id)
            {
                // TODO: Update {{model}}
            }
            
            public function destroy(int $id)
            {
                // TODO: Delete {{model}}
            }
        }

        STUB;
    }

    private function getApiControllerTemplate(): string
    {
        return <<<'STUB'
        <?php

        declare(strict_types=1);

        namespace App\Controllers\Api;

        use {{modelNamespace}};
        use Plugs\Controller\Controller;

        class {{class}} extends Controller
        {
            public function index()
            {
                // Return all {{model}}s as JSON
            }
            
            public function store()
            {
                // Create new {{model}} and return JSON
            }
            
            public function show(int $id)
            {
                // Return single {{model}} as JSON
            }
            
            public function update(int $id)
            {
                // Update {{model}} and return JSON
            }
            
            public function destroy(int $id)
            {
                // Delete {{model}} and return JSON response
            }
        }

        STUB;
    }

    private function getFactoryTemplate(): string
    {
        return <<<'STUB'
<?php

declare(strict_types=1);

namespace Database\Factories;

use {{modelNamespace}};
use Plugs\Database\Factory\PlugFactory;

class {{class}} extends PlugFactory
{
    /**
     * The associated model class.
     *
     * @var string
     */
    protected ?string $model = {{model}}::class;

    /**
     * Define the model's default state.
     *
     * @return array
     */
    public function definition(): array
    {
        return [
{{definition}}
        ];
    }
}
STUB;
    }

    private function getSeederTemplate(): string
    {
        return <<<'STUB'
<?php

declare(strict_types=1);

namespace Database\Seeders;

use {{modelNamespace}};
use Plugs\Database\Seeders\PlugSeeder;

class {{class}} extends PlugSeeder
{
    /**
     * Run the database seeds.
     *
     * @return void
     */
    public function run(): void
    {
        {{model}}::factory()->count(10)->create();
    }
}
STUB;
    }
}
