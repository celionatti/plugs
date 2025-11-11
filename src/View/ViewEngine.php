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
use Throwable;

class ViewEngine
{
    private string $viewPath;
    private string $cachePath;
    private bool $cacheEnabled;
    private array $sharedData = [];
    private string $componentPath;
    private ViewCompiler $viewCompiler;
    private array $customDirectives = [];

    private const VIEW_EXTENSIONS = ['.plug.php', '.php', '.html'];
    private const PRODUCTION_ERROR_LEVEL = E_ERROR | E_PARSE | E_CORE_ERROR | E_COMPILE_ERROR;

    public function __construct(string $viewPath, string $cachePath, bool $cacheEnabled = false)
    {
        $this->viewPath = rtrim($viewPath, '/\\');
        $this->cachePath = rtrim($cachePath, '/\\');
        $this->cacheEnabled = $cacheEnabled;
        $this->componentPath = $this->viewPath . DIRECTORY_SEPARATOR . 'components';

        $this->ensureDirectoryExists($this->cachePath);
        $this->ensureDirectoryExists($this->componentPath);

        $this->viewCompiler = new ViewCompiler($this);
        $this->registerDefaultDirectives();
    }

    public function directive(string $name, callable $handler): void
    {
        $this->customDirectives[$name] = $handler;
        $this->viewCompiler->registerCustomDirective($name, $handler);
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

        $this->directive('auth', function () {
            return "<?php if (function_exists('auth') && auth()->check()): ?>";
        });

        $this->directive('endauth', function () {
            return "<?php endif; ?>";
        });

        $this->directive('guest', function () {
            return "<?php if (function_exists('auth') && !auth()->check()): ?>";
        });

        $this->directive('endguest', function () {
            return "<?php endif; ?>";
        });
    }

    public function render(string $view, array $data = [], bool $isComponent = false): string
    {
        $data = array_merge($this->sharedData, $data);
        $data['view'] = $this;

        $viewFile = $isComponent
            ? $this->getComponentPath($view)
            : $this->getViewPath($view);

        if (!file_exists($viewFile)) {
            throw new RuntimeException(
                sprintf('View [%s] not found at %s', $view, $viewFile)
            );
        }

        if ($this->cacheEnabled) {
            return $this->renderCached($view, $viewFile, $data, $isComponent);
        }

        return $this->renderDirect($viewFile, $data);
    }

