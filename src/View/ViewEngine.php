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

    private const VIEW_EXTENSIONS = ['.plug.php', '.php', '.html'];
    private const PRODUCTION_ERROR_LEVEL = E_ERROR | E_PARSE | E_CORE_ERROR | E_COMPILE_ERROR;

    private ?string $cspNonce = null;
    private array $compilationAttempts = [];

    private array $composers = [];

    public function __construct(string $viewPath, string $cachePath, bool $cacheEnabled = false)
    {
        $this->viewPath = rtrim($viewPath, '/\\');
        $this->cachePath = rtrim($cachePath, '/\\');
        $this->cacheEnabled = $cacheEnabled;
        $this->componentPath = $this->viewPath . DIRECTORY_SEPARATOR . 'components';

        $this->ensureDirectoryExists($this->cachePath);
        $this->ensureDirectoryExists($this->componentPath);

        // $this->viewCompiler = new ViewCompiler($this);
        // $this->registerDefaultDirectives();
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

                    // Wrap in reactive container
                    return sprintf(
                        '<div data-plug-component="%s" data-plug-state="%s" id="%s">%s</div>',
                        $componentName,
                        $component->serializeState(),
                        $component->getId(),
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

        if (!file_exists($compiled) || filemtime($viewFile) > filemtime($compiled)) {
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
}
