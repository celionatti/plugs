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
            '--no-migrate' => 'Skip running migrations',
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

        $this->task('Installing Admin module files', function () use ($stubPath, $destinationPath) {
            // Copy everything except Migrations and Models folders (since we handle those separately)
            $success = Filesystem::copyDirectory($stubPath, $destinationPath);
            if (Filesystem::isDirectory($destinationPath . '/Migrations')) {
                Filesystem::deleteDirectory($destinationPath . '/Migrations');
            }
            if (Filesystem::isDirectory($destinationPath . '/Models')) {
                Filesystem::deleteDirectory($destinationPath . '/Models');
            }
            return $success;
        });

        $this->task('Publishing database migrations', function () {
            return $this->publishMigrations();
        });

        $this->task('Publishing models', function () {
            return $this->publishModels();
        });

        $this->task('Publishing services', function () {
            return $this->publishServices();
        });

        if (!$this->hasOption('no-migrate')) {
            $this->task('Running database migrations', function () {
                $this->call('migrate');
                return true;
            });
        }

        $this->newLine();
        $this->box(
            "Monochrome Admin Panel installed successfully!\n\n" .
            "Location: modules/Admin/\n" .
            "Route Prefix: /admin",
            "✅ Success",
            "success"
        );

        return 0;
    }

    private function publishModels(): bool
    {
        $stubsDir = __DIR__ . '/../Stubs/Admin/Models';
        $targetDir = getcwd() . '/app/Models';

        Filesystem::ensureDir($targetDir);

        $models = [
            'User.stub' => 'User.php',
            'Setting.stub' => 'Setting.php',
            'Article.stub' => 'Article.php',
        ];

        foreach ($models as $stubName => $fileName) {
            $stubFile = $stubsDir . '/' . $stubName;
            $destination = $targetDir . '/' . $fileName;
            
            if (Filesystem::exists($destination) && !$this->hasOption('force')) {
                $this->info("  - Model already exists: {$fileName}");
                continue;
            }

            if (Filesystem::exists($stubFile)) {
                $content = Filesystem::get($stubFile);
                Filesystem::put($destination, $content);
                $this->info("  - Published: {$fileName}");
            }
        }

        return true;
    }

    private function publishMigrations(): bool
    {
        $stubsDir = __DIR__ . '/../Stubs/Admin/Migrations';
        $targetDir = getcwd() . '/database/migrations';

        Filesystem::ensureDir($targetDir);

        $migrations = [
            'create_users_table.stub' => 'create_users_table.php',
            'create_settings_table.stub' => 'create_settings_table.php',
            'create_articles_table.stub' => 'create_articles_table.php',
            'add_author_id_to_articles.stub' => 'add_author_id_to_articles.php',
        ];

        foreach ($migrations as $stubName => $fileName) {
            $stubFile = $stubsDir . '/' . $stubName;
            
            // Check if migration already exists (checking for the file name suffix)
            $existing = glob($targetDir . '/*_' . $fileName);
            if (!empty($existing) && !$this->hasOption('force')) {
                $this->info("  - Migration already exists: {$fileName}");
                continue;
            }

            $timestamp = date('Y_m_d_His');
            $newFileName = $timestamp . '_' . $fileName;
            $destination = $targetDir . '/' . $newFileName;

            if (Filesystem::exists($stubFile)) {
                $content = Filesystem::get($stubFile);
                Filesystem::put($destination, $content);
                $this->info("  - Published: {$newFileName}");
                sleep(1); // Ensure unique timestamps
            }
        }

        return true;
    }

    private function publishServices(): bool
    {
        $stubsDir = __DIR__ . '/../Stubs/Admin/Services';

        // Service class → app/Services/
        $serviceStubs = [
            'ModuleService.stub' => 'ModuleService.php',
        ];

        $servicesDir = getcwd() . '/app/Services';
        Filesystem::ensureDir($servicesDir);

        foreach ($serviceStubs as $stubName => $fileName) {
            $this->publishStub($stubsDir, $servicesDir, $stubName, $fileName);
        }

        // Module infrastructure classes → app/Modules/
        $moduleStubs = [
            'ModuleData.stub'       => 'ModuleData.php',
            'ModuleRepository.stub' => 'ModuleRepository.php',
            'ModuleScaffolder.stub' => 'ModuleScaffolder.php',
        ];

        $modulesDir = getcwd() . '/app/Modules';
        Filesystem::ensureDir($modulesDir);

        foreach ($moduleStubs as $stubName => $fileName) {
            $this->publishStub($stubsDir, $modulesDir, $stubName, $fileName);
        }

        return true;
    }

    private function publishStub(string $stubsDir, string $targetDir, string $stubName, string $fileName): void
    {
        $stubFile = $stubsDir . '/' . $stubName;
        $destination = $targetDir . '/' . $fileName;

        if (Filesystem::exists($destination) && !$this->hasOption('force')) {
            $this->info("  - Already exists: {$fileName}");
            return;
        }

        if (Filesystem::exists($stubFile)) {
            $content = Filesystem::get($stubFile);
            Filesystem::put($destination, $content);
            $this->info("  - Published: {$fileName}");
        }
    }
}
