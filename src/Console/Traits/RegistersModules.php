<?php

declare(strict_types=1);

namespace Plugs\Console\Traits;

use Plugs\Console\Support\Filesystem;

/**
 * Trait RegistersModules
 * 
 * Provides functionality to automatically register modules in config/modules.php.
 */
trait RegistersModules
{
    /**
     * Automatically register the module in config/modules.php.
     *
     * @param string $name
     * @return void
     */
    protected function registerModuleInConfig(string $name): void
    {
        $configPath = getcwd() . '/config/modules.php';

        if (!file_exists($configPath)) {
            if (method_exists($this, 'warning')) {
                $this->warning("  ! Could not find config/modules.php for automatic registration.");
            }
            return;
        }

        $content = file_get_contents($configPath);

        // Check if module is already in the 'enabled' list
        if (preg_match("/'enabled'\s*=>\s*\[[^\]]*'{$name}'/s", $content)) {
            if (method_exists($this, 'note')) {
                $this->note("  ℹ Module '{$name}' is already registered in config/modules.php.");
            }
            return;
        }

        // Search for 'enabled' => [ and append the name
        $pattern = "/('enabled'\s*=>\s*\[)/";
        $replacement = "$1\n        '{$name}',";

        if (preg_match($pattern, $content)) {
            $newContent = preg_replace($pattern, $replacement, $content);
            file_put_contents($configPath, $newContent);
            
            if (method_exists($this, 'success')) {
                $this->success("  ✓ Automatically registered module '{$name}' in config/modules.php");
            }
        } else {
            if (method_exists($this, 'warning')) {
                $this->warning("  ! Could not automatically register module in config/modules.php. Please add it manually.");
            }
        }
    }
}
