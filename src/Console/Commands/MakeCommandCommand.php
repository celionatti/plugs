<?php

declare(strict_types=1);

namespace Plugs\Console\Commands;

/*
|--------------------------------------------------------------------------
| Make: Command Command
|--------------------------------------------------------------------------
*/

use Plugs\Console\Command;
use Plugs\Console\Support\Filesystem;
use Plugs\Console\Support\Str;

class MakeCommandCommand extends Command
{
    protected string $description = 'Create a new console command class';

    public function handle(): int
    {
        $this->checkpoint('start');
        $this->title('Console Command Generator');

        $name = $this->argument('0') ?? $this->ask('Command name', 'ExampleCommand');

        if (!str_ends_with($name, 'Command')) {
            $name .= 'Command';
        }

        $commandName = $this->ask('Console command name', Str::kebab(str_replace('Command', '', $name)));
        $description = $this->ask('Command description', 'A custom console command');

        $path = $this->getCommandPath($name);

        $this->section('Configuration Summary');
        $this->keyValue('Class Name', $name);
        $this->keyValue('Signature', $commandName);
        $this->keyValue('Description', $description);
        $this->keyValue('Target Path', str_replace(getcwd() . '/', '', $path));
        $this->newLine();

        if (Filesystem::exists($path) && !$this->confirm('File already exists. Overwrite?', false)) {
            $this->warning('Operation cancelled');
            return 0;
        }

        $this->checkpoint('generating');

        $content = $this->generateCommand($name, $commandName, $description);
        Filesystem::put($path, $content);

        $this->checkpoint('finished');

        $this->newLine();
        $this->box(
            "Console command '{$name}' generated successfully!\n\n" .
            "Signature: {$commandName}\n" .
            "Time: {$this->formatTime($this->elapsed())}",
            "✅ Success",
            "success"
        );

        $this->section('Next Steps');
        $this->bulletList([
            "Register the command in src/Console/ConsoleKernel.php",
            "Implement your logic in handle() method",
            "Run it: php theplugs {$commandName}",
        ]);
        $this->newLine();

        if ($this->isVerbose()) {
            $this->displayTimings();
        }

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
