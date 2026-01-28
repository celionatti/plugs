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
        $name = $this->argument('0');
        if (!$name) {
            $name = $this->ask('Seeder name', 'UserSeeder');
        }

        $name = Str::studly($name);
        if (!str_ends_with($name, 'Seeder')) {
            $name .= 'Seeder';
        }

        $path = getcwd() . '/database/seeders/' . $name . '.php';

        if (Filesystem::exists($path) && !$this->isForce()) {
            $this->error("Seeder [{$name}] already exists!");
            return 1;
        }

        $template = $this->getTemplate();
        $content = str_replace('{{class}}', $name, $template);

        Filesystem::put($path, $content);

        $this->success("Seeder created: {$name}");
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
