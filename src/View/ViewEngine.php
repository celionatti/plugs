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

class ViewEngine
{
    private $viewPath;
    private $cachePath;
    private $cacheEnabled;
    private $sharedData = [];
    private $componentPath;
    private $viewCompiler;

    public function __construct(string $viewPath, string $cachePath, bool $cacheEnabled = false)
    {
        $this->viewPath = rtrim($viewPath, '/');
        $this->cachePath = rtrim($cachePath, '/');
        $this->cacheEnabled = $cacheEnabled;
        $this->componentPath = $this->viewPath . '/components';

        if (!is_dir($this->cachePath)) {
            mkdir($this->cachePath, 0755, true);
        }

        if (!is_dir($this->componentPath)) {
            mkdir($this->componentPath, 0755, true);
        }

        // Initialize compiler
        $this->viewCompiler = new ViewCompiler($this);
    }

    /**
     * Render a component
     */
    public function renderComponent(string $componentName, array $data = []): string
    {
        $componentFile = $this->getComponentPath($componentName);

        if (!file_exists($componentFile)) {
            throw new \RuntimeException("Component [{$componentName}] not found at {$componentFile}");
        }

        // Handle slot content if provided via slot ID
        $slotId = $data['__slot_id'] ?? null;
        unset($data['__slot_id']);

        $slot = '';
        if ($slotId) {
            // Get the compiled slot content
            $compiledSlot = $this->viewCompiler->getCompiledSlot($slotId);

            // Execute the compiled slot with current data context
            if (!empty($compiledSlot)) {
                $slot = $this->executeCompiledContent($compiledSlot, $data);
            }
        }

        // Merge data and add slot variable
        $componentData = array_merge($data, ['slot' => $slot]);

        return $this->render($componentName, $componentData, true);
    }

    /**
     * Execute compiled PHP content with provided data
     */
    private function executeCompiledContent(string $compiledContent, array $data): string
    {
        // Remove any strict_types declarations from compiled content
        $compiledContent = $this->stripStrictTypesDeclaration($compiledContent);

        // Extract data into local scope
        extract(array_merge($this->sharedData, $data), EXTR_SKIP);

        // Ensure view engine is available
        $view = $this;

        // Initialize template variables
        $__sections = $__sections ?? [];
        $__stacks = $__stacks ?? [];

        // Suppress undefined variable warnings in production
        $previousErrorLevel = error_reporting();
        if (!$this->isDebugMode()) {
            error_reporting($previousErrorLevel & ~E_WARNING & ~E_NOTICE);
        }

        ob_start();
        try {
            // Execute the compiled content
            eval('?>' . $compiledContent);
            $result = ob_get_clean();
            error_reporting($previousErrorLevel);
            return $result;
        } catch (\Throwable $e) {
            ob_end_clean();
            error_reporting($previousErrorLevel);
            throw new \RuntimeException(
                "Error executing compiled content: " . $e->getMessage(),
                0,
                $e
            );
        }
    }

    /**
     * Check if debug mode is enabled
     */
    private function isDebugMode(): bool
    {
        // Check constant first
        if (defined('APP_DEBUG')) {
            return (bool) constant('APP_DEBUG');
        }

        // Check environment variables
        if (isset($_ENV['APP_DEBUG'])) {
            return filter_var($_ENV['APP_DEBUG'], FILTER_VALIDATE_BOOLEAN);
        }

        if (getenv('APP_DEBUG') !== false) {
            return filter_var(getenv('APP_DEBUG'), FILTER_VALIDATE_BOOLEAN);
        }

        // Default to false (production mode)
        return false;
    }

    /**
     * Strip strict_types declaration from compiled content
     * This is necessary because eval() cannot have declare() as the first statement
     */
    private function stripStrictTypesDeclaration(string $content): string
    {
        // Remove declare(strict_types=1); from the beginning of PHP blocks
        $content = preg_replace(
            '/(<\?php\s+)declare\s*\(\s*strict_types\s*=\s*1\s*\)\s*;?\s*/i',
            '$1',
            $content
        );

        return $content;
    }

    /**
     * Get the path to a component file
     */
    private function getComponentPath(string $componentName): string
    {
        // Convert CamelCase to snake_case
        $filename = preg_replace_callback('/([a-z])([A-Z])/', function ($matches) {
            return $matches[1] . '_' . strtolower($matches[2]);
        }, $componentName);
        $filename = strtolower($filename);

        return "{$this->componentPath}/{$filename}.plug.php";
    }

    public function render(string $view, array $data = [], bool $isComponent = false): string
    {
        $data = array_merge($this->sharedData, $data);

        // Always pass the view engine instance
        $data['view'] = $this;

        $viewFile = $isComponent ? $this->getComponentPath($view) : $this->getViewPath($view);

        if (!file_exists($viewFile)) {
            throw new \RuntimeException("View [{$view}] not found at {$viewFile}");
        }

        if ($this->cacheEnabled) {
            $compiled = $this->getCompiledPath($view . ($isComponent ? '_component' : ''));

            if (!file_exists($compiled) || filemtime($viewFile) > filemtime($compiled)) {
                $this->compile($viewFile, $compiled);
            }

            return $this->renderCompiled($compiled, $data);
        }

        return $this->renderDirect($viewFile, $data);
    }

    public function share(string $key, $value): void
    {
        $this->sharedData[$key] = $value;
    }

