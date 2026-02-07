<?php

declare(strict_types=1);

namespace Plugs\View;

/*
|--------------------------------------------------------------------------
| ViewEngine Class
|--------------------------------------------------------------------------
|
| This class is responsible for rendering views using a specified templating engine.
| It manages view paths, caching, and shared data across views.
*/

use RuntimeException;
use Plugs\Exceptions\ViewException;
use Throwable;

class ViewEngine
{
    private string $viewPath;
    private string $cachePath;
    private bool $cacheEnabled;
    private array $sharedData = [];
    private string $componentPath;
    private ?ViewCompiler $viewCompiler = null;
    private array $customDirectives = [];
    private bool $suppressLayout = false;
    private ?string $requestedSection = null;
    private bool $fastCache = false;

    private const VIEW_EXTENSIONS = ['.plug.php', '.php', '.html'];
    private const PRODUCTION_ERROR_LEVEL = E_ERROR | E_PARSE | E_CORE_ERROR | E_COMPILE_ERROR;

    private ?string $cspNonce = null;
    private array $compilationAttempts = [];

    private array $composers = [];

    /**
     * Path resolution cache for performance
     */
    private array $pathCache = [];

    /**
     * Component aliases (alias => actual component name)
     */
    private array $componentAliases = [];

    /**
     * Fragment renderer for HTMX/Turbo support
     */
    private ?FragmentRenderer $fragmentRenderer = null;

    /**
     * View cache for block caching
     */
    private ?ViewCache $viewCache = null;

    /**
     * Preloaded views cache
     */
    private array $preloadedViews = [];

    /**
     * Enable streaming mode
     */
    private bool $streamingEnabled = false;

    /**
     * File existence cache to reduce I/O
     */
    private array $fileExistsCache = [];

    /**
     * Enable OPcache compilation hints
     */
    private bool $opcacheEnabled = true;

    /**
     * Lazy-loaded components registry
     */
    private array $lazyComponents = [];

    /**
     * Component definition cache
     */
    private array $componentDefinitionCache = [];

    public function __construct(string $viewPath, string $cachePath, bool $cacheEnabled = false)
    {
        $this->viewPath = rtrim($viewPath, '/\\');
        $this->cachePath = rtrim($cachePath, '/\\');
        $this->cacheEnabled = $cacheEnabled;
        $this->componentPath = $this->viewPath . DIRECTORY_SEPARATOR . 'components';

        $this->ensureDirectoryExists($this->cachePath);
        $this->ensureDirectoryExists($this->componentPath);

        // Initialize fragment renderer and view cache
        $this->fragmentRenderer = new FragmentRenderer();
        $this->viewCache = new ViewCache($this->cachePath);
    }

    public function setCspNonce(string $nonce): void
    {
        $this->cspNonce = $nonce;
    }

    public function getCspNonce(): ?string
    {
        return $this->cspNonce;
    }

    /**
     * Set whether to suppress the parent layout
     * Used for SPA partial rendering
     */
    public function suppressLayout(bool $suppress = true): void
    {
        $this->suppressLayout = $suppress;
    }

    /**
     * Set a specific section to render for SPA partials
     */
    public function requestSection(?string $section): void
    {
        $this->requestedSection = $section;
    }

    /**
     * Check if layout is suppressed
     */
    public function isLayoutSuppressed(): bool
    {
        return $this->suppressLayout;
    }

    /**
     * Enable/disable fast cache mode (skip modification time checks)
     * Recommended for production
     */
    public function setFastCache(bool $enabled): self
    {
        $this->fastCache = $enabled;
        return $this;
    }

    /**
     * Check if fast cache is enabled
     */
    public function isFastCacheEnabled(): bool
    {
        return $this->fastCache;
    }

    private function checkCompilationRateLimit(): void
    {
        // Rate limiting removed to prevent production issues during high load/deployments
    }

    /**
     * Garbage collect old cache files
     * @param int $hours Cache age in hours (default 24*30 = 1 month)
     */
    public function gc(int $hours = 720): int
    {
        if (!$this->cacheEnabled || !is_dir($this->cachePath)) {
            return 0;
        }

        $count = 0;
        $now = time();
        $files = glob($this->cachePath . DIRECTORY_SEPARATOR . '*.php');

        foreach ($files as $file) {
            if (is_file($file)) {
                $mtime = filemtime($file);
                if ($now - $mtime > ($hours * 3600)) {
                    @unlink($file);
                    $count++;
                }
            }
        }

        return $count;
    }

    public function composer(string|array $views, callable $callback): void
    {
        $views = (array) $views;
        foreach ($views as $view) {
            if (!isset($this->composers[$view])) {
                $this->composers[$view] = [];
            }
            $this->composers[$view][] = $callback;
        }
    }

