<?php

declare(strict_types=1);

namespace Plugs\Console\Commands;

/*
|--------------------------------------------------------------------------
| Make: Controller Command
|--------------------------------------------------------------------------
*/

use Plugs\Console\Command;
use Plugs\Console\Support\Filesystem;
use Plugs\Console\Support\Str;

class MakeControllerCommand extends Command
{
    protected string $description = 'Create a new controller class with advanced features';

    private string $templatePath;

    public function __construct(string $name)
    {
        parent::__construct($name);
        $this->templatePath = getcwd() . '/stubs';
    }

    protected function defineArguments(): array
    {
        return [
            'name' => 'The name of the controller class',
        ];
    }

    protected function defineOptions(): array
    {
        return [
            '--model=MODEL' => 'Generate a resource controller for the given model',
            '--resource, -r' => 'Generate a resource controller with CRUD methods',
            '--api' => 'Generate an API controller (no create/edit methods)',
            '--invokable, -i' => 'Generate a single action controller',
            '--parent=PARENT' => 'Generate a nested resource controller',
            '--singleton' => 'Generate a singleton resource controller',
            '--requests' => 'Generate FormRequest classes for store and update',
            '--test' => 'Generate a test class for the controller',
            '--pest' => 'Generate a Pest test',
            '--force' => 'Overwrite existing files',
            '--type=TYPE' => 'Specify controller type (plain, resource, api, invokable)',
            '--namespace=NAMESPACE' => 'Custom namespace for the controller',
            '--methods=METHODS' => 'Comma-separated list of methods to include',
            '--no-comments' => 'Do not add PHPDoc comments',
            '--strict' => 'Add strict type declarations',
        ];
    }

    public function handle(): int
    {
        $this->checkpoint('start');
        $this->title('Controller Generator');

        // Get controller name
        $name = $this->argument('0');

        if (!$name) {
            $name = $this->ask('Controller name', 'UserController');
        }

        // Parse path and name
        $rawName = str_replace('\\', '/', $name);
        $segments = explode('/', $rawName);
        $className = array_pop($segments);
        $subDir = implode('/', $segments);

        // Ensure it ends with Controller
        if (!str_ends_with($className, 'Controller')) {
            $className .= 'Controller';
        }

        $className = Str::studly($className);
        $fullPathName = ($subDir ? $subDir . '/' : '') . $className;

        $this->checkpoint('name_collected');

        // Gather options
        $options = $this->gatherOptions($className, $subDir);

        $this->checkpoint('options_collected');

        // Display summary
        $this->displaySummary($className, $options);

        if (!$this->confirm('Proceed with generation?', true)) {
            $this->warning('Controller generation cancelled.');

            return 0;
        }

        // Initialize progress
        $totalSteps = 1; // Controller always generated
        if ($options['requests'])
            $totalSteps += 2;
        if ($options['test'])
            $totalSteps += 1;
        $currentStep = 0;

        // Generate controller
        $currentStep++;
        $this->progress($currentStep, $totalSteps, "Generating Controller...");
        $controllerPath = $this->generateController($className, $options);
        $this->checkpoint('controller_generated');

        $filesCreated = [$controllerPath];

        // Generate related files
        if ($options['requests']) {
            $currentStep++;
            $this->progress($currentStep, $totalSteps, "Generating Form Request 1/2...");
            // Internal logic refactored to allow progress updates if needed, or just step through
            $requestPaths = $this->generateRequests($className, $options);
            $currentStep++;
            $this->progress($currentStep, $totalSteps, "Generating Form Request 2/2...");
            $filesCreated = array_merge($filesCreated, $requestPaths);
        }

        if ($options['test']) {
            $currentStep++;
            $this->progress($currentStep, $totalSteps, "Generating Test Case...");
            $testPath = $this->generateTest($className, $options);
            $filesCreated[] = $testPath;
        }

        $this->checkpoint('all_generated');

        // Display results
        $this->displayResults($className, $filesCreated, $options);

        if ($this->isVerbose()) {
            $this->displayTimings();
        }

        return 0;
    }

