<?php

declare(strict_types=1);

namespace Plugs\Console\Commands;

/*
|--------------------------------------------------------------------------
| Make: Resource Command
|--------------------------------------------------------------------------
|
| Creates API Resource classes for transforming data.
*/

use Plugs\Console\Command;
use Plugs\Console\Support\Filesystem;
use Plugs\Console\Support\Str;

class MakeResourceCommand extends Command
{
    protected string $description = 'Create a new API resource class';

    protected function defineArguments(): array
    {
        return [
            'name' => 'The name of the resource class',
        ];
    }

    protected function defineOptions(): array
    {
        return [
            '--model=MODEL' => 'Associated model for the resource',
            '--collection' => 'Generate a resource collection',
            '--all' => 'Generate resource, collection, and form requests',
            '--force' => 'Overwrite existing files',
            '--strict' => 'Add strict type declarations',
        ];
    }

    public function handle(): int
    {
        $this->checkpoint('start');
        $this->title('API Resource Generator');

        $name = $this->argument('0');

        if (!$name) {
            $name = $this->ask('Resource name', 'UserResource');
        }

        // Parse path and name
        $rawName = str_replace('\\', '/', $name);
        $segments = explode('/', $rawName);
        $className = array_pop($segments);
        $subDir = implode('/', $segments);

        // Ensure proper suffix and detect collection intent
        $isCollection = str_ends_with($className, 'Collection');

        if (!$isCollection && !str_ends_with($className, 'Resource')) {
            $className .= 'Resource';
        }

        $className = Str::studly($className);
        $fullPathName = ($subDir ? $subDir . '/' : '') . $className;

        $options = [
            'model' => $this->option('model'),
            'collection' => $this->hasOption('collection') || $this->hasOption('all') || $isCollection,
            'requests' => $this->hasOption('all'),
            'force' => $this->isForce(),
            'strict' => $this->hasOption('strict'),
            'subDir' => $subDir,
        ];

        // Interactive mode
        if (!$options['model'] && !$this->hasOption('force')) {
            $this->section('Configuration');

            if ($this->confirm('Associate with a model?', true)) {
                $baseName = str_replace(['Resource', 'Collection'], '', $className);
                $modelName = $this->ask('Model name', $baseName);
                $options['model'] = Str::studly($modelName);
            }

            if (!$isCollection) {
                $options['collection'] = $this->confirm('Generate a corresponding Collection class?', false);
            }
        }

        $path = $this->getResourcePath($fullPathName);

        if (Filesystem::exists($path) && !$options['force']) {
            if (!$this->confirm("Resource {$className} already exists. Overwrite?", false)) {
                $this->warning('Resource generation cancelled.');

                return 0;
            }
        }

        $this->section('Generating Files');
        $filesCreated = [];

        // Generate main resource
        $this->task('Creating resource class', function () use ($className, $options, $path) {
            $content = $this->generateResourceClass($className, $options);
            Filesystem::put($path, $content);
            usleep(200000);
        });

        $filesCreated[] = $path;
        $this->success("Resource created: {$path}");

        // Generate collection if requested
        if ($options['collection']) {
            $collectionName = str_replace('Resource', 'Collection', $className);
            $fullCollectionPathName = ($options['subDir'] ? $options['subDir'] . '/' : '') . $collectionName;
            $collectionPath = $this->getResourcePath($fullCollectionPathName);

            $this->task('Creating collection class', function () use ($collectionName, $className, $options, $collectionPath) {
                $content = $this->generateCollectionClass($collectionName, $className, $options);
                Filesystem::put($collectionPath, $content);
                usleep(150000);
            });

            $filesCreated[] = $collectionPath;
            $this->success("Collection created: {$collectionPath}");
        }

        // Generate requests if requested
        if ($options['requests']) {
            $baseName = str_replace(['Resource', 'Collection'], '', $className);

            // Store Request
            $storeName = "Store{$baseName}Request";
            $storePath = getcwd() . '/app/Http/Requests/' . ($options['subDir'] ? $options['subDir'] . '/' : '') . $storeName . '.php';

            $this->task("Creating {$storeName}", function () use ($storeName, $options, $storePath) {
                $content = $this->generateRequestClass($storeName, $options);
                Filesystem::put($storePath, $content);
                usleep(100000);
            });
            $filesCreated[] = $storePath;
            $this->success("Request created: {$storeName}");

            // Update Request
            $updateName = "Update{$baseName}Request";
            $updatePath = getcwd() . '/app/Http/Requests/' . ($options['subDir'] ? $options['subDir'] . '/' : '') . $updateName . '.php';

            $this->task("Creating {$updateName}", function () use ($updateName, $options, $updatePath) {
                $content = $this->generateRequestClass($updateName, $options);
                Filesystem::put($updatePath, $content);
                usleep(100000);
            });
            $filesCreated[] = $updatePath;
            $this->success("Request created: {$updateName}");
        }

        $this->checkpoint('finished');

        $this->newLine(2);
        $this->box(
            "Resource '{$className}' generated successfully!\n\n" .
            "Files created: " . count($filesCreated) . "\n" .
            "Time: {$this->formatTime($this->elapsed())}",
            "✅ Success",
            "success"
        );

        if ($this->isVerbose()) {
            $this->displayTimings();
        }

        return 0;
    }

