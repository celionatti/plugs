<?php

declare(strict_types=1);

namespace Plugs\Console\Commands;

/*
|--------------------------------------------------------------------------
| Make: Repository Command
|--------------------------------------------------------------------------
|
| Creates repository classes for data access abstraction.
*/

use Plugs\Console\Command;
use Plugs\Console\Support\Filesystem;
use Plugs\Console\Support\Str;

class MakeRepositoryCommand extends Command
{
    protected string $description = 'Create a new repository class';

    protected function defineArguments(): array
    {
        return [
            'name' => 'The name of the repository class',
        ];
    }

    protected function defineOptions(): array
    {
        return [
            '--model=MODEL' => 'Associated model for the repository',
            '--interface' => 'Generate interface alongside repository',
            '--force' => 'Overwrite existing files',
            '--strict' => 'Add strict type declarations',
        ];
    }

    public function handle(): int
    {
        $this->checkpoint('start');
        $this->title('Repository Generator');

        $name = $this->argument('0');

        if (!$name) {
            $name = $this->ask('Repository name', 'UserRepository');
        }

        // Ensure it ends with Repository
        if (!str_ends_with($name, 'Repository')) {
            $name .= 'Repository';
        }

        $name = Str::studly($name);

        $options = [
            'model' => $this->option('model'),
            'interface' => $this->hasOption('interface'),
            'force' => $this->isForce(),
            'strict' => $this->hasOption('strict'),
        ];

        // Interactive mode
        if (!$options['model'] && !$this->hasOption('force')) {
            $this->section('Configuration');

            if ($this->confirm('Associate with a model?', true)) {
                $modelName = $this->ask('Model name', str_replace('Repository', '', $name));
                $options['model'] = Str::studly($modelName);
            }

            $options['interface'] = $this->confirm('Generate interface?', true);
        }

        // Ensure BaseRepository exists
        $this->ensureBaseRepository($options);

        $path = $this->getRepositoryPath($name);

        if (Filesystem::exists($path) && !$options['force']) {
            if (!$this->confirm("Repository {$name} already exists. Overwrite?", false)) {
                $this->warning('Repository generation cancelled.');

                return 0;
            }
        }

        $this->section('Generating Files');
        $filesCreated = [];

        // Generate interface if requested
        if ($options['interface']) {
            $interfaceName = str_replace('Repository', 'RepositoryInterface', $name);
            $interfacePath = $this->getInterfacePath($interfaceName);

            $this->task('Creating repository interface', function () use ($interfaceName, $options, $interfacePath) {
                $content = $this->generateInterface($interfaceName, $options);
                Filesystem::put($interfacePath, $content);
                usleep(150000);
            });

            $filesCreated[] = $interfacePath;
            $this->success("Interface created: " . str_replace(getcwd() . '/', '', $interfacePath));
        }

        // Generate repository
        $this->task('Creating repository class', function () use ($name, $options, $path) {
            $content = $this->generateRepositoryClass($name, $options);
            Filesystem::put($path, $content);
            usleep(200000);
        });

        $filesCreated[] = $path;
        $this->success("Repository created: " . str_replace(getcwd() . '/', '', $path));

        $this->checkpoint('finished');

        $this->newLine(2);
        $this->box(
            "Repository '{$name}' generated successfully!\n\n" .
            "Files created: " . count($filesCreated) . "\n" .
            "Time: {$this->formatTime($this->elapsed())}",
            "✅ Success",
            "success"
        );

        $this->newLine();
        $this->section('Next Steps');
        $this->numberedList([
            "Implement data access methods in {$name}",
            "Bind interface to implementation in service container",
            $options['model'] ? "Ensure {$options['model']} model exists" : null,
        ]);

        if ($this->isVerbose()) {
            $this->displayTimings();
        }

        return 0;
    }

    private function getBaseRepositoryPath(): string
    {
        return getcwd() . '/app/Repositories/BaseRepository.php';
    }

    private function getInterfacePath(string $name): string
    {
        return getcwd() . '/app/Repositories/Interfaces/' . $name . '.php';
    }

    private function getRepositoryPath(string $name): string
    {
        return getcwd() . '/app/Repositories/' . $name . '/' . $name . '.php';
    }

    private function ensureBaseRepository(array $options): void
    {
        $path = $this->getBaseRepositoryPath();

        if (Filesystem::exists($path)) {
            return;
        }

        $this->task('Creating BaseRepository', function () use ($path, $options) {
            $content = $this->generateBaseRepository($options);
            Filesystem::put($path, $content);
        });
    }

