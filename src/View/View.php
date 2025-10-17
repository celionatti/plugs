<?php

declare(strict_types=1);

namespace Plugs\View;

/*
|--------------------------------------------------------------------------
| View Class
|--------------------------------------------------------------------------
|
| This class represents a view in the Plugs framework. It is responsible for
| rendering templates with provided data using a specified view engine.
*/

class View
{
    private $engine;
    private $view;
    private $data;
    
    public function __construct(ViewEngine $engine, string $view, array $data = [])
    {
        $this->engine = $engine;
        $this->view = $view;
        $this->data = $data;
    }
    
    /**
     * Add data to the view
     */
    public function with(string $key, $value): self
    {
        $this->data[$key] = $value;
        return $this;
    }
    
    /**
     * Add multiple data items to the view
     */
    public function withData(array $data): self
    {
        $this->data = array_merge($this->data, $data);
        return $this;
    }
    
    /**
     * Get the view engine instance
     */
    public function getEngine(): ViewEngine
    {
        return $this->engine;
    }
    
    /**
     * Get the view name
     */
    public function getView(): string
    {
        return $this->view;
    }
    
    /**
     * Get the view data
     */
    public function getData(): array
    {
        return $this->data;
    }
    
    /**
     * Render the view
     */
    public function render(): string
    {
        try {
            // The engine already adds 'view' to data, but we ensure it's available
            return $this->engine->render($this->view, $this->data);
        } catch (\Throwable $e) {
            // Re-throw with more context
            throw new \RuntimeException(
                "Error rendering view [{$this->view}]: " . $e->getMessage(),
                $e->getCode(),
                $e
            );
        }
    }
    
    /**
     * Magic method to convert view to string
     */
    public function __toString(): string
    {
        try {
            return $this->render();
        } catch (\Throwable $e) {
            // Can't throw exceptions from __toString, so return error message
            return $this->handleToStringError($e);
        }
    }
    
    /**
     * Handle errors in __toString method
     */
    private function handleToStringError(\Throwable $e): string
    {
        // Check for debug mode
        $isDebug = $this->isDebugMode();
        
        if ($isDebug) {
            return $this->renderDebugError($e);
        }
        
        // Log the error if possible
        if (function_exists('error_log')) {
            error_log(sprintf(
                'View Error [%s]: %s in %s:%d',
                $this->view,
                $e->getMessage(),
                $e->getFile(),
                $e->getLine()
            ));
        }
        
        return '<div style="padding: 1rem; color: #721c24;">Error rendering view</div>';
    }
    
    /**
     * Check if debug mode is enabled
     */
    private function isDebugMode(): bool
    {
        // Check various debug mode indicators
        if (defined('APP_DEBUG')) {
            // return (bool) $_ENV['APP_DEBUG'];
            return (bool) constant('APP_DEBUG');
        }
        
        if (isset($_ENV['APP_DEBUG'])) {
            return filter_var($_ENV['APP_DEBUG'], FILTER_VALIDATE_BOOLEAN);
        }
        
        if (getenv('APP_DEBUG') !== false) {
            return filter_var(getenv('APP_DEBUG'), FILTER_VALIDATE_BOOLEAN);
        }
        
        return false;
    }
    
    /**
     * Render debug error message
     */
    private function renderDebugError(\Throwable $e): string
    {
        $message = htmlspecialchars($e->getMessage());
        $file = htmlspecialchars($e->getFile());
        $line = $e->getLine();
        $trace = htmlspecialchars($e->getTraceAsString());
        $view = htmlspecialchars($this->view);
        
        return <<<HTML
        <div style="background: #f8d7da; color: #721c24; padding: 1.5rem; border: 2px solid #f5c6cb; border-radius: 4px; margin: 1rem; font-family: monospace;">
            <h3 style="margin-top: 0; color: #721c24;">View Rendering Error</h3>
            <p><strong>View:</strong> {$view}</p>
            <p><strong>Error:</strong> {$message}</p>
            <p><strong>File:</strong> {$file}:{$line}</p>
            <details style="margin-top: 1rem;">
                <summary style="cursor: pointer; font-weight: bold;">Stack Trace</summary>
                <pre style="margin-top: 0.5rem; padding: 1rem; background: #fff; border: 1px solid #ccc; overflow-x: auto;">{$trace}</pre>
            </details>
        </div>
        HTML;
    }
}