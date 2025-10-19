<?php

declare(strict_types=1);

namespace Plugs\Console\Commands;

/*
|--------------------------------------------------------------------------
| Make: Middleware Command
|--------------------------------------------------------------------------
*/

use Plugs\Console\Command;
use Plugs\Console\Support\Filesystem;

class MakeMiddlewareCommand extends Command
{
    protected string $description = 'Create a new middleware class';

    public function handle(): int
    {
        $name = $this->argument('0') ?? $this->ask('Middleware name', 'AuthMiddleware');
        
        if (!str_ends_with($name, 'Middleware')) {
            $name .= 'Middleware';
        }
        
        $content = $this->generateMiddleware($name);
        $path = $this->getMiddlewarePath($name);
        
        if (Filesystem::exists($path) && !$this->confirm('File exists. Overwrite?', false)) {
            $this->warning('Operation cancelled');
            return 0;
        }
        
        Filesystem::put($path, $content);
        
        $this->success("Middleware created: {$path}");
        
        return 0;
    }

    private function generateMiddleware(string $name): string
    {
        return <<<PHP
        <?php

        declare(strict_types=1);

        namespace App\\Middleware;

        use Psr\\Http\\Message\\ResponseInterface;
        use Psr\\Http\\Message\\ServerRequestInterface;
        use Psr\\Http\\Server\\MiddlewareInterface;
        use Psr\\Http\\Server\\RequestHandlerInterface;

        class {$name} implements MiddlewareInterface
        {
            public function process(ServerRequestInterface \$request, RequestHandlerInterface \$handler): ResponseInterface
            {
                // Before middleware logic
                
                \$response = \$handler->handle(\$request);
                
                // After middleware logic
                
                return \$response;
            }
        }

        PHP;
    }

    private function getMiddlewarePath(string $name): string
    {
        return getcwd() . '/app/Middleware/' . $name . '.php';
    }
}
