<?php

declare(strict_types=1);

namespace Plugs\Console\Commands;

use Plugs\Console\Command;

class MakeProviderCommand extends Command
{
    protected function defineArguments(): array
    {
        return [
            'name' => 'The name of the provider class',
        ];
    }

    public function handle(): int
    {
        $this->checkpoint('start');
        $this->title('Provider Generator');

        $name = $this->argument('0');
        if (!$name) {
            $name = $this->ask('Provider name', 'AppServiceProvider');
        }

        if (!str_ends_with($name, 'Provider')) {
            $name .= 'Provider';
        }

        $path = getcwd() . '/app/Providers/' . $name . '.php';

        $this->section('Configuration Summary');
        $this->keyValue('Provider Name', $name);
        $this->keyValue('Target Path', str_replace(getcwd() . '/', '', $path));
        $this->newLine();

        if (file_exists($path)) {
            $this->error("Provider [{$name}] already exists!");

            return 1;
        }

        if (!$this->confirm('Proceed with generation?', true)) {
            $this->warning('Provider generation cancelled.');

            return 0;
        }

        $this->checkpoint('generating');

        $directory = dirname($path);
        if (!is_dir($directory)) {
            mkdir($directory, 0755, true);
        }

        $stub = $this->getStub($name);

        if (file_put_contents($path, $stub)) {
            $this->checkpoint('finished');

            $this->newLine();
            $this->box(
                "Provider '{$name}' generated successfully!\n\n" .
                "Time: {$this->formatTime($this->elapsed())}",
                "✅ Success",
                "success"
            );

            $this->section('Next Steps');
            $this->bulletList([
                "Register it in config/app.php in the 'providers' array",
                "Implement your bindings in the register() method",
                "Implement your boot logic in the boot() method",
            ]);
            $this->newLine();

            return 0;
        }

        $this->error("Failed to create provider.");

        return 1;
    }

    private function getStub(string $name): string
    {
        return <<<Php
<?php

declare(strict_types=1);

namespace App\Providers;

use Plugs\Support\ServiceProvider;

class {$name} extends ServiceProvider
{
    /**
     * Register any application services.
     *
     * @return void
     */
    public function register()
    {
        // \$this->app->bind('service', function(\$app) {
        //     return new Service();
        // });
    }

    /**
     * Boot any application services.
     *
     * @return void
     */
    public function boot()
    {
        //
    }
}
Php;
    }
}