    private function gatherOptions(string $className, string $subDir): array
    {
        $options = [
            'type' => $this->determineType(),
            'model' => $this->option('model'),
            'parent' => $this->option('parent'),
            'namespace' => $this->option('namespace'),
            'methods' => $this->option('methods'),
            'singleton' => $this->hasOption('singleton'),
            'requests' => $this->hasOption('requests'),
            'test' => $this->hasOption('test'),
            'pest' => $this->hasOption('pest'),
            'force' => $this->isForce(),
            'comments' => !$this->hasOption('no-comments'),
            'strict' => $this->hasOption('strict'),
            'subDir' => $subDir,
        ];

        // Interactive mode if no significant options provided
        if (!$this->hasAnyOption()) {
            $this->section('Configuration');

            $typeChoice = $this->choice(
                'Controller type?',
                ['Plain', 'Resource', 'API', 'Invokable', 'Singleton'],
                'Resource'
            );

            $options['type'] = strtolower($typeChoice);

            if (in_array($options['type'], ['resource', 'api', 'singleton'])) {
                if ($this->confirm('Associate with a model?', true)) {
                    $modelName = $this->ask('Model name', str_replace('Controller', '', $className));
                    $options['model'] = Str::studly($modelName);
                }

                if ($this->confirm('Is this a nested resource?', false)) {
                    $parentName = $this->ask('Parent resource name');
                    $options['parent'] = Str::studly($parentName);
                }
            }

            if (in_array($options['type'], ['resource', 'api'])) {
                $options['requests'] = $this->confirm('Generate Form Request classes?', false);
            }

            $options['test'] = $this->confirm('Generate test class?', false);

            if ($options['test']) {
                $options['pest'] = $this->confirm('Use Pest instead of PHPUnit?', false);
            }

            $options['comments'] = $this->confirm('Add PHPDoc comments?', true);
        }

        // Parse custom methods if provided
        if ($options['methods']) {
            $options['custom_methods'] = array_map('trim', explode(',', $options['methods']));
        }

        return $options;
    }

    private function determineType(): string
    {
        if ($this->hasOption('invokable') || $this->hasOption('i')) {
            return 'invokable';
        }

        if ($this->hasOption('api')) {
            return 'api';
        }

        if ($this->hasOption('resource') || $this->hasOption('r')) {
            return 'resource';
        }

        if ($this->hasOption('singleton')) {
            return 'singleton';
        }

        if ($this->option('type')) {
            return strtolower($this->option('type'));
        }

        return 'plain';
    }

    private function generateController(string $name, array $options): string
    {
        $path = $this->getControllerPath($name, $options);

        if (Filesystem::exists($path) && !$options['force']) {
            if (!$this->confirm("Controller {$name} already exists. Overwrite?", false)) {
                $this->warning('Controller generation skipped.');
                exit(0);
            }
        }

        // Load template
        $template = $this->loadTemplate($options);

        // Populate template
        $content = $this->populateTemplate($template, $name, $options);

        Filesystem::put($path, $content);

        $this->success("Controller created: {$path}");

        return $path;
    }

    private function generateRequests(string $controllerName, array $options): array
    {
        $baseName = str_replace('Controller', '', $controllerName);
        $paths = [];

        // Store Request
        $storeRequestName = "Store{$baseName}Request";
        $storeRequestPath = $this->getRequestPath($storeRequestName, $options);
        $storeContent = $this->generateRequestClass($storeRequestName, $options);
        Filesystem::put($storeRequestPath, $storeContent);
        $paths[] = $storeRequestPath;
        $this->success("Request created: {$storeRequestName}");

        // Update Request
        $updateRequestName = "Update{$baseName}Request";
        $updateRequestPath = $this->getRequestPath($updateRequestName, $options);
        $updateContent = $this->generateRequestClass($updateRequestName, $options);
        Filesystem::put($updateRequestPath, $updateContent);
        $paths[] = $updateRequestPath;
        $this->success("Request created: {$updateRequestName}");

        return $paths;
    }

    private function generateTest(string $controllerName, array $options): string
    {
        $testType = $options['pest'] ? 'Pest' : 'PHPUnit';

        $testName = str_replace('Controller', '', $controllerName) . 'ControllerTest';
        $path = $this->getTestPath($testName, $options);

        $template = $options['pest'] ? $this->getPestTestTemplate() : $this->getPhpUnitTestTemplate();

        $replacements = [
            '{{class}}' => $testName,
            '{{controller}}' => $controllerName,
            '{{controllerNamespace}}' => $this->buildNamespace($options) . '\\' . $controllerName,
        ];

        $content = str_replace(array_keys($replacements), array_values($replacements), $template);

        Filesystem::put($path, $content);

        $this->success("Test created: {$testName}");

        return $path;
    }

