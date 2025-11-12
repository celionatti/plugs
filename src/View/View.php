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
|
| Represents a view instance with data binding and rendering capabilities.
| This class acts as a value object for view rendering operations.
|
| @package Plugs\View
*/

use RuntimeException;
use Throwable;

class View
{
    /**
     * View engine instance
     */
    private ViewEngine $engine;

    /**
     * View template name
     */
    private string $view;

    /**
     * View data array
     */
    private array $data;

    /**
     * Maximum number of stack trace lines to display
     */
    private const MAX_TRACE_LINES = 10;

    /**
     * Create a new View instance
     *
     * @param ViewEngine $engine View engine for rendering
     * @param string $view View template name
     * @param array $data Initial data for the view
     */
    public function __construct(ViewEngine $engine, string $view, array $data = [])
    {
        $this->engine = $engine;
        $this->view = $view;
        $this->data = $data;
    }

    /**
     * Add a single data item to the view
     *
     * @param string $key Data key
     * @param mixed $value Data value
     * @return self Fluent interface
     */
    public function with(string $key, $value): self
    {
        $this->data[$key] = $value;
        return $this;
    }

    /**
     * Add multiple data items to the view
     *
     * @param array $data Array of data to merge
     * @return self Fluent interface
     */
    public function withData(array $data): self
    {
        $this->data = array_merge($this->data, $data);
        return $this;
    }

    /**
     * Get the view engine instance
     *
     * @return ViewEngine
     */
    public function getEngine(): ViewEngine
    {
        return $this->engine;
    }

    /**
     * Get the view template name
     *
     * @return string
     */
    public function getView(): string
    {
        return $this->view;
    }

    /**
     * Get the view data
     *
     * @return array
     */
    public function getData(): array
    {
        return $this->data;
    }

    /**
     * Render the view to a string
     *
     * @return string Rendered view content
     * @throws RuntimeException If view rendering fails
     */
    public function render(): string
    {
        try {
            return $this->engine->render($this->view, $this->data);
        } catch (Throwable $e) {
            throw new RuntimeException(
                sprintf(
                    'Error rendering view [%s]: %s',
                    $this->view,
                    $e->getMessage()
                ),
                (int) $e->getCode(),
                $e
            );
        }
    }

    /**
     * Magic method to convert view to string
     *
     * @return string Rendered view content or error message
     */
    public function __toString(): string
    {
        try {
            return $this->render();
        } catch (Throwable $e) {
            return $this->handleToStringError($e);
        }
    }

    /**
     * Handle errors in __toString method
     *
     * Since __toString cannot throw exceptions, we handle them gracefully
     * by logging and returning an error message.
     *
     * @param Throwable $e The exception that occurred
     * @return string Error message HTML
     */
    private function handleToStringError(Throwable $e): string
    {
        // Log the error
        $this->logError($e);

        // Return appropriate error message based on environment
        if ($this->isDebugMode()) {
            return $this->renderDebugError($e);
        }

        return '<div style="padding: 1rem; color: #721c24; background: #f8d7da; border: 1px solid #f5c6cb;">'
            . 'Error rendering view. Please check logs for details.'
            . '</div>';
    }

    /**
     * Log error if logging is available
     *
     * @param Throwable $e Exception to log
     * @return void
     */
    private function logError(Throwable $e): void
    {
        if (function_exists('error_log')) {
            error_log(
                sprintf(
                    'View Error [%s]: %s in %s:%d',
                    $this->view,
                    $e->getMessage(),
                    $e->getFile(),
                    $e->getLine()
                )
            );
        }
    }

    /**
     * Check if debug mode is enabled
     *
     * @return bool
     */
    private function isDebugMode(): bool
    {
        // Check constant first (most performant)
        if (defined('APP_DEBUG')) {
            return (bool) constant('APP_DEBUG');
        }

        // Check $_ENV
        if (isset($_ENV['APP_DEBUG'])) {
            return filter_var($_ENV['APP_DEBUG'], FILTER_VALIDATE_BOOLEAN);
        }

        // Check getenv as fallback
        $envDebug = getenv('APP_DEBUG');
        if ($envDebug !== false) {
            return filter_var($envDebug, FILTER_VALIDATE_BOOLEAN);
        }

        // Default to false for production safety
        return false;
    }

    /**
     * Render debug error message with full details
     *
     * @param Throwable $e Exception to render
     * @return string HTML error display
     */
    private function renderDebugError(Throwable $e): string
    {
        $message = htmlspecialchars($e->getMessage(), ENT_QUOTES, 'UTF-8');
        $file = htmlspecialchars($e->getFile(), ENT_QUOTES, 'UTF-8');
        $line = $e->getLine();
        $view = htmlspecialchars($this->view, ENT_QUOTES, 'UTF-8');

        // FIX: Limit stack trace to prevent excessive information disclosure
        $fullTrace = $e->getTraceAsString();
        $traceLines = explode("\n", $fullTrace);
        $limitedTraceLines = array_slice($traceLines, 0, self::MAX_TRACE_LINES);
        $limitedTrace = implode("\n", $limitedTraceLines);

        if (count($traceLines) > self::MAX_TRACE_LINES) {
            $limitedTrace .= "\n... (" . (count($traceLines) - self::MAX_TRACE_LINES) . " more lines)";
        }

        $trace = htmlspecialchars($limitedTrace, ENT_QUOTES, 'UTF-8');

        return <<<HTML
        <div style="background: #f8d7da; color: #721c24; padding: 1.5rem; border: 2px solid #f5c6cb; border-radius: 4px; margin: 1rem; font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', sans-serif;">
            <h3 style="margin-top: 0; color: #721c24; font-size: 1.25rem;">View Rendering Error</h3>
            <p style="margin: 0.5rem 0;"><strong>View:</strong> {$view}</p>
            <p style="margin: 0.5rem 0;"><strong>Error:</strong> {$message}</p>
            <p style="margin: 0.5rem 0;"><strong>File:</strong> {$file}:{$line}</p>
            <details style="margin-top: 1rem;">
                <summary style="cursor: pointer; font-weight: bold; padding: 0.5rem; background: #f5c6cb; border-radius: 4px;">Stack Trace (limited to {$this->getTraceLineCount($traceLines)} lines)</summary>
                <pre style="margin-top: 0.5rem; padding: 1rem; background: #fff; border: 1px solid #ccc; overflow-x: auto; font-size: 0.875rem; line-height: 1.5;">{$trace}</pre>
            </details>
        </div>
        HTML;
    }

    /**
     * Get the count of trace lines to display
     *
     * @param array $traceLines Full trace lines
     * @return int Number of lines shown
     */
    private function getTraceLineCount(array $traceLines): int
    {
        return min(count($traceLines), self::MAX_TRACE_LINES);
    }
}
