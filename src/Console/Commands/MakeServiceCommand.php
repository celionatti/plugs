<?php

declare(strict_types=1);

namespace Plugs\Console\Commands;

/*
|--------------------------------------------------------------------------
| Make: Service Command
|--------------------------------------------------------------------------
|
| Creates service classes for business logic.
*/

use Plugs\Console\Command;
use Plugs\Console\Support\Filesystem;
use Plugs\Console\Support\Str;

class MakeServiceCommand extends Command
{
    protected string $description = 'Create a new service class';

    protected function defineArguments(): array
    {
        return [
            'name' => 'The name of the service class',
        ];
    }

    protected function defineOptions(): array
    {
        return [
            '--model=MODEL' => 'Associated model for the service',
            '--repository' => 'Include repository dependency',
            '--interface' => 'Generate interface alongside service',
            '--force' => 'Overwrite existing files',
            '--strict' => 'Add strict type declarations',
        ];
    }

    public function handle(): int
    {
        $this->checkpoint('start');
        $this->title('Service Generator');

        $name = $this->argument('0');

        if (!$name) {
            $name = $this->ask('Service name', 'UserService');
        }

        if (!str_ends_with($name, 'Service')) {
            $name .= 'Service';
        }

        $name = Str::studly($name);

        $options = [
            'model' => $this->option('model'),
            'repository' => $this->hasOption('repository'),
            'interface' => $this->hasOption('interface'),
            'force' => $this->isForce(),
            'strict' => $this->hasOption('strict'),
        ];

        // Interactive mode
        if (!$options['model'] && !$this->hasOption('force')) {
            $this->section('Configuration');

            if ($this->confirm('Associate with a model?', true)) {
                $modelName = $this->ask('Model name', str_replace('Service', '', $name));
                $options['model'] = Str::studly($modelName);
            }

            $options['repository'] = $this->confirm('Use repository pattern?', false);
            $options['interface'] = $this->confirm('Generate interface?', false);
        }

        $path = $this->getServicePath($name);

        $this->section('Configuration Summary');
        $this->keyValue('Service Name', $name);
        $this->keyValue('Model', $options['model'] ?: 'None');
        $this->keyValue('Repository', $options['repository'] ? 'Yes' : 'No');
        $this->keyValue('Interface', $options['interface'] ? 'Yes' : 'No');
        $this->keyValue('Target Path', str_replace(getcwd() . '/', '', $path));
        $this->newLine();

        if (Filesystem::exists($path) && !$options['force']) {
            if (!$this->confirm("Service {$name} already exists. Overwrite?", false)) {
                $this->warning('Service generation cancelled.');

                return 0;
            }
        }

        $this->checkpoint('generating');
        $filesCreated = [];

        // Generate interface if requested
        if ($options['interface']) {
            $interfaceName = str_replace('Service', 'ServiceInterface', $name);
            $interfacePath = $this->getServicePath($interfaceName);

            $this->task('Creating service interface', function () use ($interfaceName, $options, $interfacePath) {
                $content = $this->generateInterface($interfaceName, $options);
                Filesystem::put($interfacePath, $content);
                usleep(150000);
            });

            $filesCreated[] = $interfacePath;
        }

        // Generate service
        $this->task('Creating service class', function () use ($name, $options, $path) {
            $content = $this->generateServiceClass($name, $options);
            Filesystem::put($path, $content);
            usleep(200000);
        });

        $filesCreated[] = $path;

        $this->checkpoint('finished');

        $this->newLine(2);
        $this->box(
            "Service '{$name}' generated successfully!\n\n" .
            "Files created: " . count($filesCreated) . "\n" .
            "Time: {$this->formatTime($this->elapsed())}",
            "✅ Success",
            "success"
        );

        $this->section('Next Steps');
        $this->numberedList([
            "Implement business logic in {$name}",
            "Inject the service into your controllers",
            $options['model'] ? "Ensure {$options['model']} model exists" : null,
        ]);

        if ($this->isVerbose()) {
            $this->displayTimings();
        }

        return 0;
    }

    private function getServicePath(string $name): string
    {
        return getcwd() . '/app/Services/' . $name . '.php';
    }

