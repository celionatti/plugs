<?php

declare(strict_types=1);

namespace Plugs\Console\Commands;

/*
|--------------------------------------------------------------------------
| Make: Serve Command
|--------------------------------------------------------------------------
*/

use Plugs\Console\Command;

class ServeCommand extends Command
{
    protected string $description = 'Start the PHP built-in development server';

    public function handle(): int
    {
        $this->clear();

        $host = $this->option('host') ?? '127.0.0.1';
        $port = (int) ($this->option('port') ?? 8000);
        $publicPath = BASE_PATH . 'public';

        // Ensure the port is available, try next if not (Port Auto-Increment)
        while ($this->isPortInUse($host, $port)) {
            $port++;
        }

        $url = "http://{$host}:{$port}";

        $this->advancedHeader('Plugs Framework', 'Development Server');

        $this->panel(
            "Local Address:  <info>{$url}</info>\n" .
            "Document Root:  <comment>{$publicPath}</comment>\n" .
            "Router Script:  " . ($this->getRouterScript() ?: '<dim>None</dim>') . "\n\n" .
            "Press <comment>Ctrl+C</comment> to stop the server",
            "🚀 Server Information"
        );

        $command = sprintf(
            'php -S %s:%d -t %s%s',
            escapeshellarg($host),
            $port,
            escapeshellarg($publicPath),
            $this->getRouterScript() ? ' ' . escapeshellarg($this->getRouterScript()) : ''
        );

        // We use passthru to allow the PHP server to handle its own I/O and signals
        passthru($command, $status);

        $this->newLine();
        $this->info("Server stopped gracefully. Goodbye!");

        return $status;
    }

    /**
     * Check if a port is already in use.
     */
    protected function isPortInUse(string $host, int $port): bool
    {
        $connection = @fsockopen($host, $port);

        if (is_resource($connection)) {
            fclose($connection);
            return true;
        }

        return false;
    }

    /**
     * Get the router script path if it exists.
     */
    protected function getRouterScript(): ?string
    {
        if (file_exists(BASE_PATH . 'server.php')) {
            return BASE_PATH . 'server.php';
        }

        return null;
    }

    protected function defineOptions(): array
    {
        return [
            '--host=HOST' => 'The host address to bind to (default: 127.0.0.1)',
            '--port=PORT' => 'The port to bind to (default: 8000)',
        ];
    }
}