    private function getResourcePath(string $name): string
    {
        return getcwd() . '/app/Http/Resources/' . $name . '.php';
    }

    private function generateResourceClass(string $name, array $options): string
    {
        $strict = $options['strict'] ? "declare(strict_types=1);\n\n" : '';
        $model = $options['model'] ?? 'Model';
        $subNamespace = $options['subDir'] ? '\\' . str_replace('/', '\\', $options['subDir']) : '';

        return <<<PHP
<?php

{$strict}namespace App\Http\Resources{$subNamespace};

use Plugs\Http\Resources\PlugResource;

/**
 * {$name}
 *
 * API Resource for transforming {$model} data.
 * Generated by ThePlugs Console
 * 
 * @property mixed \$resource The underlying {$model} instance
 */
class {$name} extends PlugResource
{
    /**
     * Transform the resource into an array.
     * 
     * Use \$this->resource to access the underlying model/data.
     * 
     * Available properties:
     *   - public static bool \$camelCase = true; - Auto conversion to camelCase
     *   - public bool \$preserveKeys = false; - Keep original keys
     * 
     * Available helpers:
     *   - \$this->when(\$condition, \$value, \$default) - Conditional attributes
     *   - \$this->whenHas('attribute') - Include only if attribute exists
     *   - \$this->whenCount('relation') - Include relationship count
     *   - \$this->whenLoaded('relation') - Include only when relation is loaded
     *   - \$this->whenNotNull(\$value) - Include only when value is not null
     *   - \$this->mergeWhen(\$condition, \$values) - Conditionally merge arrays
     */
    public function toArray(): array
    {
        return [
            'id' => \$this->resource->id,
            // 'name' => \$this->resource->name,
            // 'email' => \$this->resource->email,
            
            // Conditional attribute example:
            // 'is_admin' => \$this->when(\$this->resource->is_admin, true),
            
            // Include relationship only when loaded:
            // 'posts' => PostResource::collection(\$this->whenLoaded('posts')),
            
            // Include only when not null:
            // 'avatar' => \$this->whenNotNull(\$this->resource->avatar_url),
            
            // Timestamps
            'created_at' => \$this->resource->created_at,
            'updated_at' => \$this->resource->updated_at,
        ];
    }
}

PHP;
    }

    private function generateCollectionClass(string $collectionName, string $resourceName, array $options): string
    {
        $strict = $options['strict'] ? "declare(strict_types=1);\n\n" : '';
        $subNamespace = $options['subDir'] ? '\\' . str_replace('/', '\\', $options['subDir']) : '';

        return <<<PHP
<?php

{$strict}namespace App\Http\Resources{$subNamespace};

use Plugs\Http\Resources\PlugResourceCollection;

/**
 * {$collectionName}
 *
 * API Resource Collection for transforming multiple {$resourceName} items.
 * Generated by ThePlugs Console
 */
class {$collectionName} extends PlugResourceCollection
{
    /**
     * The resource class this collection uses
     */
    protected string \$collects = {$resourceName}::class;

    /**
     * Transform the collection into an array.
     * 
     * Override this method to customize the collection output.
     * By default, uses the parent toArray() which transforms each item.
     */
    // public function toArray(): array
    // {
    //     return [
    //         'data' => \$this->collection,
    //         'count' => \$this->count(),
    //         'meta' => [
    //             'version' => '1.0.0',
    //         ],
    //     ];
    // }
}

PHP;
    }

    private function generateRequestClass(string $name, array $options): string
    {
        $subNamespace = $options['subDir'] ? '\\' . str_replace('/', '\\', $options['subDir']) : '';
        $strict = $options['strict'] ? "declare(strict_types=1);\n\n" : '';

        return <<<PHP
<?php

{$strict}namespace App\Http\Requests{$subNamespace};

use Plugs\Http\Request;

/**
 * {$name}
 *
 * Generated by ThePlugs Console
 */
class {$name} extends Request
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     */
    public function rules(): array
    {
        return [
            // Add your validation rules here
        ];
    }
}
PHP;
    }
}
