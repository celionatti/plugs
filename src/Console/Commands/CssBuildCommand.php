<?php

declare(strict_types=1);

namespace Plugs\Console\Commands;

use Plugs\Console\Command;
use Plugs\Css\ClassExtractor;
use Plugs\Css\ColorPalette;
use Plugs\Css\UtilityGenerator;
use Plugs\Css\CssCompiler;

class CssBuildCommand extends Command
{
    protected string $description = 'Compile utility CSS from templates into a single stylesheet';

    protected function defineOptions(): array
    {
        return [
            'watch'     => 'Watch files for changes and rebuild automatically',
            'minify'    => 'Force minification regardless of config',
            'no-minify' => 'Force no minification',
            'verbose'   => 'Show all generated classes',
        ];
    }

    protected function defineExamples(): array
    {
        return [
            'php theplugs css:build'              => 'Build the CSS file',
            'php theplugs css:build --watch'       => 'Watch for changes and rebuild',
            'php theplugs css:build --no-minify'   => 'Build without minification',
        ];
    }

    public function handle(): int
    {
        $this->advancedHeader('⚡ Plugs CSS Engine', 'Utility-first CSS compiled from your templates');

        $config = $this->loadConfig();

        if (!$config['enabled']) {
            $this->warning('Plugs CSS engine is disabled in config/css.php');
            return self::FAILURE;
        }

        // Register custom colors
        if (!empty($config['colors'])) {
            ColorPalette::registerCustomColors($config['colors']);
        }

        // Register custom fonts
        if (!empty($config['fonts'])) {
            UtilityGenerator::registerCustomFonts($config['fonts']);
        }

        if ($this->option('watch')) {
            return $this->watchMode($config);
        }

        return $this->build($config);
    }

    private function build(array $config): int
    {
        $basePath = $this->getBasePath();

        // Step 1: Scan templates
        $this->step(1, 3, 'Scanning templates...');

        $scanPaths = array_map(
            fn($p) => $basePath . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $p),
            $config['scan_paths']
        );

        $extractor = new ClassExtractor(
            $scanPaths,
            $config['scan_extensions'],
            $config['safelist'],
            $config['blocklist']
        );

        $classes = $extractor->extract();
        $extractStats = $extractor->getStats();

        $this->info(sprintf(
            '  → Found %d utility classes across %d templates (%d duplicates removed)',
            $extractStats['classes'],
            $extractStats['files'],
            $extractStats['duplicates']
        ));

        if (empty($classes)) {
            $this->warning('No utility classes found in templates.');
            $this->note('Make sure your templates use class="..." with utility classes.');
            return self::SUCCESS;
        }

        // Step 2: Generate CSS
        $this->step(2, 3, 'Generating CSS...');

        $minify = $config['minify'];
        if ($this->hasOption('minify')) $minify = true;
        if ($this->hasOption('no-minify')) $minify = false;

