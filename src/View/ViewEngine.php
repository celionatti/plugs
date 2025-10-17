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

    public function __construct(string $viewPath, string $cachePath, bool $cacheEnabled = true)
    {
        $this->viewPath = rtrim($viewPath, '/');
        $this->cachePath = rtrim($cachePath, '/');
        $this->cacheEnabled = $cacheEnabled;
        $this->componentPath = $this->viewPath . '/components';

        if (!is_dir($this->cachePath)) {
            mkdir($this->cachePath, 0755, true);
        }

        // Create components directory if it doesn't exist
        if (!is_dir($this->componentPath)) {
            mkdir($this->componentPath, 0755, true);
        }
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

        // Extract slot content if provided
        $slotContent = $data['__slot'] ?? '';
        unset($data['__slot']);

        // Merge data and add slot variable
        $componentData = array_merge($data, ['slot' => $slotContent]);

        return $this->render($componentName, $componentData, true);
    }

    /**
     * Get the path to a component file
     */
    private function getComponentPath(string $componentName): string
    {
        // Convert CamelCase to snake_case for filename
        $filename = strtolower(preg_replace('/(?<!^)[A-Z]/', '_$0', $componentName));
        return "{$this->componentPath}/{$filename}.plug.php";
    }

    // public function render(string $view, array $data = []): string
    // {
    //     $data = array_merge($this->sharedData, $data);

    //     // Always pass the view engine instance for @include support
    //     $data['view'] = $this;

    //     $viewFile = $this->getViewPath($view);

    //     if (!file_exists($viewFile)) {
    //         throw new \RuntimeException("View [{$view}] not found at {$viewFile}");
    //     }

    //     if ($this->cacheEnabled) {
    //         $compiled = $this->getCompiledPath($view);

    //         if (!file_exists($compiled) || filemtime($viewFile) > filemtime($compiled)) {
    //             $this->compile($viewFile, $compiled);
    //         }

    //         return $this->renderCompiled($compiled, $data);
    //     }

    //     return $this->renderDirect($viewFile, $data);
    // }

    public function render(string $view, array $data = [], bool $isComponent = false): string
    {
        $data = array_merge($this->sharedData, $data);

        // Always pass the view engine instance for @include and component support
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
        return "{$this->viewPath}/{$view}.plug.php";
    }

    private function getCompiledPath(string $view): string
    {
        return "{$this->cachePath}/" . md5($view) . '.php';
    }

    private function compile(string $viewFile, string $compiled): void
    {
        $content = file_get_contents($viewFile);

        $compiler = new ViewCompiler($this);
        $content = $compiler->compile($content);

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
            // Convert snake_case back to CamelCase
            $componentName = str_replace('_', '', ucwords($filename, '_'));
            $components[] = $componentName;
        }

        return $components;
    }

    private function renderCompiled(string $compiled, array $data): string
    {
        extract($data, EXTR_SKIP);

        // Initialize sections array
        $__sections = [];
        $__extends = null;

        ob_start();
        try {
            include $compiled;
            $childContent = ob_get_clean();

            // If template extends a layout, render the parent
            if (isset($__extends) && $__extends) {
                $parentView = $this->getViewPath($__extends);
                if (!file_exists($parentView)) {
                    throw new \RuntimeException("Parent view [{$__extends}] not found");
                }

                $parentCompiled = $this->getCompiledPath($__extends);
                if (!file_exists($parentCompiled) || filemtime($parentView) > filemtime($parentCompiled)) {
                    $this->compile($parentView, $parentCompiled);
                }

                // Render parent with sections from child
                // Re-extract data for parent template
                extract($data, EXTR_SKIP);
                ob_start();
                include $parentCompiled;
                return ob_get_clean();
            }

            return $childContent;
        } catch (\Throwable $e) {
            ob_end_clean();
            throw new \RuntimeException(
                "Error rendering view: " . $e->getMessage() . "\nFile: " . $compiled,
                0,
                $e
            );
        }
    }

    private function renderDirect(string $viewFile, array $data): string
    {
        // Compile the view content in memory (don't save to disk)
        $content = file_get_contents($viewFile);
        $compiler = new ViewCompiler();
        $compiledContent = $compiler->compile($content);

        // Create a temporary file for the compiled content
        $tempFile = tempnam(sys_get_temp_dir(), 'view_');
        file_put_contents($tempFile, $compiledContent);

        extract($data, EXTR_SKIP);

        // Initialize sections array
        $__sections = [];
        $__extends = null;

        ob_start();
        try {
            include $tempFile;
            $childContent = ob_get_clean();

            // Clean up temp file
            @unlink($tempFile);

            // If template extends a layout, render the parent
            if (isset($__extends) && $__extends) {
                $parentView = $this->getViewPath($__extends);
                if (!file_exists($parentView)) {
                    throw new \RuntimeException("Parent view [{$__extends}] not found");
                }

                // Compile parent view
                $parentContent = file_get_contents($parentView);
                $compiledParent = $compiler->compile($parentContent);

                $parentTempFile = tempnam(sys_get_temp_dir(), 'view_');
                file_put_contents($parentTempFile, $compiledParent);

                // Re-extract data for parent template
                extract($data, EXTR_SKIP);
                ob_start();
                include $parentTempFile;
                $result = ob_get_clean();

                // Clean up parent temp file
                @unlink($parentTempFile);

                return $result;
            }

            return $childContent;
        } catch (\Throwable $e) {
            ob_end_clean();
            // Clean up temp file on error
            @unlink($tempFile);
            if (isset($parentTempFile)) {
                @unlink($parentTempFile);
            }
            throw new \RuntimeException("Error rendering view: " . $e->getMessage(), 0, $e);
        }
    }
}
