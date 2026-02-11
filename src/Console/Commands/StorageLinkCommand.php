<?php

declare(strict_types=1);

namespace Plugs\Console\Commands;

use Plugs\Console\Command;

class StorageLinkCommand extends Command
{
    protected string $description = 'Create the symbolic link from "public/storage" to "storage/app/public"';

    public function handle(): int
    {
        $this->checkpoint('start');
        $this->title('Storage Management');

        $target = storage_path('app/public');
        $link = public_path('storage');

        $this->section('Configuration');
        $this->keyValue('Target Path', str_replace(getcwd() . '/', '', $target));
        $this->keyValue('Link Path', str_replace(getcwd() . '/', '', $link));
        $this->newLine();

        if (file_exists($link)) {
            $this->error('The "public/storage" link already exists.');
            $this->checkpoint('finished');

            return 1;
        }

        $this->checkpoint('linking');

        if (!file_exists($target)) {
            $this->task('Creating target directory', function () use ($target) {
                if (!mkdir($target, 0755, true)) {
                    throw new \RuntimeException("Could not create target directory: {$target}");
                }
                usleep(150000);
            });
        }

        $this->task('Creating symbolic link', function () use ($target, $link) {
            try {
                if (!@symlink($target, $link)) {
                    throw new \RuntimeException("Failed to create symbolic link.");
                }
            } catch (\Throwable $e) {
                if (str_contains(PHP_OS, 'WIN')) {
                    $this->newLine();
                    $this->error("Windows Permission Error: Symlink creation failed.");
                    $this->newLine();
                    $this->info("To fix this, try one of the following:");
                    $this->info("1. Run your terminal (CMD or PowerShell) as Administrator.");
                    $this->info("2. Enable Windows Developer Mode in your system settings.");
                    $this->newLine();
                }
                throw new \RuntimeException($e->getMessage() ?: "Failed to create symbolic link.");
            }
            usleep(200000);
        });

        $this->checkpoint('finished');

        $this->newLine(2);
        $this->box(
            "Storage link created successfully!\n\n" .
            "The \"public/storage\" directory has been linked to \"storage/app/public\".\n" .
            "Time: {$this->formatTime($this->elapsed())}",
            "✅ Link Established",
            "success"
        );

        if ($this->isVerbose()) {
            $this->displayTimings();
        }

        return 0;
    }
}