        $outputPath = $basePath . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $config['output']);

        $compiler = new CssCompiler(
            $outputPath,
            $minify,
            $config['preflight'],
            $config['dark_mode'],
            $config['breakpoints'],
            $config['fluid_typography']
        );

        $css = $compiler->compileAndWrite($classes);
        $compileStats = $compiler->getStats();

        $this->info(sprintf(
            '  → %d rules generated (%d classes skipped)',
            $compileStats['generated_rules'],
            $compileStats['skipped']
        ));

        // Show responsive/state breakdown
        if (!empty($compileStats['responsive'])) {
            $rParts = [];
            foreach ($compileStats['responsive'] as $bp => $count) {
                $rParts[] = "{$bp}({$count})";
            }
            $this->info('  → Responsive: ' . implode(' ', $rParts));
        }

        if (!empty($compileStats['state_variants'])) {
            $sParts = [];
            foreach ($compileStats['state_variants'] as $state => $count) {
                $sParts[] = "{$state}({$count})";
            }
            $this->info('  → State variants: ' . implode(' ', $sParts));
        }

        // Step 3: Write output
        $this->step(3, 3, 'Writing output...');

        if ($minify && $compileStats['original_size'] > 0) {
            $savings = round((1 - $compileStats['final_size'] / $compileStats['original_size']) * 100);
            $this->info(sprintf(
                '  → Minified: %s → %s (%d%% savings)',
                $this->formatBytes($compileStats['original_size']),
                $this->formatBytes($compileStats['final_size']),
                $savings
            ));
        } else {
            $this->info(sprintf('  → Size: %s', $this->formatBytes($compileStats['final_size'])));
        }

        $this->info(sprintf('  → Written to: %s', $config['output']));

        // Verbose: show all classes
        if ($this->hasOption('verbose')) {
            $this->line();
            $this->section('Generated Classes');
            foreach ($classes as $class) {
                $this->line("  • {$class}");
            }
        }

        $this->line();
        $this->box(
            sprintf(
                "Plugs CSS compiled successfully in %sms\n\n" .
                "Add to your layout:\n  @plugcss\n\n" .
                "Or manually:\n  <link rel=\"stylesheet\" href=\"/build/plugs.css\">",
                $compileStats['build_time_ms']
            ),
            '✅ Build Complete',
            'success'
        );

        return self::SUCCESS;
    }

    private function watchMode(array $config): int
    {
        $basePath = $this->getBasePath();
        $scanPaths = array_map(
            fn($p) => $basePath . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $p),
            $config['scan_paths']
        );

        $this->info('👀 Watching for changes... (Press Ctrl+C to stop)');
        $this->line();

        $lastHashes = [];

        while (true) {
            $currentHashes = $this->getFileHashes($scanPaths, $config['scan_extensions']);

            if ($currentHashes !== $lastHashes) {
                if (!empty($lastHashes)) {
                    $changed = count(array_diff_assoc($currentHashes, $lastHashes));
                    $this->info(sprintf('[%s] %d file(s) changed — rebuilding...', date('H:i:s'), $changed));
                }

                $this->build($config);
                // Refresh hashes after build to account for any changes
                $lastHashes = $this->getFileHashes($scanPaths, $config['scan_extensions']);
                $this->line();
                $this->info('👀 Watching for changes...');
            }

            usleep(500000); // 500ms interval
        }
    }

    private function getFileHashes(array $paths, array $extensions): array
    {
        $hashes = [];

        foreach ($paths as $path) {
            if (!is_dir($path)) continue;

            $iterator = new \RecursiveIteratorIterator(
                new \RecursiveDirectoryIterator($path, \FilesystemIterator::SKIP_DOTS),
                \RecursiveIteratorIterator::LEAVES_ONLY
            );

            foreach ($iterator as $file) {
                /** @var \SplFileInfo $file */
                if (!$file->isFile()) continue;

                $matches = false;
                foreach ($extensions as $ext) {
                    if (str_ends_with($file->getFilename(), $ext)) {
                        $matches = true;
                        break;
                    }
                }

                if ($matches) {
                    $hashes[$file->getPathname()] = $file->getMTime();
                }
            }
        }

        ksort($hashes);

        return $hashes;
    }

    private function loadConfig(): array
    {
        $defaults = [
            'enabled' => true,
            'output' => 'public/build/plugs.css',
            'minify' => true,
            'preflight' => true,
            'dark_mode' => 'media',
            'scan_paths' => ['resources/views', 'modules', 'app/Components'],
            'scan_extensions' => ['.plug.php', '.php', '.html'],
            'safelist' => [],
            'blocklist' => [],
            'breakpoints' => ['sm'=>'640px','md'=>'768px','lg'=>'1024px','xl'=>'1280px','2xl'=>'1536px'],
            'colors' => [],
            'fonts' => [],
            'fluid_typography' => true,
        ];

        if (function_exists('config')) {
            return array_merge($defaults, config('css', []));
        }

        $configPath = $this->getBasePath() . DIRECTORY_SEPARATOR . 'config' . DIRECTORY_SEPARATOR . 'css.php';
        if (file_exists($configPath)) {
            return array_merge($defaults, require $configPath);
        }

        return $defaults;
    }

    private function getBasePath(): string
    {
        if (defined('BASE_PATH')) return BASE_PATH;
        if (defined('ROOT_PATH')) return ROOT_PATH;
        return getcwd();
    }

    private function formatBytes(int $bytes): string
    {
        if ($bytes >= 1048576) return number_format($bytes / 1048576, 1) . ' MB';
        if ($bytes >= 1024) return number_format($bytes / 1024, 1) . ' KB';
        return $bytes . ' B';
    }
}