    private function generateBaseRepository(array $options): string
    {
        $strict = $options['strict'] ? "declare(strict_types=1);\n\n" : '';

        return <<<PHP
<?php

{$strict}namespace App\Repositories;

use Plugs\Database\Collection;

/**
 * BaseRepository
 * 
 * Base class for all repositories.
 */
abstract class BaseRepository
{
    // Add common repository methods here
}

PHP;
    }

    private function generateRepositoryClass(string $name, array $options): string
    {
        $strict = $options['strict'] ? "declare(strict_types=1);\n\n" : '';
        $model = $options['model'] ?? 'Model';
        $imports = $this->buildImports($options);

        $interfaceName = str_replace('Repository', 'RepositoryInterface', $name);
        if ($options['interface']) {
            $imports .= "\nuse App\Repositories\Interfaces\\{$interfaceName};";
        }
        $imports .= "\nuse App\Repositories\BaseRepository;";

        $implements = $options['interface']
            ? ' implements ' . $interfaceName
            : '';

        return <<<PHP
<?php

{$strict}namespace App\Repositories\\{$name};

{$imports}

/**
 * {$name}
 *
 * Repository class for {$model} data access.
 * Abstracts database operations from business logic.
 * 
 * Generated by ThePlugs Console
 */
class {$name} extends BaseRepository{$implements}
{
    /**
     * Get all records
     */
    public function all(): \Plugs\Database\Collection
    {
        // Example with Eloquent: return {$model}::all();
        return new \Plugs\Database\Collection([]);
    }

    /**
     * Find a record by ID
     */
    public function find(int \$id): ?{$model}
    {
        // Example with Eloquent: return {$model}::find(\$id);
        return null;
    }

    /**
     * Find a record by specific field
     */
    public function findBy(string \$field, mixed \$value): ?{$model}
    {
        // Example with Eloquent: return {$model}::where(\$field, '=', \$value)->first();
        return null;
    }

    /**
     * Create a new record
     */
    public function create(array \$data): ?{$model}
    {
        // Example with Eloquent: return {$model}::create(\$data);
        return null;
    }

    /**
     * Update an existing record
     */
    public function update(int \$id, array \$data): bool
    {
        // TODO: Implement update() method
        // Example with Eloquent: return {$model}::where('id', \$id)->update(\$data) > 0;
        return false;
    }

    /**
     * Delete a record
     */
    public function delete(int \$id): bool
    {
        // TODO: Implement delete() method
        // Example with Eloquent: return {$model}::destroy(\$id) > 0;
        return false;
    }

    /**
     * Get paginated records
     */
    public function paginate(int \$perPage = 15, int \$page = 1): array
    {
        // TODO: Implement paginate() method
        return [
            'data' => [],
            'total' => 0,
            'per_page' => \$perPage,
            'current_page' => \$page,
            'last_page' => 1,
        ];
    }

    /**
     * Count all records
     */
    public function count(): int
    {
        // TODO: Implement count() method
        return 0;
    }
}

PHP;
    }

    private function generateInterface(string $name, array $options): string
    {
        $strict = $options['strict'] ? "declare(strict_types=1);\n\n" : '';
        $model = $options['model'] ?? 'Model';
        $imports = $this->buildImports($options);

        return <<<PHP
<?php

{$strict}namespace App\Repositories\Interfaces;

{$imports}

/**
 * {$name}
 *
 * Interface for repository implementation.
 * Generated by ThePlugs Console
 */
interface {$name}
{
    /**
     * Get all records
     */
    public function all(): \Plugs\Database\Collection;
    
    /**
     * Find a record by ID
     */
    public function find(int \$id): ?{$model};
    
    /**
     * Find a record by specific field
     */
    public function findBy(string \$field, mixed \$value): ?{$model};
    
    /**
     * Create a new record
     */
    public function create(array \$data): ?{$model};
    
    /**
     * Update an existing record
     */
    public function update(int \$id, array \$data): bool;
    
    /**
     * Delete a record
     */
    public function delete(int \$id): bool;

    /**
     * Get paginated records
     */
    public function paginate(int \$perPage = 15, int \$page = 1): array;

    /**
     * Count all records
     */
    public function count(): int;
}

PHP;
    }

    private function buildImports(array $options): string
    {
        $imports = [];

        if ($options['model']) {
            $imports[] = "use App\\Models\\{$options['model']};";
        }

        return implode("\n", $imports);
    }
}
