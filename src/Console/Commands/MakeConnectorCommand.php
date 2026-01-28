<?php

declare(strict_types=1);

namespace Plugs\Console\Commands;

use Plugs\Console\Command;
use Plugs\Console\Support\Filesystem;
use Plugs\Console\Support\Str;

class MakeConnectorCommand extends Command
{
    protected string $description = 'Create a new API connector class';

    public function __construct(string $name)
    {
        parent::__construct($name);
    }

    protected function defineArguments(): array
    {
        return [
            'name' => 'The name of the connector class (e.g. Stripe)',
        ];
    }

    protected function defineOptions(): array
    {
        return [
            '--force' => 'Overwrite existing file',
            '--base-url=URL' => 'The base URL for the connector',
        ];
    }

    public function handle(): int
    {
        $this->checkpoint('start');
        $this->title('API Connector Generator');

        $name = $this->argument('0');

        if (!$name) {
            $name = $this->ask('Connector Name (e.g. Stripe)');
        }

        if (!str_ends_with($name, 'Connector')) {
            $name .= 'Connector';
        }

        $name = Str::studly($name);
        $integrationName = str_replace('Connector', '', $name);

        $path = getcwd() . "/app/Http/Integrations/{$integrationName}/{$name}.php";

        $this->section('Configuration Summary');
        $this->keyValue('Connector Name', $name);
        $this->keyValue('Integration', $integrationName);
        $this->keyValue('Target Path', str_replace(getcwd() . '/', '', $path));
        $this->newLine();

        if (Filesystem::exists($path) && !$this->isForce()) {
            if (!$this->confirm("Connector already exists at {$path}. Overwrite?", false)) {
                $this->warning('Connector generation cancelled.');
                return 0;
            }
        }

        $this->checkpoint('generating');

        $baseUrl = $this->option('base-url') ?? '';

        $this->task('Generating API Connector class', function () use ($integrationName, $name, $baseUrl, $path) {
            $content = $this->generateContent($integrationName, $name, $baseUrl);
            Filesystem::put($path, $content);
            usleep(200000);
        });

        $this->checkpoint('finished');

        $this->newLine(2);
        $this->box(
            "API Connector '{$name}' generated successfully!\n\n" .
            "Integration: {$integrationName}\n" .
            "Time: {$this->formatTime($this->elapsed())}",
            "✅ Success",
            "success"
        );

        if ($this->isVerbose()) {
            $this->displayTimings();
        }

        return 0;
    }

    private function generateContent(string $integrationName, string $className, string $baseUrl): string
    {
        return <<<PHP
<?php

namespace App\Http\Integrations\\{$integrationName};

use Plugs\Http\Integration\Connector;

class {$className} extends Connector
{
    /**
     * Resolve the base URL for the connector.
     */
    public function resolveBaseUrl(): string
    {
        return '{$baseUrl}';
    }

    /**
     * Get the default headers for all requests.
     */
    public function headers(): array
    {
        return [
            'Accept' => 'application/json',
            'Content-Type' => 'application/json',
        ];
    }

    /**
     * Get the default configuration for the connector.
     */
    public function defaultConfig(): array
    {
        return [
            'timeout' => 30,
        ];
    }
}
PHP;
    }
}