    private function getViewPath(string $view): string
    {
        $view = str_replace('.', '/', $view);
        $viewPath = "{$this->viewPath}/{$view}.plug.php";

        // Prevent directory traversal
        $realViewPath = realpath($viewPath);
        $realBasePath = realpath($this->viewPath);

        if ($realViewPath === false || strpos($realViewPath, $realBasePath) !== 0) {
            throw new \RuntimeException("Invalid view path: {$view}");
        }

        return $viewPath;
    }

    private function getCompiledPath(string $view): string
    {
        return "{$this->cachePath}/" . md5($view) . '.php';
    }

    private function compile(string $viewFile, string $compiled): void
    {
        $content = file_get_contents($viewFile);
        $content = $this->viewCompiler->compile($content);

        // Strip strict_types from compiled content before saving
        $content = $this->stripStrictTypesDeclaration($content);

        file_put_contents($compiled, $content);
    }

    /**
     * Check if a component exists
     */
    public function componentExists(string $componentName): bool
    {
        return file_exists($this->getComponentPath($componentName));
    }

    /**
     * Get all available components
     */
    public function getAvailableComponents(): array
    {
        $components = [];
        $files = glob($this->componentPath . '/*.plug.php');

        foreach ($files as $file) {
            $filename = pathinfo($file, PATHINFO_FILENAME);
            $componentName = str_replace('_', '', ucwords($filename, '_'));
            $components[$componentName] = $file;
        }

        return $components;
    }

    private function renderCompiled(string $compiled, array $data): string
    {
        extract($data, EXTR_SKIP);

        // Initialize template variables
        $__sections = [];
        $__stacks = [];
        $__extends = null;
        $__currentSection = null;

        // Suppress undefined variable warnings in production
        $previousErrorLevel = error_reporting();
        if (!$this->isDebugMode()) {
            error_reporting($previousErrorLevel & ~E_WARNING & ~E_NOTICE);
        }

        ob_start();
        try {
            include $compiled;
            $childContent = ob_get_clean();
            error_reporting($previousErrorLevel);

            // Handle template inheritance
            if (isset($__extends) && $__extends) {
                return $this->renderParent($__extends, $data, $__sections);
            }

            return $childContent;
        } catch (\Throwable $e) {
            ob_end_clean();
            error_reporting($previousErrorLevel);
            throw new \RuntimeException(
                "Error rendering compiled view: " . $e->getMessage() .
                    "\nFile: " . $compiled . "\nLine: " . $e->getLine(),
                0,
                $e
            );
        }
    }

    private function renderParent(string $parentView, array $data, array $sections): string
    {
        $parentFile = $this->getViewPath($parentView);
        if (!file_exists($parentFile)) {
            throw new \RuntimeException("Parent view [{$parentView}] not found at {$parentFile}");
        }

        // Merge sections into data for parent
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

    private function renderDirect(string $viewFile, array $data): string
    {
        // Compile the view content
        $content = file_get_contents($viewFile);
        $compiledContent = $this->viewCompiler->compile($content);

        // Remove strict_types declaration before eval
        $compiledContent = $this->stripStrictTypesDeclaration($compiledContent);

        extract($data, EXTR_SKIP);

        // Initialize template variables
        $__sections = [];
        $__stacks = [];
        $__extends = null;
        $__currentSection = null;

        // Suppress undefined variable warnings in production
        $previousErrorLevel = error_reporting();
        if (!$this->isDebugMode()) {
            error_reporting($previousErrorLevel & ~E_WARNING & ~E_NOTICE);
        }

        ob_start();
        try {
            // Execute compiled content
            eval('?>' . $compiledContent);
            $childContent = ob_get_clean();
            error_reporting($previousErrorLevel);

            // Handle template inheritance
            if (isset($__extends) && $__extends) {
                $parentView = $this->getViewPath($__extends);
                if (!file_exists($parentView)) {
                    throw new \RuntimeException("Parent view [{$__extends}] not found");
                }

                // Compile and render parent
                $parentContent = file_get_contents($parentView);
                $compiledParent = $this->viewCompiler->compile($parentContent);

                // Remove strict_types from parent as well
                $compiledParent = $this->stripStrictTypesDeclaration($compiledParent);

                // Re-extract data for parent template
                extract($data, EXTR_SKIP);

                // Re-initialize template variables for parent
                $__sections = $__sections ?? [];
                $__stacks = $__stacks ?? [];

                ob_start();
                eval('?>' . $compiledParent);
                $result = ob_get_clean();
                error_reporting($previousErrorLevel);
                return $result;
            }

            return $childContent;
        } catch (\Throwable $e) {
            ob_end_clean();
            error_reporting($previousErrorLevel);
            throw new \RuntimeException(
                "Error rendering view: " . $e->getMessage() .
                    "\nFile: " . $viewFile .
                    "\nLine: " . $e->getLine(),
                0,
                $e
            );
        }
    }

    /**
     * Clear compilation cache
     */
    public function clearCache(): void
    {
        $this->viewCompiler->clearCache();

        // Also clear cached files if needed
        if ($this->cacheEnabled && is_dir($this->cachePath)) {
            $files = glob($this->cachePath . '/*.php');
            foreach ($files as $file) {
                if (is_file($file)) {
                    @unlink($file);
                }
            }
        }
    }
}
