<?php

declare(strict_types=1);

namespace Plugs\Console\Commands;

use Plugs\Console\Command;
use Plugs\Console\Support\Filesystem;
use Plugs\Console\Traits\RegistersModules;

class AdminInstallCommand extends Command
{
    use RegistersModules;
    protected string $description = 'Install the Monochrome Admin Panel module';

    protected function defineOptions(): array
    {
        return [
            '--force' => 'Overwrite existing Admin module if it exists',
            '--no-migrate' => 'Skip running migrations',
        ];
    }

    public function handle(): int
    {
        $this->title('Admin Panel Installation');

        // Ensure Auth module is installed first
        if (!$this->isAuthInstalled()) {
            $this->info('Admin module requires Auth module. Installing full Auth module first...');
            $this->call('make:auth-module', [
                '--no-migrate' => true,
            ]);
        }

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

        $this->task('Installing Admin module files', function () use ($stubPath, $destinationPath) {
            return Filesystem::copyDirectory($stubPath, $destinationPath);
        });

        $this->task('Publishing database migrations', function () {
            return $this->publishMigrations();
        });

        $this->task('Publishing database migrations', function () {
            return $this->publishMigrations();
        });

        // Clean up the copied migration stub from modules/Admin/Migrations
        $migrationStubDir = $destinationPath . '/Migrations';
        if (Filesystem::isDirectory($migrationStubDir)) {
            $existingStub = $migrationStubDir . '/create_settings_table.stub';
            if (Filesystem::exists($existingStub)) {
                @unlink($existingStub);
            }
        }

        if (!$this->hasOption('no-migrate')) {
            $this->task('Running database migrations', function () {
                $this->call('migrate');
                return true;
            });
        }

        $this->newLine();
        $this->note("The Administrative module has been automatically registered in config/modules.php.");

        // Register the module in the config file
        $this->registerModuleInConfig('Admin');

        return 0;
    }



    private function publishMigrations(): bool
    {
        $stubsDir = __DIR__ . '/../Stubs/Admin/Migrations';
        $targetDir = getcwd() . '/modules/Admin/Migrations';

        Filesystem::ensureDir($targetDir);

        $migrations = [
            'create_settings_table.stub' => 'create_settings_table.php',
        ];

        foreach ($migrations as $stubName => $fileName) {
            $stubFile = $stubsDir . '/' . $stubName;
            
            $existing = glob($targetDir . '/*_' . $fileName);
            if (!empty($existing) && !$this->hasOption('force')) {
                $this->fileSkipped($existing[0]);
                continue;
            }

            $timestamp = date('Y_m_d_His');
            $newFileName = $timestamp . '_' . $fileName;
            $destination = $targetDir . '/' . $newFileName;

            if (Filesystem::exists($stubFile)) {
                $content = Filesystem::get($stubFile);
                Filesystem::put($destination, $content);
                $this->fileCreated($destination);
                sleep(1); // Ensure unique timestamps
            }
        }

        return true;
    }



    /**
     * Check if Auth module is installed.
     */
    private function isAuthInstalled(): bool
    {
        $authModulePath = getcwd() . '/modules/Auth';
        $userModelPath = getcwd() . '/app/Models/User.php';

        return Filesystem::isDirectory($authModulePath) || Filesystem::exists($userModelPath);
    }
}
