<?php

declare(strict_types=1);

namespace Plugs\Console\Commands;

use Plugs\Console\Command;

class StorageLinkCommand extends Command
{
    protected string $description = 'Create the symbolic link from "public/storage" to "storage/app/public"';

    public function handle(): int
    {
        $target = storage_path('app/public');
        $link = public_path('storage');

        if (file_exists($link)) {
            $this->error('The "public/storage" link already exists.');
            return 1;
        }

        if (!file_exists($target)) {
            if (!mkdir($target, 0755, true)) {
                $this->error("The target directory \"{$target}\" does not exist and could not be created.");
                return 1;
            }
        }

        $this->info("Creating link from \"{$link}\" to \"{$target}\"...");

        if (symlink($target, $link)) {
            $this->output->success('The "public/storage" link has been connected.');
            return 0;
        }

        $this->error('Failed to create symbolic link.');
        return 1;
    }
}
