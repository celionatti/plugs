<?php

declare(strict_types=1);

namespace Plugs\Console\Commands;

/*
|--------------------------------------------------------------------------
| Share Command
|--------------------------------------------------------------------------
*/

use Plugs\Console\Command;

class ShareCommand extends Command
{
    protected string $description = 'Expose your application to the internet via localtunnel';

    protected function defineOptions(): array
    {
        return [
            '-p|--port' => 'The port your application is running on (default: 8000)',
            '--subdomain' => 'Request a specific subdomain',
        ];
    }

    public function handle(): int
    {
        $port = $this->option('port') ?: env('APP_PORT', 8000);
        $subdomain = $this->option('subdomain');

        $this->advancedHeader('Plugs Share', "Creating a secure tunnel to port {$port}");

        // Check for npx
        $npx = $this->commandExists('npx');
        if (!$npx) {
            $this->error("Node.js and npx are required for the share command.");
            $this->info("Please install Node.js from https://nodejs.org/");
            return self::FAILURE;
        }

        $command = "npx localtunnel --port {$port}";
        if ($subdomain) {
            $command .= " --subdomain {$subdomain}";
        }

        $this->info("Connecting to tunnel... (Press Ctrl+C to stop)");
        $this->newLine();

        // Use passthru to allow interactive output and Ctrl+C propagation
        passthru($command);

        return self::SUCCESS;
    }

    protected function commandExists(string $cmd): bool
    {
        $where = (strtoupper(substr(PHP_OS, 0, 3)) === 'WIN') ? 'where' : 'which';
        $path = shell_exec("{$where} {$cmd}");
        return $path !== null && trim($path) !== "";
    }
}
