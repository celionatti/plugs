<?php

declare(strict_types=1);

namespace Plugs\Console\Commands;

/*
|--------------------------------------------------------------------------
| Make: Component Command
|--------------------------------------------------------------------------
*/

use Plugs\Console\Command;
use Plugs\Console\Support\Str;

class MakeComponentCommand extends Command
{
    protected string $description = 'Create a new view component or reactive Bolt component';

    protected function defineArguments(): array
    {
        return [
            'name' => 'The name of the component',
        ];
    }

    protected function defineOptions(): array
    {
        return [
            '--bolt, -b' => 'Create a reactive Bolt component',
            '--force, -f' => 'Overwrite existing files',
        ];
    }

    public function handle(): int
    {
        $this->title('Component Generator');

        $name = $this->argument('0');

        if (!$name) {
            $name = $this->ask('Component name', 'Alert');
        }

        $isBolt = $this->hasOption('bolt') || $this->hasOption('b');
        $force = $this->isForce();

        $className = Str::studly($name);
        $viewName = Str::kebab($name);

        $this->section('Generating Component');

        if ($isBolt) {
            $this->generateBoltComponent($className, $viewName, $force);
        } else {
            $this->generateSimpleComponent($viewName, $force);
        }

        $this->newLine();
        $this->success("Component '{$className}' generated successfully.");

        return 0;
    }

    private function generateSimpleComponent(string $viewName, bool $force): void
    {
        $viewPath = getcwd() . "/resources/views/components/{$viewName}.plug.php";

        if (file_exists($viewPath) && !$force) {
            if (!$this->confirm("View for {$viewName} already exists. Overwrite?", false)) {
                $this->warning("View generation skipped.");

                return;
            }
        }

        $this->task("Creating view template", function () use ($viewPath) {
            $content = $this->getSimpleViewStub();
            $this->writeFile($viewPath, $content);
        });

        $this->info("View created: resources/views/components/{$viewName}.plug.php");
    }

    private function generateBoltComponent(string $className, string $viewName, bool $force): void
    {
        $classPath = getcwd() . "/app/Components/{$className}.php";
        $viewPath = getcwd() . "/resources/views/components/{$viewName}.plug.php";

        // Handle Class
        if (file_exists($classPath) && !$force) {
            if (!$this->confirm("Class for {$className} already exists. Overwrite?", false)) {
                $this->warning("Class generation skipped.");

                return;
            }
        }

        $this->task("Creating component class", function () use ($classPath, $className, $viewName) {
            $content = $this->getBoltClassStub($className, $viewName);
            $this->writeFile($classPath, $content);
        });

        // Handle View
        if (file_exists($viewPath) && !$force) {
            if (!$this->confirm("View for {$viewName} already exists. Overwrite?", false)) {
                $this->warning("View generation skipped.");

                return;
            }
        }

        $this->task("Creating view template", function () use ($viewPath) {
            $content = $this->getBoltViewStub();
            $this->writeFile($viewPath, $content);
        });

        $this->info("Class created: app/Components/{$className}.php");
        $this->info("View created: resources/views/components/{$viewName}.plug.php");
    }

    private function getSimpleViewStub(): string
    {
        return <<<'HTML'
<div {{ $attributes->merge(['class' => 'component-wrapper']) }}>
    {{ $slot }}
</div>
HTML;
    }

    private function getBoltClassStub(string $className, string $viewName): string
    {
        return <<<PHP
<?php

declare(strict_types=1);

namespace App\Components;

use Plugs\View\ReactiveComponent;

class {$className} extends ReactiveComponent
{
    public string \$message = 'Hello from Bolt!';

    public function handleAction(): void
    {
        \$this->message = 'Action triggered at: ' . date('H:i:s');
    }

    public function render(): string
    {
        return 'components.{$viewName}';
    }
}
PHP;
    }

    private function getBoltViewStub(): string
    {
        return <<<'HTML'
<div class="bolt-component">
    <p>{{ $message }}</p>
    <button p-click="handleAction" class="btn btn-primary">
        Trigger Action
    </button>
</div>
HTML;
    }
}