    private function applyComposers(string $view, array $data): array
    {
        if (isset($this->composers[$view])) {
            foreach ($this->composers[$view] as $composer) {
                $result = $composer($data);
                if (is_array($result)) {
                    $data = array_merge($data, $result);
                }
            }
        }

        // Also check for wildcard composers
        if (isset($this->composers['*'])) {
            foreach ($this->composers['*'] as $composer) {
                $result = $composer($data);
                if (is_array($result)) {
                    $data = array_merge($data, $result);
                }
            }
        }

        return $data;
    }

    public function directive(string $name, callable $handler): void
    {
        $this->customDirectives[$name] = $handler;
        $this->getCompiler()->registerCustomDirective($name, $handler);
    }

    public function hasDirective(string $name): bool
    {
        return isset($this->customDirectives[$name]);
    }

    public function getDirectives(): array
    {
        return array_keys($this->customDirectives);
    }

    private function registerDefaultDirectives(): void
    {
        $this->directive('dd', function ($expression) {
            return "<?php var_dump($expression); exit; ?>";
        });

        $this->directive('dump', function ($expression) {
            return "<?php var_dump($expression); ?>";
        });

        $this->directive('env', function ($expression) {
            return "<?php if (config('app.env') === $expression): ?>";
        });

        $this->directive('endenv', function () {
            return "<?php endif; ?>";
        });

        $this->directive('production', function () {
            return "<?php if (config('app.env') === 'production'): ?>";
        });

        $this->directive('endproduction', function () {
            return "<?php endif; ?>";
        });

        $this->directive('auth', function ($expression) {
            $guards = $expression ? "{$expression}" : '';
            return "<?php if (function_exists('auth') && auth()->check($guards)): ?>";
        });

        $this->directive('endauth', function () {
            return "<?php endif; ?>";
        });

        $this->directive('guest', function ($expression) {
            $guards = $expression ? "{$expression}" : '';
            return "<?php if (function_exists('auth') && !auth()->check($guards)): ?>";
        });

        $this->directive('endguest', function () {
            return "<?php endif; ?>";
        });

        $this->directive('skeletonStyles', function () {
            return "<?php echo skeleton_styles(); ?>";
        });

        $this->directive('skeleton', function ($expression) {
            if ($expression === null || trim($expression) === '') {
                return '<?php echo skeleton(); ?>';
            }

            return "<?php echo skeleton()->$expression; ?>";
        });

        $this->directive('session', function ($expression) {
            return "<?php if (\\Plugs\\Utils\\FlashMessage::peek($expression)): ?>";
        });

        $this->directive('endsession', function () {
            return "<?php endif; ?>";
        });

        $this->directive('old', function ($expression) {
            return "<?php echo old($expression); ?>";
        });

        $this->directive('flash', function ($expression) {
            return "<?php echo \\Plugs\\Utils\\FlashMessage::renderType($expression); ?>";
        });

        $this->directive('error', function ($expression) {
            $key = trim($expression, " '\"");
            return "<?php if (isset(\$errors) && \$errors->has('$key')): ?>";
        });

        $this->directive('enderror', function () {
            return "<?php endif; ?>";
        });
    }

    public function render(string $view, array $data = [], bool $isComponent = false): string
    {
        $startTime = microtime(true);

        // OPTIMIZATION: Resolve any Async/Promise data in parallel before rendering
        // This allows controllers to pass promises directly to views
        $promises = [];
        foreach ($data as $key => $value) {
            if ($value instanceof \GuzzleHttp\Promise\PromiseInterface || $value instanceof \Fiber) {
                $promises[$key] = $value;
            }
        }

        if (!empty($promises)) {
            $resolved = \Plugs\Concurrency\Async::parallel($promises);
            $data = array_merge($data, $resolved);
        }

        $data = array_merge($this->sharedData, $data);
        $data['view'] = $this;
        $data = $this->applyComposers($view, $data);

        $viewFile = $isComponent
            ? $this->getComponentPath($view)
            : $this->getViewPath($view);

        if (!file_exists($viewFile)) {
            throw new ViewException(
                sprintf('View [%s] not found at %s', $view, $viewFile),
                0,
                null,
                $view
            );
        }

        $content = $this->cacheEnabled
            ? $this->renderCached($view, $viewFile, $data, $isComponent)
            : $this->renderDirect($viewFile, $data);

        // Record in Profiler
        if (class_exists(\Plugs\Debug\Profiler::class)) {
            $duration = (microtime(true) - $startTime) * 1000;
            \Plugs\Debug\Profiler::getInstance()->addView($view, $duration);
        }

        return $content;
    }

