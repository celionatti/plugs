<?php

declare(strict_types=1);

namespace Plugs\Console\Commands;

use Plugs\Console\Command;
use Plugs\Console\Support\Filesystem;

class InstallCleanupCommand extends Command
{
    protected string $description = 'Safely deletes the public/install/ directory after setup';

    protected function defineOptions(): array
    {
        return [
            '--force' => 'Skip confirmation prompts',
        ];
    }

    public function handle(): int
    {
        $this->checkpoint('start');
        $this->title('Installer Directory Cleanup');

        $installPath = BASE_PATH . 'public/install';

        if (!Filesystem::isDirectory($installPath)) {
            $this->info("Installer directory 'public/install/' does not exist or has already been removed.");
            return self::SUCCESS;
        }

        if (!$this->hasOption('force')) {
            if (!$this->confirm("Are you sure you want to permanently delete the installer directory 'public/install/'? This cannot be undone.", true)) {
                $this->error("Cleanup cancelled.");
                return self::FAILURE;
            }
        }

        $this->task('Deleting installer files', function () use ($installPath) {
            return Filesystem::delete($installPath);
        });

        $this->newLine();
        $this->success("The installer directory has been successfully removed.");

        $this->checkpoint('finished');
        if ($this->isVerbose()) {
            $this->displayTimings();
        }

        return self::SUCCESS;
    }
}
