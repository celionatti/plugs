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
        $name = $this->argument('0');

        if (!$name) {
            $name = $this->ask('Connector Name (e.g. Stripe)');
        }

        if (!str_ends_with($name, 'Connector')) {
            $name .= 'Connector';
        }

        $name = Str::studly($name);
        // If name is StripeConnector, directory should be Stripe?
        // Or if I name it Stripe, it becomes StripeConnector.

        // Let's assume standard App\Http\Integrations\{Name}\{Name}Connector
        // But maybe just App\Http\Integrations\{Name}Connector if it's simple.
        // Saloon usually suggests Integrations/IntegrationName/Connector.

        // Let's go with:
        // app/Http/Integrations/Stripe/StripeConnector.php

        $integrationName = str_replace('Connector', '', $name);

        $path = getcwd() . "/app/Http/Integrations/{$integrationName}/{$name}.php";

        if (Filesystem::exists($path) && !$this->isForce()) {
            $this->error("Connector already exists: {$path}");

            return 1;
        }

        $baseUrl = $this->option('base-url') ?? '';

        $content = $this->generateContent($integrationName, $name, $baseUrl);

        Filesystem::put($path, $content);

        $this->success("Connector created: {$path}");

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
