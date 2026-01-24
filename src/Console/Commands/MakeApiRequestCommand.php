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

        if (Filesystem::exists($path) && !$this->isForce()) {
            $this->error("Request already exists: {$path}");

            return 1;
        }

        $method = strtoupper($this->option('method') ?? 'GET');
        $endpoint = $this->option('endpoint') ?? '/';

        $content = $this->generateContent($connector, $name, $method, $endpoint);

        Filesystem::put($path, $content);

        $this->success("API Request created: {$path}");

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
