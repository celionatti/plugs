<?php

declare(strict_types=1);

namespace Plugs\Console\Commands;

use Plugs\Console\Command;
use Plugs\Router\Router;
use Plugs\Container\Container;
use Plugs\Config\DefaultConfig;

class TypeGenCommand extends Command
{
    protected string $signature = 'type:gen';
    protected string $description = 'Generate IDE helper files for strict type checking';

    public function handle(): int
    {
        $this->info('Generating IDE helpers...');

        $this->generateConfigHelper();
        $this->generateRouteHelper();

        $this->success('IDE helpers generated successfully!');
        return 0;
    }

    private function generateConfigHelper(): void
    {
        // Simply generic helper for now
        $content = <<<'PHP'
<?php

namespace Plugs\Helpers;

/**
 * @method static mixed get(string $key, mixed $default = null)
 * @method static bool has(string $key)
 */
class Config {}
PHP;
        file_put_contents(base_path('ide_config_helper.php'), $content);
        $this->info('- Config helper generated.');
    }

    private function generateRouteHelper(): void
    {
        // This would ideally scan registered routes
        $content = <<<'PHP'
<?php

namespace Plugs\Helpers;

class Route {}
PHP;
        file_put_contents(base_path('ide_route_helper.php'), $content);
        $this->info('- Route helper generated.');
    }
}
