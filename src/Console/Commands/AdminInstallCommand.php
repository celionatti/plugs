<?php

declare(strict_types=1);

namespace Plugs\Console\Commands;

use Plugs\Console\Command;
use Plugs\Console\Support\Filesystem;

class AdminInstallCommand extends Command
{
    protected string $description = 'Install the Monochrome Admin Panel module';

    protected function defineOptions(): array
    {
        return [
            '--force' => 'Overwrite existing Admin module if it exists',
        ];
    }

    public function handle(): int
    {
        $this->title('Admin Panel Installation');

        $stubPath = __DIR__ . '/../Stubs/Admin';
        $destinationPath = getcwd() . '/modules/Admin';

        if (!Filesystem::isDirectory($stubPath)) {
            $this->error("Admin module stubs not found at: {$stubPath}");
            return 1;
        }

        if (Filesystem::isDirectory($destinationPath) && !$this->hasOption('force')) {
            $this->warning("Admin module already exists at modules/Admin/");
            $this->note("Use --force to overwrite existing files.");
            return 1;
        }

        $this->task('Copying Admin module files', function () use ($stubPath, $destinationPath) {
            return Filesystem::copyDirectory($stubPath, $destinationPath);
        });

        $this->newLine();
        $this->box(
            "Monochrome Admin Panel installed successfully!\n\n" .
            "Location: modules/Admin/\n" .
            "Route Prefix: /admin",
            "✅ Success",
            "success"
        );

        $this->section('Next Steps');
        $this->numberedList([
            "Verify Middleware: Ensure 'admin' is registered in config/middleware.php",
            "Authentication: Make sure users table exists and you have an admin user.",
            "Access: Visit http://localhost/admin to see your new dashboard.",
        ]);

        return 0;
    }
}
