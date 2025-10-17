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
     * Render the view
     */
    public function render(): string
    {
        try {
            // Pass the view engine to the data so includes can work
            $this->data['view'] = $this->engine;
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
            if (defined('APP_DEBUG') && $_ENV['APP_DEBUG']) {
                return sprintf(
                    '<div style="background: #f8d7da; color: #721c24; padding: 1rem; border: 1px solid #f5c6cb;">' .
                    '<h3>View Error</h3><p>%s</p><pre>%s</pre></div>',
                    htmlspecialchars($e->getMessage()),
                    htmlspecialchars($e->getTraceAsString())
                );
            }
            return '<div>Error rendering view</div>';
        }
    }
}