<?php

declare(strict_types=1);

namespace Plugs\Console\Commands;

use Plugs\Console\Command;
use Plugs\Console\Support\Filesystem;
use Plugs\Console\Support\Str;

class MakeModuleCommand extends Command
{
    protected string $description = 'Create a new custom module for the framework';

    protected function defineArguments(): array
    {
        return [
            'name' => 'The name of the module (e.g., Payment, Analytics)',
        ];
    }

    protected function defineOptions(): array
    {
        return [
            '--force' => 'Overwrite existing module',
            '--web' => 'Make the module boot only in Web context',
            '--api' => 'Make the module boot only in API context',
        ];
    }

    public function handle(): int
    {
        $this->advancedHeader('Module Generator', 'Scaffolding plug-and-play modules');

        $name = $this->argument('0');

        if (!$name) {
            $name = $this->ask('Module name', 'Custom');
        }

        $className = Str::studly($name);
        if (!str_ends_with($className, 'Module')) {
            $className .= 'Module';
        }

        $directory = getcwd() . '/app/Modules';
        $path = $directory . '/' . $className . '.php';

        if (!Filesystem::isDirectory($directory)) {
            Filesystem::ensureDir($directory);
        }

        if (Filesystem::exists($path) && !$this->hasOption('force')) {
            $this->error("Module {$className} already exists!");
            return 1;
        }

        $context = 'true'; // Default: boot always
        if ($this->hasOption('web')) {
            $context = '$context === \Plugs\Bootstrap\ContextType::Web';
        } elseif ($this->hasOption('api')) {
            $context = '$context === \Plugs\Bootstrap\ContextType::Api';
        }

        $template = $this->getStub();
        $content = str_replace(
            ['{{class}}', '{{name}}', '{{context}}'],
            [$className, str_replace('Module', '', $className), $context],
            $template
        );

        Filesystem::put($path, $content);

        $this->success("Module created successfully at: app/Modules/{$className}.php");

        $this->newLine();
        $this->section('Next Steps');
        $this->numberedList([
            "Register your module in `bootstrap/boot.php` or `Bootstrapper.php` using `ModuleManager::getInstance()->addModule()`.",
            "Implement the `register()` and `boot()` methods in your new module.",
            "Use `Framework::disableModule('{$name}')` to disable it dynamically if needed."
        ]);

        return 0;
    }

    private function getStub(): string
    {
        return <<<'STUB'
<?php

declare(strict_types=1);

namespace App\Modules;

use Plugs\Module\ModuleInterface;
use Plugs\Bootstrap\ContextType;
use Plugs\Container\Container;
use Plugs\Plugs;

class {{class}} implements ModuleInterface
{
    /**
     * Get the unique name for this module.
     */
    public function getName(): string
    {
        return '{{name}}';
    }

    /**
     * Determine if the module should boot based on the current context.
     */
    public function shouldBoot(ContextType $context): bool
    {
        return {{context}};
    }

    /**
     * Register services in the container.
     */
    public function register(Container $container): void
    {
        // $container->singleton('myservice', fn() => new MyService());
    }

    /**
     * Boot logic after all modules are registered.
     */
    public function boot(Plugs $app): void
    {
        // $app->pipe(new CustomMiddleware());
    }
}
STUB;
    }
}
