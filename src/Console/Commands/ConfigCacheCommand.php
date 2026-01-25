<?php

declare(strict_types=1);

namespace Plugs\Console\Commands;

use Plugs\Console\Command;
use Plugs\Config;

class ConfigCacheCommand extends Command
{
    protected string $description = 'Create a configuration cache file for faster loading';

    public function handle(): int
    {
        $this->title('Configuration Cache Generator');

        $this->task('Caching configuration files', function () {
            return Config::cache();
        });

        $this->box(
            "Configuration cached successfully!\n\n" .
            "Your application will now load configuration much faster.",
            "✅ Success",
            "success"
        );

        return 0;
    }
}
