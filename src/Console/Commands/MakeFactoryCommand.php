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
        $this->checkpoint('start');
        $this->title('Factory Generator');

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

        $path = base_path('database/Factories/' . $name . '.php');

        $this->section('Configuration Summary');
        $this->keyValue('Factory Name', $name);
        $this->keyValue('Target Model', $model);
        $this->keyValue('Target Path', str_replace(base_path(), '', $path));
        $this->newLine();

        if (Filesystem::exists($path) && !$this->isForce()) {
            $this->error("Factory [{$name}] already exists!");
            return 1;
        }

        if (!$this->confirm('Proceed with generation?', true)) {
            $this->warning('Factory generation cancelled.');
            return 0;
        }

        $this->checkpoint('generating');

        $template = $this->getTemplate();
        $content = str_replace(
            ['{{class}}', '{{model}}', '{{modelNamespace}}'],
            [$name, $model, 'App\\Models\\' . $model],
            $template
        );

        Filesystem::put($path, $content);

        $this->checkpoint('finished');

        $this->newLine();
        $this->box(
            "Factory '{$name}' generated successfully!\n\n" .
            "Model: {$model}\n" .
            "Time: {$this->formatTime($this->elapsed())}",
            "✅ Success",
            "success"
        );

        $this->section('Next Steps');
        $this->bulletList([
            "Edit the definition in: database/Factories/{$name}.php",
            "Use it in seeders or tests: {$model}::factory()->create()",
        ]);
        $this->newLine();

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
