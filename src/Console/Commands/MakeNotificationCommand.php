<?php

declare(strict_types=1);

namespace Plugs\Console\Commands;

use Plugs\Console\Command;
use Plugs\Console\Support\Filesystem;

class MakeNotificationCommand extends Command
{
    protected string $description = 'Create a new notification class';

    public function handle(): int
    {
        $this->title('Notification Generator');

        $name = $this->argument('0') ?? $this->ask('Notification name', 'ExampleNotification');

        if (!str_ends_with($name, 'Notification')) {
            $name .= 'Notification';
        }

        $path = $this->getPath($name);

        if (Filesystem::exists($path) && !$this->confirm('File already exists. Overwrite?', false)) {
            $this->warning('Operation cancelled');
            return 0;
        }

        $content = $this->generateNotification($name);
        Filesystem::put($path, $content);

        $this->success("Notification '{$name}' generated successfully!");

        return 0;
    }

    private function generateNotification(string $name): string
    {
        return <<<PHP
        <?php

        declare(strict_types=1);

        namespace App\\Notifications;

        use Plugs\\Notification\\Notification;

        class {$name} extends Notification
        {
            /**
             * Create a new notification instance.
             */
            public function __construct()
            {
                //
            }

            /**
             * Get the channels the notification should be sent on.
             *
             * @param mixed \$notifiable
             * @return array
             */
            public function via(\$notifiable): array
            {
                return ['mail'];
            }

            /**
             * Get the mail representation of the notification.
             *
             * @param mixed \$notifiable
             * @return array|string
             */
            public function toMail(\$notifiable)
            {
                return [
                    'subject' => 'Notification Subject',
                    'body' => 'The body of the notification.',
                ];
            }
        }

        PHP;
    }

    private function getPath(string $name): string
    {
        return getcwd() . '/app/Notifications/' . $name . '.php';
    }
}
