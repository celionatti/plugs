<?php

declare(strict_types=1);

namespace Plugs\Console\Commands;

use Plugs\Console\Command;
use Plugs\Config;

class ConfigClearCommand extends Command
{
    protected string $description = 'Remove the configuration cache file';

    public function handle(): int
    {
        $this->title('Clear Configuration Cache');

        $this->task('Removing configuration cache', function () {
            Config::clear();
            return true;
        });

        $this->success('Configuration cache cleared successfully!');

        return 0;
    }
}