    public function renderComponent(string $componentName, array $data = []): string
    {
        try {
            $componentFile = $this->getComponentPath($componentName);

            if (!file_exists($componentFile)) {
                throw new ViewException(
                    sprintf('Component [%s] not found at %s', $componentName, $componentFile),
                    0,
                    null,
                    $componentName
                );
            }

            $slot = $data['slot'] ?? '';
            unset($data['slot']);

            $slotId = $data['__slot_id'] ?? null;
            unset($data['__slot_id']);

            if ($slotId) {
                $compiledSlot = $this->getCompiler()->getCompiledSlot($slotId);
                if (!empty($compiledSlot)) {
                    $slot = $this->executeCompiledContent($compiledSlot, $data);
                }
            }

            $componentData = array_merge($data, ['slot' => $slot]);

            // Check if there's a reactive class for this component
            $className = "App\\Components\\" . $this->anyToPascalCase(str_replace('.', '\\', $componentName));

            if (class_exists($className)) {
                $component = new $className($componentName, $componentData);
                if ($component instanceof ReactiveComponent) {
                    $html = $this->render($componentName, array_merge($componentData, $component->getState()), true);

                    // Build attribute string
                    $attributes = $component->getAttributes();
                    $attrString = '';
                    foreach ($attributes as $key => $value) {
                        $attrString .= sprintf(' %s="%s"', $key, htmlspecialchars((string) $value));
                    }

                    // Wrap in reactive container
                    return sprintf(
                        '<div data-plug-component="%s" data-plug-state="%s" id="%s"%s>%s</div>',
                        $componentName,
                        $component->serializeState(),
                        $component->getId(),
                        $attrString,
                        $html
                    );
                }
            }

            return $this->render($componentName, $componentData, true);
        } catch (Throwable $e) {
            // FIX: Better error context for component failures
            throw new ViewException(
                sprintf(
                    'Error rendering component [%s]: %s',
                    $componentName,
                    $e->getMessage()
                ),
                (int) $e->getCode(),
                $e,
                $componentName
            );
        }
    }

    public function share(string $key, $value): void
    {
        $this->sharedData[$key] = $value;
    }

    public function componentExists(string $componentName): bool
    {
        return file_exists($this->getComponentPath($componentName));
    }

    public function getAvailableComponents(): array
    {
        $components = [];

        foreach (self::VIEW_EXTENSIONS as $extension) {
            $pattern = $this->componentPath . DIRECTORY_SEPARATOR . '*' . $extension;
            $files = glob($pattern);

            foreach ($files as $file) {
                $filename = pathinfo($file, PATHINFO_FILENAME);
                $componentName = $this->anyToPascalCase($filename);

                if (!isset($components[$componentName])) {
                    $components[$componentName] = $file;
                }
            }
        }

        return $components;
    }

    public function clearCache(): void
    {
        $this->getCompiler()->clearCache();

        if ($this->cacheEnabled && is_dir($this->cachePath)) {
            $pattern = $this->cachePath . DIRECTORY_SEPARATOR . '*.php';
            $files = glob($pattern);

            foreach ($files as $file) {
                if (is_file($file)) {
                    @unlink($file);
                }
            }
        }
    }

    private function executeCompiledContent(string $compiledContent, array $data): string
    {
        $compiledContent = $this->stripStrictTypesDeclaration($compiledContent);

        extract(array_merge($this->sharedData, $data), EXTR_SKIP);

        // FIX: Don't overwrite if already present from extract
        $view = $view ?? $this;
        $__sections = $__sections ?? [];
        $__stacks = $__stacks ?? [];

        $previousErrorLevel = $this->suppressNonCriticalErrors();

        ob_start();

        try {
            eval ('?>' . $compiledContent);
            $result = ob_get_clean();
            error_reporting($previousErrorLevel);

            return $result;
        } catch (Throwable $e) {
            ob_end_clean();
            error_reporting($previousErrorLevel);

            throw new ViewException(
                sprintf('Error executing compiled content: %s', $e->getMessage()),
                (int) $e->getCode(),
                $e
            );
        }
    }

    private function renderCached(string $view, string $viewFile, array $data, bool $isComponent): string
    {
        $cacheKey = $view . ($isComponent ? '_component' : '');
        $compiled = $this->getCompiledPath($cacheKey);

        // Optimization: In production, we can skip the filemtime check if fastCache is enabled
        $needsRecompile = !file_exists($compiled);

        if (!$needsRecompile && !($this->fastCache && \Plugs\Plugs::isProduction())) {
            if (filemtime($viewFile) > filemtime($compiled)) {
                $needsRecompile = true;
            }
        }

        if ($needsRecompile) {
            $this->compile($viewFile, $compiled);
        }

        return $this->renderCompiled($compiled, $data);
    }