    private function loadTemplate(array $options): string
    {
        $templateName = match ($options['type']) {
            'invokable' => 'controller.invokable.stub',
            'api' => 'controller.api.stub',
            'resource' => 'controller.resource.stub',
            'singleton' => 'controller.singleton.stub',
            default => 'controller.plain.stub',
        };

        $templatePath = $this->templatePath . '/' . $templateName;

        if (Filesystem::exists($templatePath)) {
            return Filesystem::get($templatePath);
        }

        return $this->getDefaultTemplate($options['type']);
    }

    private function populateTemplate(string $template, string $name, array $options): string
    {
        $namespace = $this->buildNamespace($options);
        $imports = $this->buildImports($options);
        $methods = $this->buildMethods($options);
        $comments = $options['comments'] ? $this->buildClassComment($name, $options) : '';
        $strict = $options['strict'] ? "declare(strict_types=1);\n\n" : '';

        $replacements = [
            '{{strict}}' => $strict,
            '{{namespace}}' => $namespace,
            '{{imports}}' => $imports,
            '{{classComment}}' => $comments,
            '{{class}}' => $name,
            '{{methods}}' => $methods,
            '{{model}}' => $options['model'] ?? 'Model',
            '{{modelVariable}}' => $options['model'] ? Str::camel($options['model']) : 'model',
            '{{modelNamespace}}' => $options['model'] ? 'App\\Models\\' . $options['model'] : '',
            '{{parent}}' => $options['parent'] ?? 'Parent',
            '{{parentVariable}}' => $options['parent'] ? Str::camel($options['parent']) : 'parent',
        ];

        return str_replace(array_keys($replacements), array_values($replacements), $template);
    }

    private function buildNamespace(array $options): string
    {
        if ($options['namespace']) {
            return $options['namespace'];
        }

        $base = 'App\\Http\\Controllers';

        if ($options['subDir']) {
            $base .= '\\' . str_replace('/', '\\', $options['subDir']);
        }

        if ($options['type'] === 'api' && !$options['subDir']) {
            return $base . '\\Api';
        }

        return $base;
    }

    private function buildImports(array $options): string
    {
        $imports = ["use Plugs\\Base\\Controller\\Controller;"];

        if ($options['model']) {
            $imports[] = "use App\\Models\\{$options['model']};";
        }

        if ($options['parent']) {
            $imports[] = "use App\\Models\\{$options['parent']};";
        }

        if ($options['requests']) {
            $baseName = str_replace('Controller', '', $options['model'] ?? 'Resource');
            $imports[] = "use App\\Http\\Requests\\Store{$baseName}Request;";
            $imports[] = "use App\\Http\\Requests\\Update{$baseName}Request;";
        }

        return implode("\n", $imports);
    }

    private function buildMethods(array $options): string
    {
        if (isset($options['custom_methods'])) {
            return $this->buildCustomMethods($options['custom_methods'], $options);
        }

        return match ($options['type']) {
            'invokable' => $this->buildInvokableMethod($options),
            'api' => $this->buildApiMethods($options),
            'resource' => $this->buildResourceMethods($options),
            'singleton' => $this->buildSingletonMethods($options),
            default => $this->buildPlainMethods($options),
        };
    }

    private function buildResourceMethods(array $options): string
    {
        $methods = [
            $this->buildMethod('index', [], 'Display a listing of the resource', $options),
            $this->buildMethod('create', [], 'Show the form for creating a new resource', $options),
            $this->buildMethod('store', ['Request $request'], 'Store a newly created resource', $options),
            $this->buildMethod('show', ['int $id'], 'Display the specified resource', $options),
            $this->buildMethod('edit', ['int $id'], 'Show the form for editing the resource', $options),
            $this->buildMethod('update', ['Request $request', 'int $id'], 'Update the specified resource', $options),
            $this->buildMethod('destroy', ['int $id'], 'Remove the specified resource', $options),
        ];

        return implode("\n\n", $methods);
    }

