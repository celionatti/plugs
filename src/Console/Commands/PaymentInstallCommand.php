<?php

declare(strict_types=1);

namespace Plugs\Console\Commands;

use Plugs\Console\Command;
use Plugs\Console\Support\Filesystem;

class PaymentInstallCommand extends Command
{
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
        $this->box(
            "Payment Module installed successfully!\n\n" .
            "1. Location: modules/Payment/\n" .
            "2. Admin Route: /admin/payment\n" .
            "3. Frontend Checkout: /payment/checkout\n" .
            "4. Multi-Platform Test: /payment/test\n\n" .
            "IMPORTANT: Please add the following to your routes/web.php:\n\n" .
            "use App\Http\Controllers\PaymentController;\n" .
            "Route::get('/payment/checkout', [PaymentController::class, 'checkout'])->name('payment.checkout');\n" .
            "Route::post('/payment/checkout', [PaymentController::class, 'process'])->name('payment.process');\n" .
            "Route::get('/payment/verify', [PaymentController::class, 'verify'])->name('payment.verify');\n" .
            "Route::get('/payment/test', [PaymentController::class, 'test'])->name('payment.test');",
            "✅ Success",
            "success"
        );

        return 0;
    }
}
