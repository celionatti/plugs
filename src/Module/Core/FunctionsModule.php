<?php

declare(strict_types=1);

namespace Plugs\Module\Core;

use Plugs\Bootstrap\ContextType;
use Plugs\Container\Container;
use Plugs\Module\ModuleInterface;
use Plugs\Plugs;

class FunctionsModule implements ModuleInterface
{
    public function getName(): string
    {
        return 'Functions';
    }

    public function shouldBoot(ContextType $context): bool
    {
        return true;
    }

    public function register(Container $container): void
    {
        $this->loadFunctions();
    }

    public function boot(Plugs $app): void
    {
    }

    private function loadFunctions(): void
    {
        $storagePath = defined('STORAGE_PATH') ? STORAGE_PATH : (dirname(__DIR__, 5) . '/storage/');
        $cacheFile = rtrim($storagePath, '/\\') . '/framework/functions.php';

        if (Plugs::isProduction() && file_exists($cacheFile)) {
            $files = require $cacheFile;
            foreach ($files as $file) {
                require_once $file;
            }
            return;
        }

        $functionsDir = dirname(__DIR__, 3) . '/functions/';

        if (!is_dir($functionsDir)) {
            return;
        }

        $deferredFiles = Plugs::isProduction() ? ['dump.php', 'error.php'] : [];
        $files = scandir($functionsDir);
        $loadList = [];

        foreach ($files as $file) {
            if ($file[0] !== '.' && pathinfo($file, PATHINFO_EXTENSION) === 'php') {
                $filePath = $functionsDir . $file;
                if (in_array($file, $deferredFiles, true)) {
                    continue;
                }
                require_once $filePath;
                $loadList[] = $filePath;
            }
        }

        if (!empty($deferredFiles)) {
            $this->registerDeferredDebugStubs($functionsDir);
        }

        if (Plugs::isProduction() && !empty($loadList)) {
            $cacheDir = dirname($cacheFile);
            if (!is_dir($cacheDir)) {
                mkdir($cacheDir, 0755, true);
            }
            file_put_contents($cacheFile, '<?php return ' . var_export($loadList, true) . ';');
        }
    }

    private function registerDeferredDebugStubs(string $functionsDir): void
    {
        if (!function_exists('dd')) {
            function dd(mixed ...$vars): void
            {
                require_once __DIR__ . '/../../../functions/dump.php';
                plugs_dump($vars, true);
            }
        }

        if (!function_exists('d')) {
            function d(mixed ...$vars): void
            {
                require_once __DIR__ . '/../../../functions/dump.php';
                plugs_dump($vars, false);
            }
        }
    }
}
