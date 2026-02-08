<?php

declare(strict_types=1);

namespace Plugs\Console\Commands;

use Plugs\Console\Command;
use Plugs\Console\Support\Filesystem;

class MakeListenerCommand extends Command
{
    protected string $description = 'Create a new event listener class';

    public function handle(): int
    {
        $this->title('Listener Generator');

        $name = $this->argument('0') ?? $this->ask('Listener name', 'ExampleListener');

        if (!str_ends_with($name, 'Listener')) {
            $name .= 'Listener';
        }

        $path = $this->getPath($name);

        if (Filesystem::exists($path) && !$this->confirm('File already exists. Overwrite?', false)) {
            $this->warning('Operation cancelled');

            return 0;
        }

        $content = $this->generateListener($name);
        Filesystem::put($path, $content);

        $this->success("Listener '{$name}' generated successfully!");

        return 0;
    }

    private function generateListener(string $name): string
    {
        return <<<PHP
        <?php

        declare(strict_types=1);

        namespace App\\Listeners;

        use Plugs\\Event\\Event;

        class {$name}
        {
            /**
             * Handle the event.
             *
             * @param Event \$event
             * @return void
             */
            public function handle(Event \$event): void
            {
                //
            }
        }

        PHP;
    }

    private function getPath(string $name): string
    {
        return getcwd() . '/app/Listeners/' . $name . '.php';
    }
}