    private function renderCompiled(string $compiled, array $data): string
    {
        extract($data, EXTR_SKIP);

        // FIX: Don't reinitialize if already set by extract
        // This preserves stacks from child views
        $__sections = $__sections ?? [];
        $__stacks = $__stacks ?? [];
        $__extends = null;
        $__currentSection = null;

        $previousErrorLevel = $this->suppressNonCriticalErrors();

        ob_start();

        try {
            include $compiled;
            $childContent = ob_get_clean() ?: '';
            error_reporting($previousErrorLevel);

            /** @phpstan-ignore-next-line */
            if (isset($__extends) && $__extends && !$this->suppressLayout) {
                // FIX: Pass stacks and childContent to parent layout
                return (string) $this->renderParent(
                    $__extends,
                    array_merge($data, ['childContent' => $childContent]),
                    $__sections,
                    $__stacks
                );
            }

            if ($this->suppressLayout) {
                $output = $childContent;

                if ($this->requestedSection && isset($__sections[$this->requestedSection])) {
                    $output = $__sections[$this->requestedSection];
                } elseif (isset($__sections['content'])) {
                    $output = $__sections['content'];
                }

                if (isset($__sections['title'])) {
                    $output = "<title>{$__sections['title']}</title>\n" . $output;
                }

                if (!empty($__stacks['styles'])) {
                    $output .= implode("\n", $__stacks['styles']);
                }
                if (!empty($__stacks['scripts'])) {
                    $output .= implode("\n", $__stacks['scripts']);
                }

                // Include layout information for SPA to detect if a full reload is needed
                /** @phpstan-ignore-next-line */
                if (isset($__extends) && $__extends) {
                    $output = "<meta name=\"plugs-layout\" content=\"" . (string) ($__extends ?? '') . "\">\n" . $output;
                }

                return $output;
            }

            return $childContent;
        } catch (Throwable $e) {
            if (ob_get_level() > 0) {
                ob_end_clean();
            }
            error_reporting($previousErrorLevel);

            throw new ViewException(
                sprintf(
                    'Error rendering compiled view: %s (File: %s, Line: %d)',
                    $e->getMessage(),
                    $compiled,
                    $e->getLine()
                ),
                (int) $e->getCode(),
                $e
            );
        }
    }

    private function renderDirect(string $viewFile, array $data): string
    {
        $content = file_get_contents($viewFile);
        $compiledContent = $this->getCompiler()->compile($content);
        $compiledContent = $this->stripStrictTypesDeclaration($compiledContent);

        extract($data, EXTR_SKIP);

        // FIX: Don't reinitialize if already set by extract
        // This preserves stacks from child views
        $__sections = $__sections ?? [];
        $__stacks = $__stacks ?? [];
        $__extends = null;
        $__currentSection = null;

        $previousErrorLevel = $this->suppressNonCriticalErrors();

        ob_start();

        try {
            eval ('?>' . $compiledContent);
            $childContent = ob_get_clean() ?: '';
            error_reporting($previousErrorLevel);

            /** @phpstan-ignore-next-line */
            if (isset($__extends) && $__extends && !$this->suppressLayout) {
                // FIX: Pass stacks and childContent to parent layout
                return (string) $this->renderParentDirect(
                    $__extends,
                    array_merge($data, ['childContent' => $childContent]),
                    $__sections,
                    $__stacks
                );
            }

            if ($this->suppressLayout) {
                $output = $childContent;

                if ($this->requestedSection && isset($__sections[$this->requestedSection])) {
                    $output = $__sections[$this->requestedSection];
                } elseif (isset($__sections['content'])) {
                    $output = $__sections['content'];
                }

                if (isset($__sections['title'])) {
                    $output = "<title>{$__sections['title']}</title>\n" . $output;
                }

                if (!empty($__stacks['styles'])) {
                    $output .= implode("\n", $__stacks['styles']);
                }
                if (!empty($__stacks['scripts'])) {
                    $output .= implode("\n", $__stacks['scripts']);
                }

                // Include layout information for SPA to detect if a full reload is needed
                /** @phpstan-ignore-next-line */
                if (isset($__extends) && $__extends) {
                    $output = "<meta name=\"plugs-layout\" content=\"" . (string) ($__extends ?? '') . "\">\n" . $output;
                }

                return $output;
            }

            return $childContent;
        } catch (Throwable $e) {
            if (ob_get_level() > 0) {
                ob_end_clean();
            }
            error_reporting($previousErrorLevel);

            throw new ViewException(
                sprintf(
                    'Error rendering view: %s (File: %s, Line: %d)',
                    $e->getMessage(),
                    $viewFile,
                    $e->getLine()
                ),
                (int) $e->getCode(),
                $e
            );
        }
    }

    private function renderParent(string $parentView, array $data, array $sections, array $stacks = []): string
    {
        $parentFile = $this->getViewPath($parentView);

        if (!file_exists($parentFile)) {
            throw new ViewException(
                sprintf('Parent view [%s] not found at %s', $parentView, $parentFile),
                0,
                null,
                $parentView
            );
        }

        // FIX: Pass both sections and stacks to parent
        $parentData = array_merge($data, [
            '__sections' => $sections,
            '__stacks' => $stacks,
        ]);

        if ($this->cacheEnabled) {
            $parentCompiled = $this->getCompiledPath($parentView);
            if (!file_exists($parentCompiled) || filemtime($parentFile) > filemtime($parentCompiled)) {
                $this->compile($parentFile, $parentCompiled);
            }

            return $this->renderCompiled($parentCompiled, $parentData);
        }

        return $this->renderDirect($parentFile, $parentData);
    }

