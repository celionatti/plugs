<?php

declare(strict_types=1);

namespace Plugs\Support;

class Translator
{
    /**
     * The current locale.
     *
     * @var string
     */
    protected string $locale;

    /**
     * The default locale.
     *
     * @var string
     */
    protected string $fallbackLocale;

    /**
     * The registered translator paths.
     *
     * @var array
     */
    protected array $paths = [];

    /**
     * The loaded translation strings.
     *
     * @var array
     */
    protected array $loaded = [];

    /**
     * Create a new translator instance.
     *
     * @param string $locale
     * @param string $fallbackLocale
     */
    public function __construct(string $locale, string $fallbackLocale = 'en')
    {
        $this->locale = $locale;
        $this->fallbackLocale = $fallbackLocale;
    }

    /**
     * Set the current locale.
     *
     * @param string $locale
     * @return void
     */
    public function setLocale(string $locale): void
    {
        $this->locale = $locale;
    }

    /**
     * Get the current locale.
     *
     * @return string
     */
    public function getLocale(): string
    {
        return $this->locale;
    }

    /**
     * Add a path to the translator.
     *
     * @param string $path
     * @return void
     */
    public function addPath(string $path): void
    {
        if (!in_array($path, $this->paths)) {
            $this->paths[] = rtrim($path, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR;
        }
    }

    /**
     * Get the translation for the given key.
     *
     * @param string $key
     * @param array $replace
     * @param string|null $locale
     * @return string
     */
    public function get(string $key, array $replace = [], ?string $locale = null): string
    {
        $locale = $locale ?: $this->locale;

        // Try current locale
        $line = $this->getLine($locale, $key);

        // Fallback if not found
        if (is_null($line) && $locale !== $this->fallbackLocale) {
            $line = $this->getLine($this->fallbackLocale, $key);
        }

        // Return key if still not found
        if (is_null($line)) {
            return $key;
        }

        return $this->makeReplacements($line, $replace);
    }

    /**
     * Get the line from the loaded translations.
     *
     * @param string $locale
     * @param string $key
     * @return string|null
     */
    protected function getLine(string $locale, string $key): ?string
    {
        $parts = explode('.', $key);
        $group = array_shift($parts);

        if (!$this->isLoaded($locale, $group)) {
            $this->load($locale, $group);
        }

        return \Plugs\Utils\Arr::get($this->loaded[$locale][$group] ?? [], implode('.', $parts));
    }

    /**
     * Load the translation group for the given locale.
     *
     * @param string $locale
     * @param string $group
     * @return void
     */
    protected function load(string $locale, string $group): void
    {
        foreach ($this->paths as $path) {
            $file = "{$path}{$locale}/{$group}.php";

            if (file_exists($file)) {
                $content = include $file;
                $this->loaded[$locale][$group] = array_merge(
                    $this->loaded[$locale][$group] ?? [],
                    $content
                );
            }
        }

        // If not loaded, set empty to avoid repeated checks
        if (!isset($this->loaded[$locale][$group])) {
            $this->loaded[$locale][$group] = [];
        }
    }

    /**
     * Determine if the given group is loaded.
     *
     * @param string $locale
     * @param string $group
     * @return bool
     */
    protected function isLoaded(string $locale, string $group): bool
    {
        return isset($this->loaded[$locale][$group]);
    }

    /**
     * Make the replacements on the line.
     *
     * @param string $line
     * @param array $replace
     * @return string
     */
    protected function makeReplacements(string $line, array $replace): string
    {
        if (empty($replace)) {
            return $line;
        }

        $replace = \Plugs\Utils\Arr::sortRecursive($replace);

        foreach ($replace as $key => $value) {
            $line = str_replace(
                [':' . strtolower((string) $key), ':' . ucwords((string) $key), ':' . strtoupper((string) $key)],
                [$value, ucwords((string) $value), strtoupper((string) $value)],
                $line
            );
        }

        return $line;
    }
}
