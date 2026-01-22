<?php

declare(strict_types=1);

namespace Plugs\Console\Commands;

use Plugs\Console\Command;

class MakeProviderCommand extends Command
{
    protected string $signature = 'make:provider {name : The name of the provider}';
    protected string $description = 'Create a new service provider class';

    public function handle(): int
    {
        $name = $this->argument('name');

        if (!str_ends_with($name, 'Provider')) {
            $name .= 'Provider';
        }

        $directory = base_path('app/Providers');
        if (!is_dir($directory)) {
            mkdir($directory, 0755, true);
        }

        $path = $directory . '/' . $name . '.php';

        if (file_exists($path)) {
            $this->error("Provider [{$name}] already exists!");
            return 1;
        }

        $stub = $this->getStub($name);

        if (file_put_contents($path, $stub)) {
            $this->output->success("Provider [{$name}] created successfully.");
            $this->info("Don't forget to register it in config/app.php!");
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
