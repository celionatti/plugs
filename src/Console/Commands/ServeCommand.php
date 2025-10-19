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
        $host = $this->option('host') ?? '127.0.0.1';
        $port = $this->option('port') ?? 8000;
        
        $this->banner('Development Server');
        
        $this->panel(
            "Server URL: http://{$host}:{$port}\n" .
            "Document Root: " . getcwd() . "/public\n" .
            "Press Ctrl+C to stop",
            "🚀 Server Information"
        );
        
        $command = "php -S {$host}:{$port} -t public";
        
        return $this->execRealtime($command);
    }

    protected function defineOptions(): array
    {
        return [
            '--host=HOST' => 'The host address to bind to (default: 127.0.0.1)',
            '--port=PORT' => 'The port to bind to (default: 8000)',
        ];
    }
}