    private function buildApiMethods(array $options): string
    {
        $methods = [
            $this->buildMethod('index', [], 'Display a listing of the resource', $options),
            $this->buildMethod('store', ['Request $request'], 'Store a newly created resource', $options),
            $this->buildMethod('show', ['int $id'], 'Display the specified resource', $options),
            $this->buildMethod('update', ['Request $request', 'int $id'], 'Update the specified resource', $options),
            $this->buildMethod('destroy', ['int $id'], 'Remove the specified resource', $options),
        ];

        return implode("\n\n", $methods);
    }

    private function buildSingletonMethods(array $options): string
    {
        $methods = [
            $this->buildMethod('show', [], 'Display the resource', $options),
            $this->buildMethod('edit', [], 'Show the form for editing the resource', $options),
            $this->buildMethod('update', ['Request $request'], 'Update the resource', $options),
        ];

        return implode("\n\n", $methods);
    }

    private function buildInvokableMethod(array $options): string
    {
        return $this->buildMethod('__invoke', ['Request $request'], 'Handle the incoming request', $options);
    }

    private function buildPlainMethods(array $options): string
    {
        return $this->buildMethod('index', [], 'Handle the request', $options);
    }

    private function buildCustomMethods(array $methods, array $options): string
    {
        $built = [];

        foreach ($methods as $method) {
            $built[] = $this->buildMethod($method, [], "Handle {$method} request", $options);
        }

        return implode("\n\n", $built);
    }

    private function buildMethod(string $name, array $params, string $description, array $options): string
    {
        $indent = '    ';
        $method = '';

        if ($options['comments']) {
            $method .= "{$indent}/**\n";
            $method .= "{$indent} * {$description}\n";
            $method .= "{$indent} */\n";
        }

        $paramString = implode(', ', $params);
        $returnType = $options['strict'] ? ': mixed' : '';

        $method .= "{$indent}public function {$name}({$paramString}){$returnType}\n";
        $method .= "{$indent}{\n";
        $method .= "{$indent}    // TODO: Implement {$name} method\n";
        $method .= "{$indent}}";

        return $method;
    }

    private function buildClassComment(string $name, array $options): string
    {
        $comment = "/**\n";
        $comment .= " * {$name}\n";
        $comment .= " *\n";

        if ($options['model']) {
            $comment .= " * Controller for managing {$options['model']} resources\n";
        }

        $comment .= " * Generated by ThePlugs Console\n";
        $comment .= " * @created " . date('Y-m-d H:i:s') . "\n";
        $comment .= " */\n";

        return $comment;
    }

    private function generateRequestClass(string $name, array $options): string
    {
        $template = $this->loadRequestTemplate();
        $subNamespace = $options['subDir'] ? '\\' . str_replace('/', '\\', $options['subDir']) : '';

        $replacements = [
            '{{namespace}}' => "App\\Http\\Requests{$subNamespace}",
            '{{class}}' => $name,
            '{{rules}}' => $this->buildValidationRules($options),
        ];

        return str_replace(array_keys($replacements), array_values($replacements), $template);
    }

    private function buildValidationRules(array $options): string
    {
        // Basic validation rules - can be customized
        return "[\n            // Add validation rules here\n        ]";
    }

    private function displaySummary(string $name, array $options): void
    {
        $this->newLine();
        $this->section('Generation Summary');

        $this->keyValue('Controller Name', $name);
        $this->keyValue('Type', ucfirst($options['type']));
        $this->keyValue('Namespace', $this->buildNamespace($options));

        if ($options['model']) {
            $this->keyValue('Associated Model', $options['model']);
        }

        if ($options['parent']) {
            $this->keyValue('Parent Resource', $options['parent']);
        }

        $this->newLine();
        $this->info('Files to generate:');

        $files = ['Controller'];
        if ($options['requests']) {
            $files[] = 'Form Requests (2)';
        }
        if ($options['test']) {
            $files[] = ($options['pest'] ? 'Pest' : 'PHPUnit') . ' Test';
        }

        $this->bulletList($files);

        $this->newLine();
    }