    private function renderParentDirect(string $parentView, array $data, array $sections, array $stacks = []): string
    {
        $parentFile = $this->getViewPath($parentView);

        if (!file_exists($parentFile)) {
            throw new ViewException(
                sprintf('Parent view [%s] not found', $parentView),
                0,
                null,
                $parentView
            );
        }

        $parentContent = file_get_contents($parentFile);
        $compiledParent = $this->getCompiler()->compile($parentContent);
        $compiledParent = $this->stripStrictTypesDeclaration($compiledParent);

        // FIX: Pass both sections and stacks to parent
        extract(array_merge($data, [
            '__sections' => $sections,
            '__stacks' => $stacks,
        ]), EXTR_SKIP);

        // Don't reinitialize $__stacks - it was just extracted!
        $__stacks = $__stacks ?? [];

        $previousErrorLevel = $this->suppressNonCriticalErrors();

        ob_start();

        try {
            eval ('?>' . $compiledParent);
            $result = ob_get_clean();
            error_reporting($previousErrorLevel);

            return (string) ($result ?: '');
        } catch (Throwable $e) {
            ob_end_clean();
            error_reporting($previousErrorLevel);

            throw new ViewException(
                sprintf(
                    'Error rendering parent view: %s (File: %s)',
                    $e->getMessage(),
                    $parentFile
                ),
                (int) $e->getCode(),
                $e,
                $parentView
            );
        }
    }

    private function compile(string $viewFile, string $compiled): void
    {
        $this->checkCompilationRateLimit();

        $content = file_get_contents($viewFile);
        $content = $this->getCompiler()->compile($content);
        $content = $this->stripStrictTypesDeclaration($content);

        file_put_contents($compiled, $content, LOCK_EX);
    }

    private function getCompiler(): ViewCompiler
    {
        if ($this->viewCompiler === null) {
            $this->viewCompiler = new ViewCompiler();
            $this->registerDefaultDirectives();
        }

        return $this->viewCompiler;
    }

    private function getViewPath(string $view): string
    {
        $view = str_replace('.', DIRECTORY_SEPARATOR, $view);

        foreach (self::VIEW_EXTENSIONS as $extension) {
            $viewPath = $this->viewPath . DIRECTORY_SEPARATOR . $view . $extension;

            if (file_exists($viewPath)) {
                $realViewPath = realpath(dirname($viewPath));
                $realBasePath = realpath($this->viewPath);

                if (
                    $realViewPath === false ||
                    $realBasePath === false ||
                    strpos($realViewPath, $realBasePath) !== 0
                ) {
                    throw new ViewException(
                        sprintf('Invalid view path: %s', $view),
                        0,
                        null,
                        $view
                    );
                }

                return $viewPath;
            }
        }

        throw new ViewException(
            sprintf(
                'View [%s] not found. Looked for: %s',
                $view,
                implode(', ', array_map(function ($ext) use ($view) {
                    return $view . $ext;
                }, self::VIEW_EXTENSIONS))
            ),
            0,
            null,
            $view
        );
    }

    private function getComponentPath(string $componentName): string
    {
        // Validate component name to prevent path traversal
        if (preg_match('/[\.\/\\\\]/', $componentName)) {
            throw new ViewException(
                sprintf('Invalid component name: %s (cannot contain path separators)', $componentName),
                0,
                null,
                $componentName
            );
        }

        $kebab = $this->pascalToKebabCase($componentName);
        $snake = str_replace('-', '_', $kebab);

        $filenames = array_unique([$kebab, $snake]);

        foreach ($filenames as $filename) {
            foreach (self::VIEW_EXTENSIONS as $extension) {
                $componentPath = $this->componentPath . DIRECTORY_SEPARATOR . $filename . $extension;

                if (file_exists($componentPath)) {
                    // Additional security check - verify real path is within component directory
                    $realComponentPath = realpath($componentPath);
                    $realBaseComponentPath = realpath($this->componentPath);

                    if (
                        $realComponentPath === false ||
                        $realBaseComponentPath === false ||
                        strpos($realComponentPath, $realBaseComponentPath) !== 0
                    ) {
                        throw new ViewException(
                            sprintf('Invalid component path: %s', $componentName),
                            0,
                            null,
                            $componentName
                        );
                    }

                    return $componentPath;
                }
            }
        }

        return $this->componentPath . DIRECTORY_SEPARATOR . $kebab . self::VIEW_EXTENSIONS[0];
    }

    private function getCompiledPath(string $view): string
    {
        return $this->cachePath . DIRECTORY_SEPARATOR . md5($view) . '.php';
    }

    private function stripStrictTypesDeclaration(string $content): string
    {
        return preg_replace(
            '/(<\?php\s+)declare\s*\(\s*strict_types\s*=\s*1\s*\)\s*;?\s*/i',
            '$1',
            $content
        );
    }

    public function pascalToKebabCase(string $input): string
    {
        $result = preg_replace('/([a-z])([A-Z])/', '$1-$2', $input);

        return strtolower($result);
    }

    public function anyToPascalCase(string $input): string
    {
        return str_replace(['_', '-'], '', ucwords($input, '_-'));
    }

