<?php

declare(strict_types=1);

namespace Plugs\Console\Commands;

use Plugs\Console\Command;
use Plugs\Console\Support\Filesystem;
use Plugs\Console\Traits\RegistersModules;

class PaymentInstallCommand extends Command
{
    use RegistersModules;
    protected string $description = 'Install the Payment module and checkout frontend';

    protected function defineOptions(): array
    {
        return [
            '--force' => 'Overwrite existing Payment module if it exists',
        ];
    }

    public function handle(): int
    {
        $this->title('Payment Module Installation');

        $stubPath = __DIR__ . '/../Stubs/Payment';
        $destinationModulePath = getcwd() . '/modules/Payment';
        $destinationControllerPath = getcwd() . '/app/Http/Controllers/PaymentController.php';
        $destinationViewsPath = getcwd() . '/resources/views/payment';

        if (!Filesystem::isDirectory($stubPath)) {
            $this->error("Payment module stubs not found at: {$stubPath}");
            return 1;
        }

        if (Filesystem::isDirectory($destinationModulePath) && !$this->hasOption('force')) {
            $this->warning("Payment module already exists at modules/Payment/");
            $this->note("Use --force to overwrite existing files.");
            return 1;
        }

        $this->task('Installing Payment module files', function () use ($stubPath, $destinationModulePath) {
            return Filesystem::copyDirectory($stubPath . '/Module', $destinationModulePath);
        });

        $this->task('Publishing Payment frontend controller', function () use ($stubPath, $destinationControllerPath) {
            $controllerStub = $stubPath . '/Controllers/PaymentController.stub';
            if (Filesystem::exists($controllerStub)) {
                $content = Filesystem::get($controllerStub);
                Filesystem::ensureDir(dirname($destinationControllerPath));
                Filesystem::put($destinationControllerPath, $content);
                return true;
            }
            return false;
        });

        $this->task('Publishing Payment frontend views', function () use ($stubPath, $destinationViewsPath) {
            $viewsStubDir = $stubPath . '/Views';
            if (Filesystem::isDirectory($viewsStubDir)) {
                Filesystem::ensureDir($destinationViewsPath);
                return Filesystem::copyDirectory($viewsStubDir, $destinationViewsPath);
            }
            return false;
        });

        $this->newLine();
        $this->note("The Payment module has been automatically registered in config/modules.php.");

        // Register the module in the config file
        $this->registerModuleInConfig('Payment');

        return 0;
    }
}
