<?php

declare(strict_types=1);

namespace Plugs\Console\Commands;

/*
|--------------------------------------------------------------------------
| Make: Command Command
|--------------------------------------------------------------------------
*/

use Plugs\Console\Command;
use Plugs\Console\Support\Str;
use Plugs\Console\Support\Filesystem;

class MakeCommandCommand extends Command
{
    protected string $description = 'Create a new console command class';

    public function handle(): int
    {
        $name = $this->argument('0') ?? $this->ask('Command name', 'ExampleCommand');
        
        if (!str_ends_with($name, 'Command')) {
            $name .= 'Command';
        }
        
        $commandName = $this->ask('Console command name', Str::kebab(str_replace('Command', '', $name)));
        $description = $this->ask('Command description', 'A custom console command');
        
        $this->checkpoint('input_collected');
        
        $content = $this->generateCommand($name, $commandName, $description);
        $path = $this->getCommandPath($name);
        
        if (Filesystem::exists($path) && !$this->confirm('File exists. Overwrite?', false)) {
            $this->warning('Operation cancelled');
            return 0;
        }
        
        Filesystem::put($path, $content);
        
        $this->success("Command created: {$path}");
        $this->info("Register it in ConsoleKernel:");
        $this->line("  '{$commandName}' => {$name}::class,");
        
        return 0;
    }

    private function generateCommand(string $name, string $commandName, string $description): string
    {
        return <<<PHP
        <?php

        declare(strict_types=1);

        namespace App\\Console\\Commands;

        use Plugs\\Console\\Command;

        class {$name} extends Command
        {
            protected string \$description = '{$description}';

            public function handle(): int
            {
                \$this->info('Executing {$commandName} command...');
                
                // Your command logic here
                
                \$this->success('Command completed successfully!');
                
                return 0;
            }
        }

        PHP;
    }

    private function getCommandPath(string $name): string
    {
        return getcwd() . '/app/Console/Commands/' . $name . '.php';
    }
}
