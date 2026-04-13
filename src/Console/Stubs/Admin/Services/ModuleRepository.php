<?php

declare(strict_types=1);

namespace Modules\Admin\Services;

/**
 * Handles filesystem read/write operations for feature modules.
 *
 * Responsible for: discovering modules, reading/writing module.json
 * and settings.json, toggling status, and deleting module directories.
 */
class ModuleRepository
{
    protected string $modulesPath;

    public function __construct(?string $modulesPath = null)
    {
        $this->modulesPath = $modulesPath
            ?? (defined('BASE_PATH') ? BASE_PATH : rtrim((string) getcwd(), '/\\') . DIRECTORY_SEPARATOR) . 'modules';
    }

    /**
     * Get the base modules directory path.
     */
    public function getModulesPath(): string
    {
        return $this->modulesPath;
    }

    /**
     * Discover all modules from the filesystem.
     *
     * @return ModuleData[]
     */
    public function getAll(): array
    {
        if (!is_dir($this->modulesPath)) {
            return [];
        }

        $modules = [];
        $dirs = array_filter(glob($this->modulesPath . '/*') ?: [], 'is_dir');

        foreach ($dirs as $dir) {
            $module = $this->readModuleData($dir);
            if ($module !== null) {
                $modules[] = $module;
            }
        }

        return $modules;
    }

    /**
     * Find a single module by name.
     */
    public function find(string $name): ?ModuleData
    {
        $dir = $this->modulesPath . DIRECTORY_SEPARATOR . $name;

        if (!is_dir($dir)) {
            return null;
        }

        return $this->readModuleData($dir);
    }

    /**
     * Check if a module exists on disk.
     */
    public function exists(string $name): bool
    {
        return is_dir($this->modulesPath . DIRECTORY_SEPARATOR . $name);
    }

    /**
     * Toggle a module's enabled/disabled status in module.json.
     */
    public function toggleStatus(string $name): bool
    {
        $jsonPath = $this->moduleJsonPath($name);

        if (!file_exists($jsonPath)) {
            return false;
        }

        $data = $this->readJson($jsonPath);

        if ($data === null) {
            return false;
        }

        $data['enabled'] = !($data['enabled'] ?? true);

        return $this->writeJson($jsonPath, $data);
    }

    /**
     * Read a module's settings.json.
     *
     * @return array<string, mixed>
     */
    public function getSettings(string $name): array
    {
        $path = $this->settingsJsonPath($name);

        if (!file_exists($path)) {
            return [];
        }

        return $this->readJson($path) ?? [];
    }

    /**
     * Write updated settings to a module's settings.json.
     *
     * @param array<string, mixed> $settings
     */
    public function updateSettings(string $name, array $settings): bool
    {
        if (!$this->exists($name)) {
            return false;
        }

        return $this->writeJson($this->settingsJsonPath($name), $settings);
    }

    /**
     * Recursively delete a module directory.
     */
    public function delete(string $name): bool
    {
        $modulePath = $this->modulesPath . DIRECTORY_SEPARATOR . $name;

        if (!is_dir($modulePath)) {
            return false;
        }

        return $this->deleteDirectory($modulePath);
    }

    // ──────────────────────────────────────────────
    //  Internal helpers
    // ──────────────────────────────────────────────

    /**
     * Read module metadata from a directory's module.json.
     */
    protected function readModuleData(string $dir): ?ModuleData
    {
        $name = basename($dir);
        $jsonPath = $dir . DIRECTORY_SEPARATOR . 'module.json';

        $meta = [
            'name' => $name,
            'path' => $dir,
            'enabled' => true,
        ];

        if (file_exists($jsonPath)) {
            $decoded = $this->readJson($jsonPath);
            if (is_array($decoded)) {
                $meta = array_merge($meta, $decoded);
                $meta['path'] = $dir;
            }
        }

        return ModuleData::fromArray($meta);
    }

    protected function moduleJsonPath(string $name): string
    {
        return $this->modulesPath . DIRECTORY_SEPARATOR . $name . DIRECTORY_SEPARATOR . 'module.json';
    }

    protected function settingsJsonPath(string $name): string
    {
        return $this->modulesPath . DIRECTORY_SEPARATOR . $name . DIRECTORY_SEPARATOR . 'settings.json';
    }

    /**
     * @return array<string, mixed>|null
     */
    protected function readJson(string $path): ?array
    {
        $content = file_get_contents($path);

        if ($content === false) {
            return null;
        }

        $decoded = json_decode($content, true);

        return is_array($decoded) ? $decoded : null;
    }

    /**
     * @param array<string, mixed> $data
     */
    protected function writeJson(string $path, array $data): bool
    {
        return (bool) file_put_contents(
            $path,
            json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . "\n"
        );
    }

    protected function deleteDirectory(string $dir): bool
    {
        if (!is_dir($dir)) {
            return false;
        }

        $items = scandir($dir);

        if ($items === false) {
            return false;
        }

        foreach ($items as $item) {
            if ($item === '.' || $item === '..') {
                continue;
            }

            $path = $dir . DIRECTORY_SEPARATOR . $item;

            is_dir($path) ? $this->deleteDirectory($path) : unlink($path);
        }

        return rmdir($dir);
    }
}
