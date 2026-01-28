<?php

declare(strict_types=1);

namespace Plugs\Console\Commands;

use Plugs\Console\Command;
use Plugs\Console\Support\Filesystem;
use Plugs\Console\Support\Str;

/**
 * MakeFactoryCommand
 * 
 * Generate a new factory class.
 * 
 * @package Plugs\Console\Commands
 */
class MakeFactoryCommand extends Command
{
    protected string $description = 'Create a new model factory';

    protected function defineArguments(): array
    {
        return [
            'name' => 'The name of the factory class',
        ];
    }

    protected function defineOptions(): array
    {
        return [
            '--model, -m=MODEL' => 'The model that the factory applies to',
            '--force' => 'Overwrite existing factory',
        ];
    }

    public function handle(): int
    {
        $name = $this->argument('0');
        if (!$name) {
            $name = $this->ask('Factory name', 'UserFactory');
        }

        $name = Str::studly($name);
        if (!str_ends_with($name, 'Factory')) {
            $name .= 'Factory';
        }

        $model = $this->option('model');
        if (!$model) {
            $model = str_replace('Factory', '', $name);
        }
        $model = Str::studly($model);

        $path = getcwd() . '/database/factories/' . $name . '.php';

        if (Filesystem::exists($path) && !$this->isForce()) {
            $this->error("Factory [{$name}] already exists!");
            return 1;
        }

        $template = $this->getTemplate();
        $content = str_replace(
            ['{{class}}', '{{model}}', '{{modelNamespace}}'],
            [$name, $model, 'App\\Models\\' . $model],
            $template
        );

        Filesystem::put($path, $content);

        $this->success("Factory created: {$name}");
        return 0;
    }

    protected function getTemplate(): string
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
            // 'name' => $this->faker->name(),
            // 'email' => $this->faker->unique()->safeEmail(),
        ];
    }
}
STUB;
    }
}
