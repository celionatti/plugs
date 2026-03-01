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
    private ViewEngineInterface $engine;

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
     * Response headers to send
     */
    private array $headers = [];

    /**
     * HTTP status code
     */
    private int $statusCode = 200;

    /**
     * Sections to exclude from rendering
     */
    private array $excludedSections = [];

    /**
     * Create a new View instance
     *
     * @param ViewEngineInterface $engine View engine for rendering
     * @param string $view View template name
     * @param array $data Initial data for the view
     */
    public function __construct(ViewEngineInterface $engine, string $view, array $data = [])
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
     * @return ViewEngineInterface
     */
    public function getEngine(): ViewEngineInterface
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
            $start = microtime(true);
            $content = $this->engine->render($this->view, $this->data);
            $duration = (microtime(true) - $start) * 1000;

            if (class_exists(\Plugs\Debug\Profiler::class)) {
                \Plugs\Debug\Profiler::getInstance()->addView($this->view, $duration);
            }

            return $content;
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
        return ErrorRenderer::render($e, $this->view, $this->data);
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
    public function isDebugMode(): bool
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



    // ============================================
    // NEW CHAINABLE RESPONSE METHODS
    // ============================================

    /**
     * Add headers to the response
     *
     * @param array $headers Key-value pairs of headers
     * @return self Fluent interface
     */
    public function withHeaders(array $headers): self
    {
        $this->headers = array_merge($this->headers, $headers);

        return $this;
    }

    /**
     * Add a single header to the response
     *
     * @param string $name Header name
     * @param string $value Header value
     * @return self Fluent interface
     */
    public function withHeader(string $name, string $value): self
    {
        $this->headers[$name] = $value;

        return $this;
    }

    /**
     * Set the HTTP status code
     *
     * @param int $code HTTP status code
     * @return self Fluent interface
     */
    public function withStatus(int $code): self
    {
        $this->statusCode = $code;

        return $this;
    }

    /**
     * Get the configured headers
     *
     * @return array
     */
    public function getHeaders(): array
    {
        return $this->headers;
    }

    /**
     * Get the configured status code
     *
     * @return int
     */
    public function getStatusCode(): int
    {
        return $this->statusCode;
    }

    /**
     * Send the response to the browser
     *
     * @return void
     */
    public function send(): void
    {
        // Set status code
        if ($this->statusCode !== 200) {
            http_response_code($this->statusCode);
        }

        // Send headers
        foreach ($this->headers as $name => $value) {
            header("$name: $value");
        }

        // Output rendered content
        echo $this->render();
    }

    // ============================================
    // PARTIAL RENDERING METHODS
    // ============================================

    /**
     * Render only a specific section/fragment from the view
     *
     * @param string $section Section name to render
     * @return string Rendered section content
     */
    public function renderOnly(string $section): string
    {
        return $this->engine->renderFragment($this->view, $section, $this->data);
    }

    /**
     * Exclude sections from rendering
     *
     * @param string|array $sections Section name(s) to exclude
     * @return self Fluent interface
     */
    public function without(string|array $sections): self
    {
        $sections = is_array($sections) ? $sections : [$sections];
        $this->excludedSections = array_merge($this->excludedSections, $sections);
        $this->data['__excludedSections'] = $this->excludedSections;

        return $this;
    }

    /**
     * Get a specific fragment from the view
     * Alias for renderOnly for HTMX/Turbo usage
     *
     * @param string $name Fragment name
     * @return string Fragment content
     */
    public function fragment(string $name): string
    {
        return $this->renderOnly($name);
    }

    /**
     * Render the view smartly based on request type
     * Uses HTMX/Turbo headers to determine partial vs full rendering
     *
     * @return string Rendered content
     */
    public function renderSmart(): string
    {
        return $this->engine->renderSmart($this->view, $this->data);
    }

    // ============================================
    // DEBUGGING HELPERS
    // ============================================

    /**
     * Dump view data and die
     *
     * @return never
     */
    public function dd(): never
    {
        $this->dump();
        exit(1);
    }

    /**
     * Dump view data for debugging
     *
     * @return self Fluent interface
     */
    public function dump(): self
    {
        echo '<div style="background: #1a1a2e; color: #e0e0e0; padding: 1rem; margin: 1rem; border-radius: 8px; font-family: monospace;">';
        echo '<h4 style="color: #00d9ff; margin-top: 0;">View Debug: ' . htmlspecialchars($this->view) . '</h4>';
        echo '<pre style="overflow-x: auto; font-size: 12px;">';

        // Dump data
        echo '<strong style="color: #ffd700;">Data:</strong> ';
        var_export($this->data);

        echo '</pre>';
        echo '<hr style="border-color: #333; margin: 1rem 0;">';
        echo '<p style="color: #888; margin-bottom: 0;"><small>';
        echo 'Headers: ' . count($this->headers) . ' | ';
        echo 'Status: ' . $this->statusCode . ' | ';
        echo 'Template: ' . $this->view;
        echo '</small></p>';
        echo '</div>';

        return $this;
    }

    /**
     * Get debug information as array
     *
     * @return array Debug info
     */
    public function debug(): array
    {
        return [
            'view' => $this->view,
            'data' => $this->data,
            'headers' => $this->headers,
            'status_code' => $this->statusCode,
            'excluded_sections' => $this->excludedSections,
            'engine_class' => get_class($this->engine),
        ];
    }

    /**
     * Convert view to JSON for AJAX/API responses
     *
     * @return string JSON encoded data
     */
    public function toJson(): string
    {
        return json_encode([
            'html' => $this->render(),
            'view' => $this->view,
        ], JSON_THROW_ON_ERROR);
    }

    /**
     * Create a response for HTMX requests
     * Includes rendered HTML and any configured headers
     *
     * @return void
     */
    public function htmxResponse(): void
    {
        // Add HTMX-specific headers if teleport content exists
        $teleportScripts = $this->engine->getTeleportScripts();
        if (!empty($teleportScripts)) {
            $this->withHeader('HX-Trigger-After-Settle', 'teleport-ready');
        }

        $this->send();
    }
}
