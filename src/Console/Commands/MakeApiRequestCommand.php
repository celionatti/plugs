<?php

declare(strict_types=1);

namespace Plugs\Console\Commands;

use Plugs\Console\Command;
use Plugs\Console\Support\Filesystem;
use Plugs\Console\Support\Str;
use Plugs\Http\Integration\Enums\Method;

class MakeApiRequestCommand extends Command
{
    protected string $description = 'Create a new API request class';

    public function __construct(string $name)
    {
        parent::__construct($name);
    }

    protected function defineArguments(): array
    {
        return [
            'name' => 'The name of the request class (e.g. GetUser)',
            'connector' => 'The name of the integration/connector (e.g. Stripe)',
        ];
    }

    protected function defineOptions(): array
    {
        return [
            '--force' => 'Overwrite existing file',
            '--method=METHOD' => 'HTTP Method (GET, POST, etc)',
            '--endpoint=URI' => 'API Endpoint (e.g. /users)',
        ];
    }

    public function handle(): int
    {
        $this->checkpoint('start');
        $this->title('API Request Generator');

        $name = $this->argument('0');
        $connector = $this->argument('1');

        if (!$name) {
            $name = $this->ask('Request Name (e.g. GetUser)');
        }

        if (!str_ends_with($name, 'Request')) {
            $name .= 'Request';
        }
        $name = Str::studly($name);

        if (!$connector) {
            $connector = $this->ask('Connector/Integration Name (e.g. Stripe)');
        }
        $connector = ucfirst($connector);

        // Path: app/Http/Integrations/{Connector}/Requests/{Name}.php
        $path = getcwd() . "/app/Http/Integrations/{$connector}/Requests/{$name}.php";

        $this->section('Configuration Summary');
        $this->keyValue('Request Name', $name);
        $this->keyValue('Connector', $connector);
        $this->keyValue('Target Path', str_replace(getcwd() . '/', '', $path));
        $this->newLine();

        if (Filesystem::exists($path) && !$this->isForce()) {
            if (!$this->confirm("Request already exists at {$path}. Overwrite?", false)) {
                $this->warning('API Request generation cancelled.');
                return 0;
            }
        }

        $this->checkpoint('generating');

        $method = strtoupper($this->option('method') ?? 'GET');
        $endpoint = $this->option('endpoint') ?? '/';

        $this->task('Generating API Request class', function () use ($connector, $name, $method, $endpoint, $path) {
            $content = $this->generateContent($connector, $name, $method, $endpoint);
            Filesystem::put($path, $content);
            usleep(200000);
        });

        $this->checkpoint('finished');

        $this->newLine(2);
        $this->box(
            "API Request '{$name}' generated successfully!\n\n" .
            "Connector: {$connector}\n" .
            "Time: {$this->formatTime($this->elapsed())}",
            "✅ Success",
            "success"
        );

        if ($this->isVerbose()) {
            $this->displayTimings();
        }

        return 0;
    }

    private function generateContent(string $connector, string $className, string $method, string $endpoint): string
    {
        // Check if Method enum exists to use constants, or just string
        // Assuming Method enum exists as per previous steps.

        return <<<PHP
<?php

namespace App\Http\Integrations\\{$connector}\Requests;

use Plugs\Http\Integration\Request;
use Plugs\Http\Integration\Enums\Method;

class {$className} extends Request
{
    /**
     * The HTTP method.
     */
    protected string $method = Method::{$method};

    /**
     * The endpoint for the request.
     */
    public function resolveEndpoint(): string
    {
        return '{$endpoint}';
    }
}
PHP;
    }
}