    private function isDebugMode(): bool
    {
        if (defined('APP_DEBUG')) {
            return (bool) constant('APP_DEBUG');
        }

        if (isset($_ENV['APP_DEBUG'])) {
            return filter_var($_ENV['APP_DEBUG'], FILTER_VALIDATE_BOOLEAN);
        }

        $envDebug = getenv('APP_DEBUG');
        if ($envDebug !== false) {
            return filter_var($envDebug, FILTER_VALIDATE_BOOLEAN);
        }

        return false;
    }

    private function suppressNonCriticalErrors(): int
    {
        $previousLevel = error_reporting();

        if (!$this->isDebugMode()) {
            error_reporting(self::PRODUCTION_ERROR_LEVEL);
        }

        return $previousLevel;
    }

    private function ensureDirectoryExists(string $path): void
    {
        if (!is_dir($path)) {
            if (!mkdir($path, 0755, true) && !is_dir($path)) {
                throw new RuntimeException(
                    sprintf('Failed to create directory: %s', $path)
                );
            }
        }
    }

    // ============================================
    // NEW ENGINE ENHANCEMENTS
    // ============================================

    /**
     * Get the fragment renderer instance
     */
    public function getFragmentRenderer(): FragmentRenderer
    {
        if ($this->fragmentRenderer === null) {
            $this->fragmentRenderer = new FragmentRenderer();
        }
        return $this->fragmentRenderer;
    }

    /**
     * Get the view cache instance
     */
    public function getViewCache(): ViewCache
    {
        if ($this->viewCache === null) {
            $this->viewCache = new ViewCache($this->cachePath);
        }
        return $this->viewCache;
    }

    /**
     * Set a custom view cache instance
     */
    public function setViewCache(ViewCache $cache): self
    {
        $this->viewCache = $cache;
        return $this;
    }

    /**
     * Register a component alias
     * Usage: $engine->alias('btn', 'components.forms.button')
     */
    public function alias(string $alias, string $component): self
    {
        $this->componentAliases[$alias] = $component;
        return $this;
    }

    /**
     * Get component name from alias
     */
    public function resolveAlias(string $alias): string
    {
        return $this->componentAliases[$alias] ?? $alias;
    }

    /**
     * Preload views for faster rendering
     */
    public function preload(array $views): self
    {
        foreach ($views as $view) {
            $viewPath = $this->getViewPath($view);
            if (file_exists($viewPath)) {
                $this->preloadedViews[$view] = file_get_contents($viewPath);
            }
        }
        return $this;
    }

    /**
     * Warm the view cache for all views in the view directory
     * Returns the number of views warmed
     */
    public function warmCache(?string $tag = null): int
    {
        $count = 0;
        $pattern = $this->viewPath . DIRECTORY_SEPARATOR . '**' . DIRECTORY_SEPARATOR . '*.plug.php';

        foreach (glob($pattern, GLOB_BRACE) as $file) {
            $viewName = $this->getViewNameFromPath($file);

            // If tag specified, only warm views that match
            if ($tag !== null && strpos($viewName, $tag) === false) {
                continue;
            }

            $compiledPath = $this->getCompiledPath($viewName);
            if (!file_exists($compiledPath) || filemtime($file) > filemtime($compiledPath)) {
                $this->compile($file, $compiledPath);
                $count++;
            }
        }

        // Also warm root views
        foreach (self::VIEW_EXTENSIONS as $ext) {
            foreach (glob($this->viewPath . DIRECTORY_SEPARATOR . '*' . $ext) as $file) {
                $viewName = basename($file, $ext);
                $compiledPath = $this->getCompiledPath($viewName);
                if (!file_exists($compiledPath) || filemtime($file) > filemtime($compiledPath)) {
                    $this->compile($file, $compiledPath);
                    $count++;
                }
            }
        }

        return $count;
    }

    /**
     * Convert file path to view name
     */
    private function getViewNameFromPath(string $path): string
    {
        $relativePath = str_replace($this->viewPath . DIRECTORY_SEPARATOR, '', $path);

        foreach (self::VIEW_EXTENSIONS as $ext) {
            $relativePath = preg_replace('/' . preg_quote($ext, '/') . '$/', '', $relativePath);
        }

        return str_replace(DIRECTORY_SEPARATOR, '.', $relativePath);
    }

    /**
     * Render a dynamic component by name
     * Resolves aliases and renders the component
     */
    public function renderDynamic(string $componentName, array $data = []): string
    {
        // Resolve alias if exists
        $resolvedName = $this->resolveAlias($componentName);

        // Render as component
        return $this->renderComponent($resolvedName, $data);
    }

    /**
     * Render only a specific fragment from a view
     * Perfect for HTMX/Turbo partial updates
     */
    public function renderFragment(string $view, string $fragment, array $data = []): string
    {
        // Render the full view (fragments are captured)
        $this->render($view, array_merge($data, [
            '__fragmentRenderer' => $this->getFragmentRenderer(),
        ]));

        // Return only the requested fragment
        $content = $this->getFragmentRenderer()->getFragment($fragment);

        if ($content === null) {
            throw new ViewException(
                sprintf('Fragment [%s] not found in view [%s]', $fragment, $view),
                0,
                null,
                $view
            );
        }

        return $content;
    }

