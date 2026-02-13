<?php

declare(strict_types=1);

namespace Plugs\Console\Commands;

use Plugs\Console\Command;
use Plugs\Support\OpCacheManager;

class OpCacheClearCommand extends Command
{
    protected string $name = 'opcache:clear';
    protected string $description = 'Clear the OPcache to reload PHP scripts.';

    public function handle(): int
    {
        $this->output->info('Clearing OPcache...');

        $manager = new OpCacheManager();

        if (!$manager->isEnabled()) {
            $this->output->warning('OPcache is not enabled.');

            return 1;
        }

        if ($manager->clear()) {
            $this->output->success('OPcache cleared successfully.');

            return 0;
        }

        $this->output->error('Failed to clear OPcache.');

        return 1;
    }
}