    private function displayResults(string $name, array $filesCreated, array $options): void
    {
        $this->newLine(2);

        $this->box(
            "Controller '{$name}' generated successfully!\n\n" .
            "Type: " . ucfirst($options['type']) . "\n" .
            "Files created: " . count($filesCreated),
            "✅ Success",
            "success"
        );

        $this->metrics($this->elapsed(), memory_get_peak_usage());

        $this->newLine();
        $this->section('Generated Files');

        foreach ($filesCreated as $file) {
            $relativePath = str_replace(getcwd() . '/', '', $file);
            $this->success("  ✓ {$relativePath}");
        }

        $this->newLine();
        $this->section('Next Steps');

        $steps = [
            "Implement methods in: {$name}",
            "Register routes for the controller",
        ];

        if ($options['model']) {
            $steps[] = "Ensure {$options['model']} model exists";
        }

        if ($options['test']) {
            $steps[] = "Write tests for the controller methods";
        }

        $this->numberedList($steps);
        $this->newLine();
    }

    private function hasAnyOption(): bool
    {
        $checkOptions = ['resource', 'r', 'api', 'invokable', 'i', 'model', 'type', 'singleton'];

        foreach ($checkOptions as $option) {
            if ($this->hasOption($option) || $this->option($option)) {
                return true;
            }
        }

        return false;
    }

    private function getControllerPath(string $className, array $options): string
    {
        $namespace = $this->buildNamespace($options);
        $path = str_replace(['App\\Http\\Controllers', '\\'], ['app/Http/Controllers', '/'], $namespace);

        return getcwd() . '/' . $path . '/' . $className . '.php';
    }

    private function getRequestPath(string $name, array $options): string
    {
        $path = 'app/Http/Requests';
        if ($options['subDir']) {
            $path .= '/' . $options['subDir'];
        }

        return getcwd() . '/' . $path . '/' . $name . '.php';
    }

    private function getTestPath(string $name, array $options): string
    {
        $path = 'tests/Feature';
        if ($options['subDir']) {
            $path .= '/' . $options['subDir'];
        }

        return getcwd() . '/' . $path . '/' . $name . '.php';
    }

    private function getDefaultTemplate(string $type): string
    {
        return match ($type) {
            'invokable' => $this->getInvokableTemplate(),
            'api' => $this->getApiTemplate(),
            'resource' => $this->getResourceTemplate(),
            'singleton' => $this->getSingletonTemplate(),
            default => $this->getPlainTemplate(),
        };
    }

    private function getPlainTemplate(): string
    {
        return <<<'STUB'
        <?php

        {{strict}}namespace {{namespace}};

        {{imports}}

        {{classComment}}class {{class}} extends Controller
        {
        {{methods}}
        }

        STUB;
    }

    private function getResourceTemplate(): string
    {
        return $this->getPlainTemplate();
    }

    private function getApiTemplate(): string
    {
        return $this->getPlainTemplate();
    }

    private function getInvokableTemplate(): string
    {
        return $this->getPlainTemplate();
    }

    private function getSingletonTemplate(): string
    {
        return $this->getPlainTemplate();
    }

    private function loadRequestTemplate(): string
    {
        $templatePath = $this->templatePath . '/request.stub';

        if (Filesystem::exists($templatePath)) {
            return Filesystem::get($templatePath);
        }

        return <<<'STUB'
        <?php

        declare(strict_types=1);

        namespace {{namespace}};

        use Plugs\Http\Requests\FormRequest;

        class {{class}} extends FormRequest
        {
            public function authorize(): bool
            {
                return true;
            }
            
            public function rules(): array
            {
                return {{rules}};
            }
        }

        STUB;
    }

    private function getPhpUnitTestTemplate(): string
    {
        return <<<'STUB'
        <?php

        declare(strict_types=1);

        namespace Tests\Feature;

        use Tests\TestCase;
        use {{controllerNamespace}};

        class {{class}} extends TestCase
        {
            public function test_controller_exists(): void
            {
                $this->assertTrue(class_exists({{controller}}::class));
            }
            
            // Add more tests here
        }

        STUB;
    }

    private function getPestTestTemplate(): string
    {
        return <<<'STUB'
        <?php

        use {{controllerNamespace}};

        test('controller exists', function () {
            expect(class_exists({{controller}}::class))->toBeTrue();
        });

        // Add more tests here

        STUB;
    }
}