    /**
     * Render multiple views in parallel (when possible)
     * Returns array of view => rendered content
     */
    public function renderMany(array $views, array $sharedData = []): array
    {
        $results = [];

        foreach ($views as $view => $data) {
            if (is_int($view)) {
                $view = $data;
                $data = [];
            }

            $results[$view] = $this->render($view, array_merge($sharedData, $data));
        }

        return $results;
    }

    /**
     * Enable streaming mode
     */
    public function enableStreaming(bool $enable = true): self
    {
        $this->streamingEnabled = $enable;
        return $this;
    }

    /**
     * Stream render a view as a Generator
     * Yields content in chunks for large views
     */
    public function stream(string $view, array $data = []): \Generator
    {
        $content = $this->render($view, $data);

        // Stream in 8KB chunks
        $chunkSize = 8192;
        $length = strlen($content);

        for ($i = 0; $i < $length; $i += $chunkSize) {
            yield substr($content, $i, $chunkSize);
        }
    }

    /**
     * Render and output directly to the browser with flushing
     * Good for large views - sends content as it's ready
     */
    public function renderToStream(string $view, array $data = []): void
    {
        // Disable output buffering as much as possible
        while (ob_get_level() > 0) {
            ob_end_flush();
        }

        // Set headers for streaming
        if (!headers_sent()) {
            header('Content-Type: text/html; charset=utf-8');
            header('X-Accel-Buffering: no');
        }

        // Stream the content
        foreach ($this->stream($view, $data) as $chunk) {
            echo $chunk;
            if (connection_aborted()) {
                break;
            }
            flush();
        }
    }

    /**
     * Get path with caching for performance
     */
    public function getViewPathCached(string $view): string
    {
        if (!isset($this->pathCache[$view])) {
            $this->pathCache[$view] = $this->getViewPath($view);
        }
        return $this->pathCache[$view];
    }

    /**
     * Clear path cache
     */
    public function clearPathCache(): self
    {
        $this->pathCache = [];
        return $this;
    }

    /**
     * Check if view exists (with caching)
     */
    public function exists(string $view): bool
    {
        $path = $this->getViewPathCached($view);
        return file_exists($path);
    }

    /**
     * Get all registered component aliases
     */
    public function getAliases(): array
    {
        return $this->componentAliases;
    }

    /**
     * Render view if it's a partial/HTMX request, otherwise render full layout
     */
    public function renderSmart(string $view, array $data = []): string
    {
        // Check if this is a partial request
        if (FragmentRenderer::isPartialRequest()) {
            $requestedFragment = FragmentRenderer::getRequestedFragment();

            if ($requestedFragment !== null) {
                try {
                    return $this->renderFragment($view, $requestedFragment, $data);
                } catch (ViewException) {
                    // Fragment not found, render full view
                }
            }

            // Suppress layout for partial requests
            $this->suppressLayout(true);
        }

        return $this->render($view, $data);
    }

    /**
     * Get all available fragments from the last render
     */
    public function getRenderedFragments(): array
    {
        return $this->getFragmentRenderer()->getFragments();
    }

    /**
     * Get teleport content for rendering at end of body
     */
    public function getTeleportScripts(): string
    {
        return $this->getFragmentRenderer()->renderTeleportScripts();
    }

    // ============================================
    // PERFORMANCE OPTIMIZATIONS
    // ============================================

    /**
     * Fast hash function - uses xxHash on PHP 8.1+ for ~3-4x speedup
     * Falls back to md5 for older PHP versions
     */
    public static function fastHash(string $content): string
    {
        // PHP 8.1+ has xxh128 which is significantly faster than md5
        if (function_exists('hash') && in_array('xxh128', hash_algos(), true)) {
            return hash('xxh128', $content);
        }

        // Fallback to md5 for older PHP
        return md5($content);
    }

    /**
     * Cached file existence check - reduces I/O operations
     */
    public function fileExistsCached(string $path): bool
    {
        if (!isset($this->fileExistsCache[$path])) {
            $this->fileExistsCache[$path] = file_exists($path);
        }
        return $this->fileExistsCache[$path];
    }

    /**
     * Clear file existence cache
     * Call after creating/deleting files
     */
    public function clearFileExistsCache(?string $path = null): self
    {
        if ($path !== null) {
            unset($this->fileExistsCache[$path]);
        } else {
            $this->fileExistsCache = [];
        }
        return $this;
    }

    /**
     * Invalidate specific path in cache
     */
    public function invalidatePath(string $path): self
    {
        unset($this->fileExistsCache[$path]);
        unset($this->pathCache[$path]);
        return $this;
    }

