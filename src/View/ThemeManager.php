<?php

declare(strict_types=1);

namespace Plugs\View;

use App\Models\Setting;

/**
 * Class ThemeManager
 *
 * Discovers, inspects, and manages view themes for the Plugs framework.
 * Themes are subdirectories of {viewPath}/themes/ and may contain
 * a theme.json file with metadata.
 *
 * @package Plugs\View
 */
class ThemeManager
{
    /**
     * Base views directory (e.g. resources/views).
     */
    protected string $viewPath;

    /**
     * The resolved themes directory.
     */
    protected string $themesPath;

    /**
     * Cached list of discovered themes (null = not scanned yet).
     *
     * @var array<string, array>|null
     */
    protected ?array $cachedThemes = null;

    /**
     * Default metadata stub for themes without a theme.json.
     */
    private const DEFAULT_META = [
        'name'        => '',
        'description' => 'No description provided.',
        'author'      => 'Unknown',
        'version'     => '1.0.0',
        'screenshot'  => null,
        'tags'        => [],
    ];

    public function __construct(string $viewPath)
    {
        $this->viewPath   = rtrim($viewPath, '/\\');
        $this->themesPath = $this->viewPath . DIRECTORY_SEPARATOR . 'themes';
    }

    // ------------------------------------------------------------------
    //  Discovery
    // ------------------------------------------------------------------

    /**
     * Return every discovered theme keyed by directory name.
     *
     * Each entry contains:
     *   name, slug, description, author, version, screenshot, path, active
     *
     * @return array<string, array>
     */
    public function getAvailableThemes(): array
    {
        if ($this->cachedThemes !== null) {
            return $this->cachedThemes;
        }

        $themes = [];
        $activeTheme = $this->getActiveTheme();

        // Always include a virtual "default" entry
        $themes['default'] = [
            'name'        => 'Default',
            'slug'        => 'default',
            'description' => 'The built-in default theme. Views are loaded from the standard views directory.',
            'author'      => 'Plugs Framework',
            'version'     => '1.0.0',
            'screenshot'  => $this->viewPath . DIRECTORY_SEPARATOR . 'screenshot.png',
            'path'        => $this->viewPath,
            'tags'        => [],
            'active'      => ($activeTheme === 'default' || $activeTheme === null),
        ];

        if (!is_dir($this->themesPath)) {
            $this->cachedThemes = $themes;
            return $themes;
        }

        $dirs = @scandir($this->themesPath);
        if ($dirs === false) {
            $this->cachedThemes = $themes;
            return $themes;
        }

        foreach ($dirs as $entry) {
            if ($entry === '.' || $entry === '..') {
                continue;
            }

            $fullPath = $this->themesPath . DIRECTORY_SEPARATOR . $entry;

            if (!is_dir($fullPath)) {
                continue;
            }

            $meta = $this->readThemeMeta($fullPath, $entry);
            $meta['slug']   = $entry;
            $meta['path']   = $fullPath;
            $meta['active'] = ($activeTheme === $entry);

            $themes[$entry] = $meta;
        }

        $this->cachedThemes = $themes;
        return $themes;
    }

    // ------------------------------------------------------------------
    //  Active Theme
    // ------------------------------------------------------------------

    /**
     * Get the slug of the currently active theme.
     *
     * Resolution order:
     *   1. Database setting `active_theme`
     *   2. Config value `app.theme`
     *   3. 'default'
     */
    public function getActiveTheme(): string
    {
        // Try DB first
        if (class_exists(Setting::class)) {
            try {
                $dbTheme = Setting::getValue('active_theme');
                if ($dbTheme !== null && $dbTheme !== '') {
                    return (string) $dbTheme;
                }
            } catch (\Throwable) {
                // DB may not be available yet — fall through
            }
        }

        // Fall back to config
        $configTheme = config('app.theme', 'default');
        return is_string($configTheme) ? $configTheme : 'default';
    }

    /**
     * Activate a theme by slug.
     *
     * @throws \InvalidArgumentException if the theme does not exist
     */
    public function activateTheme(string $theme): bool
    {
        if ($theme !== 'default' && !$this->themeExists($theme)) {
            throw new \InvalidArgumentException(
                sprintf('Theme [%s] does not exist in %s', $theme, $this->themesPath)
            );
        }

        $result = Setting::setValue('active_theme', $theme, 'appearance');

        // Bust the in-memory cache
        $this->cachedThemes = null;

        return (bool) $result;
    }

    // ------------------------------------------------------------------
    //  Info / Helpers
    // ------------------------------------------------------------------

    /**
     * Get metadata for a single theme.
     *
     * @return array|null null if the theme does not exist
     */
    public function getThemeInfo(string $theme): ?array
    {
        $themes = $this->getAvailableThemes();
        return $themes[$theme] ?? null;
    }

    /**
     * Check whether a theme slug exists on disk.
     */
    public function themeExists(string $theme): bool
    {
        if ($theme === 'default') {
            return true;
        }

        return is_dir($this->themesPath . DIRECTORY_SEPARATOR . $theme);
    }

    /**
     * Return the absolute path to the themes directory.
     */
    public function getThemesPath(): string
    {
        return $this->themesPath;
    }

    // ------------------------------------------------------------------
    //  Internal
    // ------------------------------------------------------------------

    /**
     * Read and merge theme.json metadata for the given theme directory.
     */
    protected function readThemeMeta(string $themePath, string $slug): array
    {
        $metaFile = $themePath . DIRECTORY_SEPARATOR . 'theme.json';
        $meta = self::DEFAULT_META;
        $meta['name'] = ucfirst($slug);

        if (is_file($metaFile)) {
            $contents = @file_get_contents($metaFile);
            if ($contents !== false) {
                $decoded = json_decode($contents, true);
                if (is_array($decoded)) {
                    $meta = array_merge($meta, $decoded);
                }
            }
        }

        // Resolve screenshot to an absolute path if relative
        if (!empty($meta['screenshot']) && !str_starts_with($meta['screenshot'], '/')) {
            $meta['screenshot'] = $themePath . DIRECTORY_SEPARATOR . $meta['screenshot'];
        }

        return $meta;
    }
}