    public function renderComponent(string $componentName, array $data = []): string
    {
        $componentFile = $this->getComponentPath($componentName);

        if (!file_exists($componentFile)) {
            throw new RuntimeException(
                sprintf('Component [%s] not found at %s', $componentName, $componentFile)
            );
        }

        $slotId = $data['__slot_id'] ?? null;
        unset($data['__slot_id']);

        $slot = '';
        if ($slotId) {
            $compiledSlot = $this->viewCompiler->getCompiledSlot($slotId);
            if (!empty($compiledSlot)) {
                $slot = $this->executeCompiledContent($compiledSlot, $data);
            }
        }

        $componentData = array_merge($data, ['slot' => $slot]);

        return $this->render($componentName, $componentData, true);
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
                $componentName = $this->snakeToPascalCase($filename);

                if (!isset($components[$componentName])) {
                    $components[$componentName] = $file;
                }
            }
        }

        return $components;
    }

    public function clearCache(): void
    {
        $this->viewCompiler->clearCache();

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

        $view = $this;
        $__sections = $__sections ?? [];
        $__stacks = $__stacks ?? [];

        $previousErrorLevel = $this->suppressNonCriticalErrors();

        ob_start();
        try {
            eval('?>' . $compiledContent);
            $result = ob_get_clean();
            error_reporting($previousErrorLevel);
            return $result;
        } catch (Throwable $e) {
            ob_end_clean();
            error_reporting($previousErrorLevel);
            throw new RuntimeException(
                sprintf('Error executing compiled content: %s', $e->getMessage()),
                0,
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

        $__sections = [];
        $__stacks = [];
        $__extends = null;
        $__currentSection = null;

        $previousErrorLevel = $this->suppressNonCriticalErrors();

        ob_start();
        try {
            include $compiled;
            $childContent = ob_get_clean();
            error_reporting($previousErrorLevel);

            if (isset($__extends) && $__extends) {
                return $this->renderParent($__extends, $data, $__sections);
            }

            return $childContent;
        } catch (Throwable $e) {
            ob_end_clean();
            error_reporting($previousErrorLevel);
            throw new RuntimeException(
                sprintf(
                    'Error rendering compiled view: %s (File: %s, Line: %d)',
                    $e->getMessage(),
                    $compiled,
                    $e->getLine()
                ),
                0,
                $e
            );
        }
    }

    private function renderDirect(string $viewFile, array $data): string
    {
        $content = file_get_contents($viewFile);
        $compiledContent = $this->viewCompiler->compile($content);
        $compiledContent = $this->stripStrictTypesDeclaration($compiledContent);

        extract($data, EXTR_SKIP);

        $__sections = [];
        $__stacks = [];
        $__extends = null;
        $__currentSection = null;

        $previousErrorLevel = $this->suppressNonCriticalErrors();

        ob_start();
        try {
            eval('?>' . $compiledContent);
            $childContent = ob_get_clean();
            error_reporting($previousErrorLevel);

            if (isset($__extends) && $__extends) {
                return $this->renderParentDirect($__extends, $data, $__sections);
            }

            return $childContent;
        } catch (Throwable $e) {
            ob_end_clean();
            error_reporting($previousErrorLevel);
            throw new RuntimeException(
                sprintf(
                    'Error rendering view: %s (File: %s, Line: %d)',
                    $e->getMessage(),
                    $viewFile,
                    $e->getLine()
                ),
                0,
                $e
            );
        }
    }

    private function renderParent(string $parentView, array $data, array $sections): string
    {
        $parentFile = $this->getViewPath($parentView);

        if (!file_exists($parentFile)) {
            throw new RuntimeException(
                sprintf('Parent view [%s] not found at %s', $parentView, $parentFile)
            );
        }

        $parentData = array_merge($data, ['__sections' => $sections]);

        if ($this->cacheEnabled) {
            $parentCompiled = $this->getCompiledPath($parentView);
            if (!file_exists($parentCompiled) || filemtime($parentFile) > filemtime($parentCompiled)) {
                $this->compile($parentFile, $parentCompiled);
            }
            return $this->renderCompiled($parentCompiled, $parentData);
        }

        return $this->renderDirect($parentFile, $parentData);
    }

    private function renderParentDirect(string $parentView, array $data, array $sections): string
    {
        $parentFile = $this->getViewPath($parentView);

        if (!file_exists($parentFile)) {
            throw new RuntimeException(
                sprintf('Parent view [%s] not found', $parentView)
            );
        }

        $parentContent = file_get_contents($parentFile);
        $compiledParent = $this->viewCompiler->compile($parentContent);
        $compiledParent = $this->stripStrictTypesDeclaration($compiledParent);

        extract(array_merge($data, ['__sections' => $sections]), EXTR_SKIP);

        $__stacks = [];
        $previousErrorLevel = $this->suppressNonCriticalErrors();

        ob_start();
        try {
            eval('?>' . $compiledParent);
            $result = ob_get_clean();
            error_reporting($previousErrorLevel);
            return $result;
        } catch (Throwable $e) {
            ob_end_clean();
            error_reporting($previousErrorLevel);
            throw new RuntimeException(
                sprintf(
                    'Error rendering parent view: %s (File: %s)',
                    $e->getMessage(),
                    $parentFile
                ),
                0,
                $e
            );
        }
    }

    private function compile(string $viewFile, string $compiled): void
    {
        $content = file_get_contents($viewFile);
        $content = $this->viewCompiler->compile($content);
        $content = $this->stripStrictTypesDeclaration($content);

        file_put_contents($compiled, $content, LOCK_EX);
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
                    throw new RuntimeException(
                        sprintf('Invalid view path: %s', $view)
                    );
                }

                return $viewPath;
            }
        }

        throw new RuntimeException(
            sprintf(
                'View [%s] not found. Looked for: %s',
                $view,
                implode(', ', array_map(function ($ext) use ($view) {
                    return $view . $ext;
                }, self::VIEW_EXTENSIONS))
            )
        );
    }

    private function getComponentPath(string $componentName): string
    {
        $filename = $this->pascalToSnakeCase($componentName);

        foreach (self::VIEW_EXTENSIONS as $extension) {
            $componentPath = $this->componentPath . DIRECTORY_SEPARATOR . $filename . $extension;

            if (file_exists($componentPath)) {
                return $componentPath;
            }
        }

        return $this->componentPath . DIRECTORY_SEPARATOR . $filename . self::VIEW_EXTENSIONS[0];
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

    private function pascalToSnakeCase(string $input): string
    {
        $result = preg_replace('/([a-z])([A-Z])/', '$1_$2', $input);
        return strtolower($result);
    }

    private function snakeToPascalCase(string $input): string
    {
        return str_replace('_', '', ucwords($input, '_'));
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