    private function generateServiceClass(string $name, array $options): string
    {
        $strict = $options['strict'] ? "declare(strict_types=1);\n\n" : '';
        $imports = $this->buildImports($options);
        $properties = $this->buildProperties($options);
        $constructor = $this->buildConstructor($options);
        $methods = $this->buildMethods($options);
        $implements = $options['interface']
            ? ' implements ' . str_replace('Service', 'ServiceInterface', $name)
            : '';

        return <<<PHP
<?php

{$strict}namespace App\Services;

{$imports}

/**
 * {$name}
 *
 * Service class for handling business logic.
 * Generated by ThePlugs Console
 */
class {$name}{$implements}
{
{$properties}
{$constructor}
{$methods}
}

PHP;
    }

    private function generateInterface(string $name, array $options): string
    {
        $strict = $options['strict'] ? "declare(strict_types=1);\n\n" : '';
        $model = $options['model'] ?? 'Model';
        $imports = $this->buildImports($options);
        $modelVar = Str::camel($model);

        return <<<PHP
<?php

{$strict}namespace App\Services;

{$imports}

/**
 * {$name}
 *
 * Interface for the service class.
 * Generated by ThePlugs Console
 */
interface {$name}
{
    public function all(): \Plugs\Database\Collection;
    
    public function find(int \$id): ?{$model};
    
    public function create(array \$data): ?{$model};
    
    public function update(int \$id, array \$data): bool;
    
    public function delete(int \$id): bool;
}

PHP;
    }

    private function buildImports(array $options): string
    {
        $imports = [];

        if ($options['model']) {
            $imports[] = "use App\\Models\\{$options['model']};";
        }

        if ($options['repository']) {
            $repoName = ($options['model'] ?? 'Base') . 'Repository';
            $imports[] = "use App\\Repositories\\{$repoName};";
        }

        return implode("\n", $imports);
    }

    private function buildProperties(array $options): string
    {
        $props = [];

        if ($options['repository']) {
            $repoName = ($options['model'] ?? 'Base') . 'Repository';
            $props[] = "    protected {$repoName} \$repository;";
        }

        return implode("\n", $props);
    }

    private function buildConstructor(array $options): string
    {
        if (!$options['repository']) {
            return <<<'PHP'
    public function __construct()
    {
        // Initialize service
    }
PHP;
        }

        $repoName = ($options['model'] ?? 'Base') . 'Repository';

        return <<<PHP
    public function __construct({$repoName} \$repository)
    {
        \$this->repository = \$repository;
    }
PHP;
    }

    private function buildMethods(array $options): string
    {
        $model = $options['model'] ?? 'Model';
        $modelVar = Str::camel($model);
        $useRepo = $options['repository'];

        $methods = [];

        // all() method
        $methods[] = $this->buildMethod(
            'all',
            [],
            'Get all records',
            $useRepo ? 'return $this->repository->all();' : '// TODO: Implement retrieval logic',
            '\Plugs\Database\Collection'
        );

        // find() method
        $methods[] = $this->buildMethod(
            'find',
            ['int $id'],
            'Find a record by ID',
            $useRepo ? 'return $this->repository->find($id);' : '// TODO: Implement find logic',
            "?{$model}"
        );

        // create() method
        $methods[] = $this->buildMethod(
            'create',
            ['array $data'],
            'Create a new record',
            $useRepo ? 'return $this->repository->create($data);' : '// TODO: Implement create logic',
            "?{$model}"
        );

        // update() method
        $methods[] = $this->buildMethod(
            'update',
            ['int $id', 'array $data'],
            'Update an existing record',
            $useRepo ? 'return $this->repository->update($id, $data);' : '// TODO: Implement update logic',
            'bool'
        );

        // delete() method
        $methods[] = $this->buildMethod(
            'delete',
            ['int $id'],
            'Delete a record',
            $useRepo ? 'return $this->repository->delete($id);' : '// TODO: Implement delete logic',
            'bool'
        );

        return implode("\n\n", $methods);
    }

    private function buildMethod(string $name, array $params, string $description, string $body, string $returnType): string
    {
        $paramStr = implode(', ', $params);

        return <<<PHP
    /**
     * {$description}
     */
    public function {$name}({$paramStr}): {$returnType}
    {
        {$body}
    }
PHP;
    }
}
