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
        $this->checkpoint('start');
        $this->title('Middleware Generator');

        $name = $this->argument('0') ?? $this->ask('Middleware name', 'AuthMiddleware');

        if (!str_ends_with($name, 'Middleware')) {
            $name .= 'Middleware';
        }

        $path = $this->getMiddlewarePath($name);

        $this->section('Configuration Summary');
        $this->keyValue('Middleware Name', $name);
        $this->keyValue('Target Path', str_replace(getcwd() . '/', '', $path));
        $this->newLine();

        if (Filesystem::exists($path) && !$this->confirm('File already exists. Overwrite?', false)) {
            $this->warning('Operation cancelled');
            return 0;
        }

        $this->checkpoint('generating');

        $content = $this->generateMiddleware($name);
        Filesystem::put($path, $content);

        $this->checkpoint('finished');

        $this->newLine();
        $this->box(
            "Middleware '{$name}' generated successfully!\n\n" .
            "Time: {$this->formatTime($this->elapsed())}",
            "✅ Success",
            "success"
        );

        $this->section('Next Steps');
        $this->bulletList([
            "Register the middleware in App\Http\Kernel.php",
            "Implement your logic in the process() method",
            "Apply it to your routes",
        ]);
        $this->newLine();

        if ($this->isVerbose()) {
            $this->displayTimings();
        }

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
