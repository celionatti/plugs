<?php

declare(strict_types=1);

namespace Modules\Admin\Services;

/**
 * Scaffolds new feature module directories and boilerplate files.
 */
class ModuleScaffolder
{
    protected string $modulesPath;

    public function __construct(?string $modulesPath = null)
    {
        $this->modulesPath = $modulesPath
            ?? (defined('BASE_PATH') ? BASE_PATH : rtrim((string) getcwd(), '/\\') . DIRECTORY_SEPARATOR) . 'modules';
    }

    /**
     * Create a full module directory structure with boilerplate files.
     *
     * @throws \RuntimeException If the module already exists or creation fails.
     */
    public function scaffold(string $name): void
    {
        $name = ucfirst(trim($name));

        if ($name === '') {
            throw new \RuntimeException('Module name cannot be empty.');
        }

        $modulePath = $this->modulesPath . DIRECTORY_SEPARATOR . $name;

        if (is_dir($modulePath)) {
            throw new \RuntimeException("Module '{$name}' already exists.");
        }

        $this->createDirectories($modulePath);
        $this->generateModuleClass($modulePath, $name);
        $this->generateModuleJson($modulePath, $name);
        $this->generateSettingsJson($modulePath);
        $this->generateRouteFiles($modulePath, $name);
    }

    // ──────────────────────────────────────────────
    //  Directory structure
    // ──────────────────────────────────────────────

    protected function createDirectories(string $modulePath): void
    {
        $dirs = [
            $modulePath,
            $modulePath . '/Controllers',
            $modulePath . '/Models',
            $modulePath . '/Views',
            $modulePath . '/Routes',
            $modulePath . '/Migrations',
        ];

        foreach ($dirs as $dir) {
            if (!mkdir($dir, 0755, true) && !is_dir($dir)) {
                throw new \RuntimeException("Failed to create directory: {$dir}");
            }
        }
    }

    // ──────────────────────────────────────────────
    //  File generators
    // ──────────────────────────────────────────────

    protected function generateModuleClass(string $modulePath, string $name): void
    {
        $prefix = strtolower($name);

        $content = <<<PHP
<?php

declare(strict_types=1);

namespace Modules\\{$name};

use Plugs\\FeatureModule\\AbstractFeatureModule;
use Plugs\\Container\\Container;
use Plugs\\Plugs;

class {$name}Module extends AbstractFeatureModule
{
    public function getName(): string
    {
        return '{$name}';
    }

    public function getRoutePrefix(): string
    {
        return '{$prefix}';
    }

    public function getMiddleware(): array
    {
        return [];
    }

    public function register(Container \$container): void
    {
        //
    }

    public function boot(Plugs \$app): void
    {
        //
    }
}
PHP;

        file_put_contents($modulePath . "/{$name}Module.php", $content);
    }

    protected function generateModuleJson(string $modulePath, string $name): void
    {
        $data = [
            'name' => $name,
            'description' => "The {$name} feature module.",
            'version' => '1.0.0',
            'author' => '',
            'enabled' => true,
        ];

        file_put_contents(
            $modulePath . '/module.json',
            json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . "\n"
        );
    }

    protected function generateSettingsJson(string $modulePath): void
    {
        file_put_contents(
            $modulePath . '/settings.json',
            json_encode(new \stdClass(), JSON_PRETTY_PRINT) . "\n"
        );
    }

    protected function generateRouteFiles(string $modulePath, string $name): void
    {
        $web = <<<PHP
<?php

declare(strict_types=1);

use Plugs\\Facades\\Route;

// Define your {$name} module web routes here
PHP;

        $api = <<<PHP
<?php

declare(strict_types=1);

use Plugs\\Facades\\Route;

// Define your {$name} module API routes here
PHP;

        file_put_contents($modulePath . '/Routes/web.php', $web . "\n");
        file_put_contents($modulePath . '/Routes/api.php', $api . "\n");
    }
}
