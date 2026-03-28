<?php

declare(strict_types=1);

namespace Plugs\Console\Commands;

use Plugs\Console\Command;
use Plugs\Console\Support\Filesystem;

class MakeEventCommand extends Command
{
    protected string $signature = 'make:event {name? : The event class name} {--broadcast : Generate a broadcastable event}';
    protected string $description = 'Create a new event class';

    public function handle(): int
    {
        $this->title('Event Generator');

        $name = $this->argument('0') ?? $this->ask('Event name', 'ExampleEvent');

        if (!str_ends_with($name, 'Event')) {
            $name .= 'Event';
        }

        $broadcast = $this->hasOption('broadcast') && $this->option('broadcast');

        $path = $this->getPath($name);

        if (Filesystem::exists($path) && !$this->confirm('File already exists. Overwrite?', false)) {
            $this->warning('Operation cancelled');

            return 0;
        }

        $content = $broadcast
            ? $this->generateBroadcastEvent($name)
            : $this->generateEvent($name);

        Filesystem::put($path, $content);

        $this->success("Event '{$name}' generated successfully!");

        if ($broadcast) {
            $this->info("  → Implements ShouldBroadcast");
            $this->info("  → Override broadcastOn() to set the channel");
            $this->info("  → Override broadcastWith() to customize the payload");
        }

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

    private function generateBroadcastEvent(string $name): string
    {
        // Derive a sensible default topic name from the class name
        // e.g. 'NewMessageEvent' → 'NewMessage'
        $topicName = str_ends_with($name, 'Event')
            ? substr($name, 0, -5)
            : $name;

        return <<<PHP
        <?php

        declare(strict_types=1);

        namespace App\\Events;

        use Plugs\\Event\\Event;
        use Plugs\\Broadcasting\\ShouldBroadcast;
        use Plugs\\Broadcasting\\Channel;
        use Plugs\\Broadcasting\\PrivateChannel;

        class {$name} extends Event implements ShouldBroadcast
        {
            /**
             * Create a new broadcastable event instance.
             */
            public function __construct(
                // Define your event properties here
                // public readonly int \$userId,
                // public readonly string \$message,
            ) {
            }

            /**
             * Get the channel(s) the event should broadcast on.
             *
             * Return a channel name string, Channel object, or an array of either.
             *
             * Examples:
             *   return 'chat';                           // Public channel
             *   return new PrivateChannel('user.' . \$this->userId); // Private
             *   return ['chat', new PrivateChannel('admin')];         // Multiple
             */
            public function broadcastOn(): string|array|Channel
            {
                return 'general';
            }

            /**
             * Get the SSE event/topic name.
             *
             * This is the event name clients listen for:
             *   echo.channel('general').listen('{$topicName}', callback);
             */
            public function broadcastAs(): string
            {
                return '{$topicName}';
            }

            /**
             * Get the data payload to broadcast to clients.
             *
             * Return an associative array of data. This will be
             * JSON-encoded and delivered via SSE.
             */
            public function broadcastWith(): array
            {
                return [
                    // 'user_id' => \$this->userId,
                    // 'message' => \$this->message,
                    'timestamp' => time(),
                ];
            }
        }

        PHP;
    }

    private function getPath(string $name): string
    {
        return getcwd() . '/app/Events/' . $name . '.php';
    }
}
