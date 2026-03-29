<?php

declare(strict_types=1);

namespace Plugs\Css;

/**
 * Scans template files and extracts utility class names.
 *
 * Recursively reads .plug.php, .php, and .html templates,
 * parses class="..." attributes, @class(...) directives,
 * and :class="..." bindings, then returns a deduplicated
 * list of all utility class names found.
 */
class ClassExtractor
{
    /**
     * Directories to scan (absolute paths).
     * @var string[]
     */
    private array $scanPaths;

    /**
     * File extensions to scan.
     * @var string[]
     */
    private array $extensions;

    /**
     * Classes that are always included regardless of scanning.
     * @var string[]
     */
    private array $safelist;

    /**
     * Classes that are always excluded regardless of scanning.
     * @var string[]
     */
    private array $blocklist;

    /**
     * Stats from the last extraction.
     * @var array{files: int, classes: int, duplicates: int}
     */
    private array $stats = ['files' => 0, 'classes' => 0, 'duplicates' => 0];

    public function __construct(
        array $scanPaths = [],
        array $extensions = ['.plug.php', '.php', '.html'],
        array $safelist = [],
        array $blocklist = []
    ) {
        $this->scanPaths = $scanPaths;
        $this->extensions = $extensions;
        $this->safelist = $safelist;
        $this->blocklist = $blocklist;
    }

    /**
     * Extract all unique utility class names from configured scan paths.
     *
     * @return string[] Deduplicated class names
     */
    public function extract(): array
    {
        $allClasses = [];
        $this->stats = ['files' => 0, 'classes' => 0, 'duplicates' => 0];

        foreach ($this->scanPaths as $path) {
            if (!is_dir($path)) {
                continue;
            }
            $this->scanDirectory($path, $allClasses);
        }

        // Add safelist
        foreach ($this->safelist as $class) {
            $allClasses[$class] = true;
        }

        // Remove blocklist
        foreach ($this->blocklist as $class) {
            unset($allClasses[$class]);
        }

        $unique = array_keys($allClasses);
        sort($unique);

        $this->stats['classes'] = count($unique);

        return $unique;
    }

    /**
     * Recursively scan a directory for template files.
     */
    private function scanDirectory(string $directory, array &$allClasses): void
    {
        $iterator = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($directory, \FilesystemIterator::SKIP_DOTS),
            \RecursiveIteratorIterator::LEAVES_ONLY
        );

        foreach ($iterator as $file) {
            /** @var \SplFileInfo $file */
            if (!$file->isFile()) {
                continue;
            }

            $filename = $file->getFilename();
            $matches = false;
            foreach ($this->extensions as $ext) {
                if (str_ends_with($filename, $ext)) {
                    $matches = true;
                    break;
                }
            }

            if (!$matches) {
                continue;
            }

            $this->stats['files']++;
            $content = file_get_contents($file->getPathname());
            if ($content === false) {
                continue;
            }

            $this->extractFromContent($content, $allClasses);
        }
    }

    /**
     * Extract class names from a single template's content.
     */
    public function extractFromContent(string $content, array &$allClasses): void
    {
        // 1. Standard class="..." attributes (double and single quotes)
        preg_match_all('/\bclass\s*=\s*"([^"]*)"/', $content, $matches);
        foreach ($matches[1] as $classString) {
            $this->parseClassString($classString, $allClasses);
        }

        preg_match_all("/\bclass\s*=\s*'([^']*)'/", $content, $matches);
        foreach ($matches[1] as $classString) {
            $this->parseClassString($classString, $allClasses);
        }

        // 2. @class([...]) directive — extract string literals from array
        preg_match_all('/@class\s*\(\s*\[(.*?)\]\s*\)/s', $content, $matches);
        foreach ($matches[1] as $arrayContent) {
            // Extract quoted strings (both keys and values)
            preg_match_all('/[\'"]([a-zA-Z0-9\-:\/\[\]\. ]+)[\'"]/', $arrayContent, $stringMatches);
            foreach ($stringMatches[1] as $classString) {
                $this->parseClassString($classString, $allClasses);
            }
        }

        // 3. :class="..." dynamic binding — extract string literals
        preg_match_all('/:class\s*=\s*"([^"]*)"/', $content, $matches);
        foreach ($matches[1] as $expression) {
            preg_match_all('/[\'"]([a-zA-Z0-9\-:\/\[\]\. ]+)[\'"]/', $expression, $stringMatches);
            foreach ($stringMatches[1] as $classString) {
                $this->parseClassString($classString, $allClasses);
            }
        }

        // 4. Inline style — look for class= in PHP echo blocks
        preg_match_all('/class="[^"]*\<\?php.*?\?\>[^"]*"/', $content, $matches);
        foreach ($matches[0] as $fullMatch) {
            // Extract the static parts around PHP blocks
            $static = preg_replace('/\<\?php.*?\?\>/', ' ', $fullMatch);
            $static = preg_replace('/^class="/', '', $static);
            $static = preg_replace('/"$/', '', $static);
            $this->parseClassString($static, $allClasses);
        }
    }

    /**
     * Parse a class string (space-separated) into individual class names.
     */
    private function parseClassString(string $classString, array &$allClasses): void
    {
        // Clean up PHP expressions, template variables, etc.
        $classString = preg_replace('/\{\{.*?\}\}/', '', $classString);
        $classString = preg_replace('/\{.*?\}/', '', $classString);
        $classString = preg_replace('/\$[a-zA-Z_][a-zA-Z0-9_]*/', '', $classString);

        $classes = preg_split('/\s+/', trim($classString), -1, PREG_SPLIT_NO_EMPTY);

        foreach ($classes as $class) {
            $class = trim($class);

            // Skip empty, purely numeric, or obviously invalid classes
            if (empty($class) || is_numeric($class) || strlen($class) < 2) {
                continue;
            }

            // Must look like a utility class: letters, numbers, hyphens, colons, slashes, dots, brackets, and @ for containers
            if (!preg_match('/^[a-zA-Z\-!@][a-zA-Z0-9\-:\/\[\]\._%#,()@]*$/', $class)) {
                continue;
            }

            if (isset($allClasses[$class])) {
                $this->stats['duplicates']++;
            }
            $allClasses[$class] = true;
        }
    }

    /**
     * Get statistics from the last extraction.
     *
     * @return array{files: int, classes: int, duplicates: int}
     */
    public function getStats(): array
    {
        return $this->stats;
    }
}
