<?php

declare(strict_types=1);

namespace Plugs\Console\Commands;

use Plugs\Console\Command;
use Plugs\Console\Support\Filesystem;

class MakeEventCommand extends Command
{
    protected string $description = 'Create a new event class';

    public function handle(): int
    {
        $this->title('Event Generator');

        $name = $this->argument('0') ?? $this->ask('Event name', 'ExampleEvent');

        if (!str_ends_with($name, 'Event')) {
            $name .= 'Event';
        }

        $path = $this->getPath($name);

        if (Filesystem::exists($path) && !$this->confirm('File already exists. Overwrite?', false)) {
            $this->warning('Operation cancelled');

            return 0;
        }

        $content = $this->generateEvent($name);
        Filesystem::put($path, $content);

        $this->success("Event '{$name}' generated successfully!");

        return 0;
    }

    private function generateEvent(string $name): string
    {
        return <<<PHP
        <?php

        declare(strict_types=1);

        namespace App\\Events;

        use Plugs\\Event\\Event;

        class {$name} extends Event
        {
            /**
             * Create a new event instance.
             */
            public function __construct()
            {
                //
            }
        }

        PHP;
    }

    private function getPath(string $name): string
    {
        return getcwd() . '/app/Events/' . $name . '.php';
    }
}
