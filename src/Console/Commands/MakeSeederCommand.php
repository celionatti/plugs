<?php

declare(strict_types=1);

namespace Plugs\Console\Commands;

use Plugs\Console\Command;
use Plugs\Console\Support\Filesystem;
use Plugs\Console\Support\Str;

/**
 * MakeSeederCommand
 * 
 * Generate a new seeder class.
 * 
 * @package Plugs\Console\Commands
 */
class MakeSeederCommand extends Command
{
    protected string $description = 'Create a new database seeder';

    protected function defineArguments(): array
    {
        return [
            'name' => 'The name of the seeder class',
        ];
    }

    protected function defineOptions(): array
    {
        return [
            '--force' => 'Overwrite existing seeder',
        ];
    }

    public function handle(): int
    {
        $this->checkpoint('start');
        $this->title('Seeder Generator');

        $name = $this->argument('0');
        if (!$name) {
            $name = $this->ask('Seeder name', 'UserSeeder');
        }

        $name = Str::studly($name);
        if (!str_ends_with($name, 'Seeder')) {
            $name .= 'Seeder';
        }

        $path = BASE_PATH . 'database/Seeders/' . $name . '.php';

        $this->section('Configuration Summary');
        $this->keyValue('Seeder Name', $name);
        $this->keyValue('Target Path', str_replace(BASE_PATH, '', $path));
        $this->newLine();

        if (Filesystem::exists($path) && !$this->isForce()) {
            $this->error("Seeder [{$name}] already exists!");
            return 1;
        }

        if (!$this->confirm('Proceed with generation?', true)) {
            $this->warning('Seeder generation cancelled.');
            return 0;
        }

        $this->checkpoint('generating');

        $template = $this->getTemplate();
        $content = str_replace('{{class}}', $name, $template);

        Filesystem::put($path, $content);

        $this->checkpoint('finished');

        $this->newLine();
        $this->box(
            "Seeder '{$name}' generated successfully!\n\n" .
            "Time: {$this->formatTime($this->elapsed())}",
            "✅ Success",
            "success"
        );

        $this->section('Next Steps');
        $this->bulletList([
            "Edit the run logic in: database/Seeders/{$name}.php",
            "Call it from DatabaseSeeder or run directly: php theplugs db:seed --class={$name}",
        ]);
        $this->newLine();

        return 0;
    }

    protected function getTemplate(): string
    {
        return <<<'STUB'
<?php

declare(strict_types=1);

namespace Database\Seeders;

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
        //
    }
}
STUB;
    }
}