    /**
     * Enable/disable OPcache compilation hints
     */
    public function setOpcacheEnabled(bool $enabled): self
    {
        $this->opcacheEnabled = $enabled;
        return $this;
    }

    /**
     * Compile file to OPcache if available
     */
    public function opcacheCompile(string $filePath): bool
    {
        if (!$this->opcacheEnabled) {
            return false;
        }

        if (!function_exists('opcache_compile_file')) {
            return false;
        }

        if (!function_exists('opcache_is_script_cached')) {
            return false;
        }

        // Don't recompile if already cached
        if (opcache_is_script_cached($filePath)) {
            return true;
        }

        // Suppress errors - OPcache might not be enabled
        return @opcache_compile_file($filePath);
    }

    /**
     * Invalidate OPcache for a file
     */
    public function opcacheInvalidate(string $filePath): bool
    {
        if (!function_exists('opcache_invalidate')) {
            return false;
        }

        return @opcache_invalidate($filePath, true);
    }

    /**
     * Compile and cache view with OPcache optimization
     */
    public function compileWithOpcache(string $viewFile, string $compiledPath): void
    {
        $this->compile($viewFile, $compiledPath);

        // Add to OPcache
        $this->opcacheCompile($compiledPath);

        // Update file existence cache
        $this->fileExistsCache[$compiledPath] = true;
    }

    /**
     * Register a lazy-loaded component
     * Component will only be parsed when first used
     */
    public function registerLazyComponent(string $name, callable $loader): self
    {
        $this->lazyComponents[$name] = $loader;
        return $this;
    }

    /**
     * Get a lazy component, loading it if needed
     */
    public function getLazyComponent(string $name): ?string
    {
        // Check if already loaded
        if (isset($this->componentDefinitionCache[$name])) {
            return $this->componentDefinitionCache[$name];
        }

        // Check if has lazy loader
        if (isset($this->lazyComponents[$name])) {
            $loader = $this->lazyComponents[$name];
            $this->componentDefinitionCache[$name] = $loader();
            return $this->componentDefinitionCache[$name];
        }

        return null;
    }

    /**
     * Preload component definitions for faster rendering
     */
    public function preloadComponents(array $componentNames): self
    {
        foreach ($componentNames as $name) {
            $path = $this->getComponentPath($name);
            if ($this->fileExistsCached($path)) {
                $this->componentDefinitionCache[$name] = file_get_contents($path);
            }
        }
        return $this;
    }

    /**
     * Get optimized compiled path using fast hash
     */
    public function getOptimizedCompiledPath(string $view): string
    {
        $hash = self::fastHash($view);
        return $this->cachePath . DIRECTORY_SEPARATOR . $hash . '.php';
    }

    /**
     * Warm cache with OPcache optimization
     */
    public function warmCacheWithOpcache(?string $tag = null): array
    {
        $count = $this->warmCache($tag);
        $opcacheCount = 0;

        // Compile all cached views to OPcache
        foreach (glob($this->cachePath . DIRECTORY_SEPARATOR . '*.php') as $file) {
            if ($this->opcacheCompile($file)) {
                $opcacheCount++;
            }
        }

        return [
            'views_compiled' => $count,
            'opcache_compiled' => $opcacheCount,
        ];
    }

    /**
     * Get performance statistics
     */
    public function getPerformanceStats(): array
    {
        $stats = [
            'path_cache_size' => count($this->pathCache),
            'file_exists_cache_size' => count($this->fileExistsCache),
            'preloaded_views' => count($this->preloadedViews),
            'component_cache_size' => count($this->componentDefinitionCache),
            'lazy_components_registered' => count($this->lazyComponents),
            'opcache_enabled' => $this->opcacheEnabled,
        ];

        // Add OPcache stats if available
        if (function_exists('opcache_get_status')) {
            $opcacheStatus = @opcache_get_status(false);
            if ($opcacheStatus) {
                $stats['opcache_memory_used'] = $opcacheStatus['memory_usage']['used_memory'] ?? 0;
                $stats['opcache_scripts_cached'] = $opcacheStatus['opcache_statistics']['num_cached_scripts'] ?? 0;
                $stats['opcache_hit_rate'] = $opcacheStatus['opcache_statistics']['opcache_hit_rate'] ?? 0;
            }
        }

        return $stats;
    }

    /**
     * Clear all caches for fresh start
     */
    public function clearAllCaches(): self
    {
        $this->pathCache = [];
        $this->fileExistsCache = [];
        $this->preloadedViews = [];
        $this->componentDefinitionCache = [];

        // Clear view cache
        if ($this->viewCache) {
            $this->viewCache->flush();
        }

        return $this;
    }

    /**
     * Optimize view for production
     * Precompiles and caches everything
     */
    public function optimizeForProduction(): array
    {
        $results = [];

        // Enable fast cache
        $this->setFastCache(true);

        // Warm all caches with OPcache
        $results['cache_warming'] = $this->warmCacheWithOpcache();

        // Get stats
        $results['stats'] = $this->getPerformanceStats();

        return $results;
    }
}
