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
     * Variables/types to exclude during automatic collection.
     * Prevents leaking framework internals into the view scope.
     */
    private const AUTO_EXCLUDED_TYPES = [
        ViewEngineInterface::class,
        \Plugs\Container\Container::class,
        \Plugs\Router\Router::class,
        \Plugs\Http\MiddlewareDispatcher::class,
    ];

    private const AUTO_EXCLUDED_KEYS = [
        'view', 'engine', 'db', 'currentRequest',
        'middleware', 'container', 'app', 'kernel',
    ];

    /**
     * Add data to the view (multi-mode).
     *
     * Usage:
     *   ->with('key', $value)           // Manual: single key-value pair
     *   ->with('user', 'posts')         // Easy: collect named variables from caller (properties)
     *   ->with(['user' => $u])          // Manual: array of key-value pairs
     *
     * For local variable collection, pass get_defined_vars() as the LAST argument:
     *   ->with('user', 'posts', get_defined_vars())
     *
     * @return self Fluent interface
     */
    public function with(string|array ...$args): self
    {
        // with(['key' => 'value']) — array merge mode
        if (count($args) === 1 && is_array($args[0])) {
            $this->data = array_merge($this->data, $args[0]);
            return $this;
        }

        // with('key', $value) — exactly 2 args where second is any type
        // Detect: if 2 args AND second arg is NOT a plain string name → treat as key-value
        if (count($args) === 2 && is_string($args[0]) && !is_string($args[1])) {
            $this->data[$args[0]] = $args[1];
            return $this;
        }

        // with('user', 'posts', ...) — selective variable pickup
        // Check if last arg is an array (from get_defined_vars()), rest are strings
        $lastArg = end($args);
        $hasVarsArray = is_array($lastArg);

        if ($hasVarsArray) {
            // Last arg is get_defined_vars() result, preceding args are names
            $vars = $this->filterData($lastArg);
            $names = array_slice($args, 0, -1);

            // If only the array was passed (no names), merge all filtered vars
            if (empty($names)) {
                $this->data = array_merge($this->data, $vars);
                return $this;
            }

            foreach ($names as $name) {
                if (is_string($name) && array_key_exists($name, $vars)) {
                    $this->data[$name] = $vars[$name];
                }
            }

            return $this;
        }

        // All args are strings — selective pickup from caller properties
        if (count($args) >= 1 && array_reduce($args, fn($carry, $a) => $carry && is_string($a), true)) {
            /** @var string[] $names */
            $names = $args;
            $callerVars = $this->collectFromCaller();

            foreach ($names as $name) {
                if (array_key_exists($name, $callerVars)) {
                    $this->data[$name] = $callerVars[$name];
                }
            }

            return $this;
        }

        return $this;
    }

    /**
     * Automatically collect all safe variables and pass them to the view.
     *
     * Two modes:
     *   ->auto()                     // Collects public properties from the caller (controller)
     *   ->auto(get_defined_vars())   // Collects local variables from the calling scope
     *
     * Local variables like $user = User::find(1) can only be discovered
     * if you pass get_defined_vars() — PHP does not allow reading another
     * function's local scope.
     *
     * @param array|null $vars Optional: result of get_defined_vars() from the caller
     * @return self Fluent interface
     */
    public function auto(?array $vars = null): self
    {
        if ($vars !== null) {
            // Local variable mode — filter and merge
            $filtered = $this->filterData($vars);
            $this->data = array_merge($this->data, $filtered);
        } else {
            // Property mode — collect public properties from caller
            $callerVars = $this->collectFromCaller();
            $this->data = array_merge($this->data, $callerVars);
        }

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
     * Collect safe variables from the calling scope.
     *
     * Uses debug_backtrace() to find the caller (typically a controller method),
     * then extracts its public properties. Filters out framework internals.
     *
     * @return array<string, mixed>
     */
    private function collectFromCaller(): array
    {
        // Walk up the call stack to find the originating controller/caller
        $trace = debug_backtrace(DEBUG_BACKTRACE_PROVIDE_OBJECT, 6);

        $collected = [];

        foreach ($trace as $frame) {
            if (!isset($frame['object'])) {
                continue;
            }

            $obj = $frame['object'];

            // Skip the View class itself
            if ($obj instanceof self) {
                continue;
            }

            // Found a non-View caller — extract its public properties
            $reflection = new \ReflectionObject($obj);
            foreach ($reflection->getProperties(\ReflectionProperty::IS_PUBLIC) as $prop) {
                $name = $prop->getName();

                // Skip excluded property names
                if (in_array($name, self::AUTO_EXCLUDED_KEYS, true)) {
                    continue;
                }

                $value = $prop->getValue($obj);

                // Skip excluded types
                if (is_object($value)) {
                    foreach (self::AUTO_EXCLUDED_TYPES as $excludedType) {
                        if ($value instanceof $excludedType) {
                            continue 2;
                        }
                    }
                }

                $collected[$name] = $value;
            }

            break; // Only inspect the first non-View caller
        }

        return $this->filterData($collected);
    }

    /**
     * Filter data array to remove unsafe/internal keys.
     *
     * Removes superglobals, PHP internals, and framework engine references
     * from the data before passing to the template.
     *
     * @param array $data Raw data array
     * @return array Filtered data
     */
    private function filterData(array $data): array
    {
        $blocked = [
            'this', '_GET', '_POST', '_SERVER', '_SESSION',
            '_COOKIE', '_FILES', '_ENV', '_REQUEST', 'GLOBALS',
            '__DIR__', '__FILE__', '__LINE__', '__FUNCTION__', '__CLASS__',
        ];

        foreach ($blocked as $key) {
            unset($data[$key]);
        }

        // Remove any ViewEngineInterface instances that slipped through
        foreach ($data as $key => $value) {
            if ($value instanceof ViewEngineInterface) {
                unset($data[$key]);
            }
        }

        return $data;
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
     * @return void
     */
    public function dd(): void
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
