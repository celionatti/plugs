<?php

declare(strict_types=1);

namespace Plugs\Console\Commands;

use Plugs\Console\Command;
use Plugs\Console\Support\Filesystem;
use Plugs\Console\Support\Str;

class MakeAuthModuleCommand extends Command
{
    protected string $description = 'Create a fully functional authentication module';

    protected function defineArguments(): array
    {
        return [];
    }

    protected function defineOptions(): array
    {
        return [
            '--force' => 'Overwrite existing module files',
            '--no-migrate' => 'Skip running migrations',
        ];
    }

    public function handle(): int
    {
        $this->advancedHeader('Auth Module Generator', 'Scaffolding full authentication systems');

        $name = 'Auth';
        $lowerName = 'auth';
        $basePath = getcwd() . '/modules/' . $name;

        if (Filesystem::isDirectory($basePath) && !$this->hasOption('force')) {
            $this->error("Auth module '{$name}' already exists at modules/{$name}/");
            $this->note("Use --force to overwrite existing files.");
            return 1;
        }

        $this->task('Creating directory structure', function () use ($basePath) {
            $directories = [
                $basePath,
                $basePath . '/Controllers',
                $basePath . '/Repositories',
                $basePath . '/Requests',
                $basePath . '/Routes',
                $basePath . '/Services',
                $basePath . '/Views/layouts',
                $basePath . '/Views/components',
            ];

            foreach ($directories as $dir) {
                Filesystem::ensureDir($dir);
            }
        });

        $stubsPath = __DIR__ . '/../Stubs/Auth';

        $filesToGenerate = [
            'AuthModule.php.stub' => $name . 'Module.php',
            'module.json.stub' => 'module.json',
            'Controllers/AuthController.php.stub' => 'Controllers/AuthController.php',
            'Controllers/ForgotPasswordController.php.stub' => 'Controllers/ForgotPasswordController.php',
            'Controllers/ResetPasswordController.php.stub' => 'Controllers/ResetPasswordController.php',
            'Controllers/VerifyEmailController.php.stub' => 'Controllers/VerifyEmailController.php',
            'Repositories/UserRepository.php.stub' => 'Repositories/UserRepository.php',
            'Requests/LoginRequest.php.stub' => 'Requests/LoginRequest.php',
            'Requests/RegisterRequest.php.stub' => 'Requests/RegisterRequest.php',
            'Requests/ForgotPasswordRequest.php.stub' => 'Requests/ForgotPasswordRequest.php',
            'Requests/ResetPasswordRequest.php.stub' => 'Requests/ResetPasswordRequest.php',
            'Routes/web.php.stub' => 'Routes/web.php',
            'Services/AuthService.php.stub' => 'Services/AuthService.php',
            'Views/login.plug.php.stub' => 'Views/login.plug.php',
            'Views/register.plug.php.stub' => 'Views/register.plug.php',
            'Views/forgot-password.plug.php.stub' => 'Views/forgot-password.plug.php',
            'Views/reset-password.plug.php.stub' => 'Views/reset-password.plug.php',
            'Views/verify-email.plug.php.stub' => 'Views/verify-email.plug.php',
            'Views/layouts/auth.plug.php.stub' => 'Views/layouts/auth.plug.php',
            'Views/components/AuthButton.plug.php.stub' => 'Views/components/AuthButton.plug.php',
            'Views/components/AuthInput.plug.php.stub' => 'Views/components/AuthInput.plug.php',
        ];

        foreach ($filesToGenerate as $stub => $destination) {
            $this->task("Generating {$destination}", function () use ($stubsPath, $stub, $basePath, $destination, $name, $lowerName) {
                $content = Filesystem::get($stubsPath . '/' . $stub);
                $content = str_replace(
                    ['{{name}}', '{{lowerName}}'],
                    [$name, $lowerName],
                    $content
                );
                Filesystem::put($basePath . '/' . $destination, $content);
            });
        }

        $this->section('Publishing Global Scaffolding');
        $this->task('Configuring Database & Models', function () {
            $options = [];
            if ($this->hasOption('no-migrate')) {
                $options['--no-migrate'] = true;
            }
            if ($this->hasOption('force')) {
                $options['--force'] = true;
            }
            
            $this->call('auth:install', $options);
            return true;
        });

        $this->newLine();
        $this->box(
            "Auth module '{$name}' scaffolded successfully!\n\n" .
            "Location: modules/{$name}/\n" .
            "Namespace: Modules\\{$name}\\",
            "✅ Auth Module Ready",
            "success"
        );

        $this->section('Next Steps');
        $this->numberedList([
            "Visit `/login` to see your new auth system in action."
        ]);

        return 0;
    }
}
